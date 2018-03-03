<?php
/**
 * Created by PhpStorm.
 * User: will202
 * Date: 1/9/18
 * Time: 5:32 PM
 */

namespace Pnnl\PrettyJSONYAML\Linter;

use GrumPHP\Collection\LintErrorsCollection;
use GrumPHP\Util\Filesystem;
use Pnnl\PrettyJSONYAML\Exception\OrderException;
use Pnnl\PrettyJSONYAML\Parser\ParserInterface;
use Seld\JsonLint\ParsingException;
use SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;

abstract class AbstractPrettyLinter implements LinterInterface
{

    /**
     * How to interact with files
     *
     * @var Filesystem $filesystem
     */
    protected $filesystem;

    /**
     * Parser to convert the string content into a structured array
     *
     * @var ParserInterface $parser
     */
    protected $parser;

    /**
     * The string content of the data file to be parsed
     *
     * @var string $content
     */
    private $content;

    /**
     * The data array to be sorted
     *
     * @var array $data
     */
    protected $data;

    /**
     * The sorted prettified data object
     *
     * @var string $sorted
     */
    protected $sorted;

    /**
     * Number of spaces to use for the indent
     *
     * @var int $indent
     */
    protected $indent = 2;

    /**
     * Whether or not to sort the keys alphabetically
     *
     * @var bool $sort
     */
    protected $sort = true;

    /**
     * A sorted list of keys to keep at the top of the alphabetical list
     *
     * @var array $topKeys
     */
    protected $topKeys;

    /**
     * AbstractPrettyLinter constructor.
     *
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }


    public function lint(SplFileInfo $file)
    {
        $errors = new LintErrorsCollection();

        try {
            // Read the data from the file
            $this->content = $this->filesystem->readFromFileInfo($file);
            $this->data = $this->parser->parse($this->content);
            // Sort the data and convert back to a string
            $this->sort($this->data);
            $this->sorted = $this->parser->dump($this->data);
            // TODO: Compare $content with $sorted fail if different
            if ($this->content != $this->sorted) {
                // TODO: Get filename, linenumber, etc. added to exception.
                /*
                 * Split both into lines
                 * Compare each line to get line number that is different
                 */
                throw new OrderException("File is not sorted properly", -1, null, $file->getFilename());
            }

            // TODO: Ensure sorted file is consistent with .editorconfig file
            /*
             * Figure out how to write
             * - Indent
             * - Line ending
             * - Tab style
             * - empty line at end of file
             *
             * from settings in .editorconfig
             * Maybe should just be settings in grumphp config
             */
        } catch (OrderException $e) {
            $e->setParsedFile($file->getPathname());
            $errors[] = self::errorFromOrderException($e);
        } catch (ParseException $e) {
            $e->setParsedFile($file->getPathname());
            $errors[] = self::errorFromParseException($e);
        } catch (ParsingException $e) {
            $errors[] = self::errorFromParsingException($e);
        }
    }

    /**
     * Sorts the passed array
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
                $before[] = $value;
            } else {
                $after[] = $value;
            }
        }
        // Sort $before according to order listed in $this->topKeys
        $before = $this->sortByTopKeys($before);

        // Sort $after alphabetically
        if ($this->sort) {
            sort($after);
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
        $sorted = array_intersect_assoc($merged, $data);
        // Return sorted array
        return $sorted;
    }

}