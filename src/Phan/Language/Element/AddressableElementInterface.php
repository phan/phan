<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\FQSEN;

/**
 * An AddressableElementInterface is a TypedElementInterface with an FQSEN.
 * (e.g. represents a class, property, function, etc.)
 */
interface AddressableElementInterface extends TypedElementInterface
{
    /**
     * @return FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN();

    /**
     * Sets the fully qualified structural element name of this element.
     * @param FQSEN $fqsen
     * @return void
     */
    public function setFQSEN(FQSEN $fqsen);

    /**
     * @return bool true if this element's visibility
     *                   is strictly more visible than $other (public > protected > private)
     */
    public function isStrictlyMoreVisibleThan(AddressableElementInterface $other) : bool;

    /**
     * @return bool
     * True if this is a public property
     */
    public function isPublic() : bool;

    /**
     * @return bool
     * True if this is a protected property
     */
    public function isProtected() : bool;

    /**
     * @return bool
     * True if this is a private property
     */
    public function isPrivate() : bool;

    /**
     * Track a location $file_ref in which this typed structural element
     * is referenced.
     *
     * @param FileRef $file_ref
     * @return void
     */
    public function addReference(FileRef $file_ref);

    /**
     * @return FileRef[]
     * A list of references to this typed structural element.
     */
    public function getReferenceList() : array;

    /**
     * @param CodeBase $code_base
     * Some elements may need access to the code base to
     * figure out their total reference count.
     *
     * @return int
     * The number of references to this typed structural element
     */
    public function getReferenceCount(
        CodeBase $code_base
    ) : int;

    /**
     * @return string For use in the language server protocol.
     */
    public function getMarkupDescription() : string;

    /**
     * @return ?string the 'docComment' for this element, if any exists.
     */
    public function getDocComment();

    /**
     * @return Context
     * The context in which this structural element exists
     */
    public function getContext() : Context;

    /**
     * @return bool
     * True if this element is marked as deprecated
     */
    public function isDeprecated() : bool;

    /**
     * Set this element as deprecated or not deprecated
     *
     * @param bool $is_deprecated
     *
     * @return void
     */
    public function setIsDeprecated(bool $is_deprecated);

    /**
     * Set the list of issue names ($suppress_issue_list) to suppress
     *
     * @param array<int,string> $suppress_issue_list
     *
     * @return void
     * @deprecated
     */
    public function setSuppressIssueList(array $suppress_issue_list);

    /**
     * Set the set of issue names ($suppress_issue_list) to suppress
     *
     * @param array<string,int> $suppress_issue_set
     *
     * @return void
     */
    public function setSuppressIssueSet(array $suppress_issue_set);

    /**
     * @return array<string,int>
     * Returns a map from issue name to count of suppressions
     */
    public function getSuppressIssueList() : array;

    /**
     * Increments the number of times $issue_name was suppressed.
     * @return void
     */
    public function incrementSuppressIssueCount(string $issue_name);

    /**
     * return bool
     * True if this element would like to suppress the given
     * issue name
     */
    public function hasSuppressIssue(string $issue_name) : bool;

    /**
     * @return bool
     * True if this element would like to suppress the given
     * issue name.
     *
     * If this is true, this automatically calls incrementSuppressIssueCount.
     * Most callers should use this, except for uses similar to UnusedSuppressionPlugin
     */
    public function checkHasSuppressIssueAndIncrementCount(string $issue_name) : bool;

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
