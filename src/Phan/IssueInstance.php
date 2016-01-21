<?php declare(strict_types=1);
namespace Phan;

class IssueInstance {

    /** @var Issue */
    private $issue;

    /** @var array */
    private $template_parameters;

    /** @var string */
    private $file;

    /** @var int */
    private $line;

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
        $this->template_parameters = $template_parameters;
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * @return Issue
     */
    public function getIssue() : Issue {
        return $this->issue;
    }

    /**
     * @return string
     */
    public function getFile() : string {
        return $this->file;
    }

    /**
     * @return int
     */
    public function getLine() : int {
        return $this->line;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return vsprintf($this->getIssue()->getTemplate(), $this->template_parameters);
    }
}
