<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\UnionType;

/**
 * Any PHP structural element that also has a type and is
 * addressable such as a class, method, closure, property,
 * constant, variable, ...
 */
interface TypedElementInterface
{
    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    public function getName() : string;

    /**
     * @return UnionType
     * Get the type of this structural element
     */
    public function getUnionType() : UnionType;

    /**
     * @param UnionType $type
     * Set the type of this element
     *
     * @return void
     */
    public function setUnionType(UnionType $type);

    /**
     * @return Context
     * The context in which this structural element exists
     */
    public function getContext() : Context;

    /**
     * @return FileRef
     * A reference to where this element was found
     */
    public function getFileRef() : FileRef;

    /**
     * @return bool
     * True if this element is marked as deprecated
     */
    public function isDeprecated() : bool;

    /**
     * @param bool $is_deprecated
     * Set this element as deprecated
     *
     * @return void
     */
    public function setIsDeprecated(bool $is_deprecated);

    /**
     * @param string[] $suppress_issue_list
     * Set the set of issue names to suppress
     *
     * @return void
     */
    public function setSuppressIssueList(array $suppress_issue_list);

    /**
     * return bool
     * True if this element would like to suppress the given
     * issue name
     */
    public function hasSuppressIssue(string $issue_name) : bool;

    /**
     * @return bool
     * True if this was an internal PHP object
     */
    public function isPHPInternal() : bool;

    /**
     * This method must be called before analysis
     * begins.
     *
     * @return void
     */
    public function hydrate(CodeBase $code_base);
}
