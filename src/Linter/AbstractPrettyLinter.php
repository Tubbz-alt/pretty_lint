<?php
/**
 * Created by PhpStorm.
 * User: will202
 * Date: 1/9/18
 * Time: 5:32 PM
 */

namespace Pnnl\PrettyJSONYAML\Linter;

use GrumPHP\Collection\LintErrorsCollection;
use GrumPHP\Linter\Json\JsonLintError;
use GrumPHP\Linter\LinterInterface;
use GrumPHP\Linter\Yaml\YamlLintError;
use Pnnl\PrettyJSONYAML\Exception\OrderException;
use Pnnl\PrettyJSONYAML\Parser\ParserInterface;
use Seld\JsonLint\ParsingException;
use SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;

abstract class AbstractPrettyLinter implements LinterInterface
{

    /** @var bool */
    protected $autoFix = true;

    /** @var string $content - The string content of the data file to be parsed */
    protected $content;

    /** @var array $data - The data array to be sorted */
    protected $data;

    /** @var int $indent - Number of spaces to use for the indent */
    protected $indent;

    /** @var ParserInterface $parser - Parser to convert the string content into a structured array */
    protected $parser;

    /** @var bool $sort - Whether or not to sort the keys alphabetically */
    protected $sort = true;

    /** @var string $sorted - The sorted prettified data object */
    protected $sorted;

    /** @var array $topKeys - A sorted list of keys to keep at the top of the alphabetical list */
    protected $topKeys = [];

    /**
     * AbstractPrettyLinter constructor.
     *
     * @param ParserInterface $parser
     */
    public function __construct(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @param SplFileInfo $file
     *
     * @return LintErrorsCollection
     */
    public function lint(SplFileInfo $file)
    {
        $errors = new LintErrorsCollection();

        try {
            // Read the data from the file
            $this->data = $this->parser->parseFile($file);
            $this->content = $this->parser->dump($this->data);
            // Sort the data and convert back to a string
            $this->sort($this->data);
            $this->sorted = $this->parser->dump($this->data);
            // Compare $content with $sorted - fail if different
            if ($this->content != $this->sorted) {
                /*
                 * Split both into lines
                 * Compare each line to get line number that is different
                 */
                $orig = explode("\n", $this->content);
                $sort = explode("\n", $this->sorted);
                $count = count($orig);
                $lineNumber = -1;
                $snippet = null;
                for ($i = 0; $i < $count; $i++) {
                    if ($orig[$i] != $sort[$i]) {
                        $lineNumber = $i + 1;
                        $snippet = $orig[$i];
                        break;
                    }
                }
                if ($this->autoFix) {
                    $this->parser->dumpFile($this->data, $file);
                }
                throw new OrderException(
                  "Improper object sort",
                  $lineNumber,
                  $snippet,
                  $file->getFilename()
                );
            }
        } catch (OrderException $e) {
            $errors[] = PrettyLintError::fromOrderException($e);
        } catch (ParseException $e) {
            $e->setParsedFile($file->getPathname());
            $errors[] = YamlLintError::fromParseException($e);
        } catch (ParsingException $e) {
            $errors[] = JsonLintError::fromParsingException($file, $e);
        }
        return $errors;
    }

    /**
     * @param bool $autoFix
     */
    public function setAutoFix($autoFix)
    {
        $this->autoFix = $autoFix;
    }

    public function setTopKeys(array $keys)
    {
        $this->topKeys = $keys;
    }

    /**
     * Sorts the passed array
     *
     * @param array $data
     *
     * @return void
     * @throws OrderException
     */
    private function sort(array &$data)
    {
        // Don't sort $data if all numeric keys
        if (count(array_filter(array_keys($data), 'is_string')) == 0) {
            return;
        }

        $before = [];
        $after = [];

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $this->sort($value);
            }

            if (in_array($key, $this->topKeys)) {
                $before[$key] = $value;
            } else {
                $after[$key] = $value;
            }
        }
        // Sort $before according to order listed in $this->topKeys
        $before = $this->sortByTopKeys($before);

        // Sort $after alphabetically
        if ($this->sort) {
            ksort($after);
        }

        // Set $data to combined sorted data
        $data = $before + $after;
        return;
    }

    /**
     * Sort array by values listed in $this->topKeys
     *
     * @param array $data The array to sort
     *
     * @return array The sorted array
     */
    private function sortByTopKeys(array $data)
    {
        // Make the desired values sorted keys (instead of numeric keys)
        $keys = array_flip($this->topKeys);
        // Merge arrays to sort by order in $keys
        $merged = array_merge($keys, $data);
        // Remove any keys not in $data
        $sorted = @array_intersect_assoc($merged, $data);
        // Return sorted array
        return $sorted;
    }
}
