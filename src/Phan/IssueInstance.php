<?php declare(strict_types=1);
namespace Phan;

use Phan\Output\Colorizing;

class IssueInstance
{
    /** @var Issue */
    private $issue;

    /** @var string */
    private $file;

    /** @var int */
    private $line;

    /** @var string */
    private $message;

    /**
     * @param Issue $issue
     * @param string $file
     * @param int $line
     * @param array $template_parameters
     */
    public function __construct(
        Issue $issue,
        string $file,
        int $line,
        array $template_parameters
    ) {
        $this->issue = $issue;
        $this->file = $file;
        $this->line = $line;

        // color_issue_message will interfere with some formatters, such as xml.
        if (Config::getValue('color_issue_messages')) {
            $this->message = self::generateColorizedMessage($issue, $template_parameters);
        } else {
            $this->message = self::generatePlainMessage($issue, $template_parameters);
        }
    }

    private static function generatePlainMessage(
        Issue $issue,
        array $template_parameters
    ) : string {
        $template = $issue->getTemplate();

        // markdown_issue_messages doesn't make sense with color, unless you add <span style="color:red">msg</span>
        // Not sure if codeclimate supports that.
        if (Config::getValue('markdown_issue_messages')) {
            $template = preg_replace(
                '/([^ ]*%s[^ ]*)/',
                '`\1`',
                $template
            );
        }
        return vsprintf(
            $template,
            $template_parameters
        );
    }

    private static function generateColorizedMessage(
        Issue $issue,
        array $template_parameters
    ) : string {
        $template = $issue->getTemplateRaw();

        return Colorizing::colorizeTemplate($template, $template_parameters);
    }

    /**
     * @return Issue
     */
    public function getIssue() : Issue
    {
        return $this->issue;
    }

    /**
     * @return string
     */
    public function getFile() : string
    {
        return $this->file;
    }

    /**
     * @return int
     */
    public function getLine() : int
    {
        return $this->line;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    public function __toString() : string
    {
        return "{$this->getFile()}:{$this->getLine()} {$this->getMessage()}";
    }
}
