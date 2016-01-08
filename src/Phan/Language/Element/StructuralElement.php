<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;

/**
 * Any PHP structural element such as a property, constant
 * class, method, closure, ...
 */
abstract class StructuralElement {

    /**
     * @var Context
     * The context in which the structural element lives
     */
    private $context = null;

    /**
     * @var bool
     * True if this element is marked as deprecated
     */
    private $is_deprecated = false;

    /**
     * @var string[]
     * A set of issues types to be suppressed
     */
    private $suppress_issue_list = [];

    /**
     * @param Context $context
     * The context in which the structural element lives
     */
    public function __construct(
        Context $context
    ) {
        $this->context = $context;
    }

    /**
     * After a clone is called on this object, clone our
     * deep objects.
     *
     * @return null
     */
    public function __clone() {
        $this->context = $this->context
            ? clone($this->context)
            : $this->context;
    }

    /**
     * @return Context
     * The context in which this structural element exists
     */
    public function getContext() : Context {
        return $this->context;
    }

    /**
     * @return bool
     * True if this element is marked as deprecated
     */
    public function isDeprecated() : bool {
        return $this->is_deprecated;
    }

    /**
     * @param bool $is_deprecated
     * Set this element as deprecated
     *
     * @return null
     */
    public function setIsDeprecated(bool $is_deprecated) {
        $this->is_deprecated = $is_deprecated;
    }

    /**
     * @param string[] $suppress_issue_list
     * Set the set of issue names to suppress
     *
     * @return void
     */
    public function setSuppressIssueList(array $suppress_issue_list) {
        $this->suppress_issue_list = [];
        foreach ($suppress_issue_list as $i => $issue_name) {
            $this->suppress_issue_list[$issue_name] = $issue_name;
        }
    }

    /**
     * return bool
     * True if this element would like to suppress the given
     * issue name
     */
    public function hasSuppressIssue(string $issue_name) : bool {
        return isset($this->suppress_issue_list[$issue_name]);
    }

    /**
     * @return bool
     * True if this was an internal PHP object
     */
    public function isInternal() : bool {
        return 'internal' === $this->getContext()->getFile();
    }
}
