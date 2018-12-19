<?php
declare(strict_types=1);

/**
 * This class will update and save config setting documentation (for .phan/config.php) in a markdown format that can be uploaded to Phan's wiki
 */
class WikiWriter
{
    /**
     * @var string the built up contents to save to the markdown file
     */
    private $contents = '';

    /**
     * @var bool should this print to stdout while building up the markdown contents?
     * Useful for debugging.
     */
    private $print_to_stdout;

    public function __construct(bool $print_to_stdout = true)
    {
        $this->print_to_stdout = $print_to_stdout;
    }

    /**
     * Append $text to the buffer of text to save.
     * @return void
     */
    public function append(string $text)
    {
        $this->contents .= $text;
        if ($this->print_to_stdout) {
            echo $text;
        }
    }

    /**
     * @return string the built up contents to save to the markdown file
     */
    public function getContents() : string
    {
        return $this->contents;
    }
}
