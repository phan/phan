<?php declare(strict_types=1);
namespace Phan;

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
        $this->message = vsprintf(
            $issue->getTemplate(),
            $template_parameters
        );
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
