<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Database;
use \Phan\Language\Context;
use \Phan\Language\FileRef;
use \Phan\Language\UnionType;

/**
 * Any PHP structural element that also has a type and is
 * addressable such as a class, method, closure, property,
 * constant, variable, ...
 */
abstract class TypedElement implements TypedElementInterface
{
    use \Phan\Memoize;

    /**
     * @var string
     * The name of the typed structural element
     */
    private $name;

    /**
     * @var UnionType
     * A set of types satisfyped by this typed structural
     * element.
     */
    private $type = null;

    /**
     * @var int
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    private $flags = 0;


    /**
     * @var Context
     * The context in which the structural element lives
     */
    private $context = null;

    /**
     * @var string[]
     * A set of issues types to be suppressed
     */
    private $suppress_issue_list = [];

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags
    ) {
        $this->context = $context;
        $this->name = $name;
        $this->type = $type;
        $this->flags = $flags;

        $this->setIsInternal($context->isInternal());
    }

    /**
     * After a clone is called on this object, clone our
     * type and fqsen so that they survive copies intact
     *
     * @return null
     */
    public function __clone()
    {
        $this->context = $this->context
            ? clone($this->context)
            : $this->context;

        $this->type = $this->type
            ? clone($this->type)
            : $this->type;
    }

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return UnionType
     * Get the type of this structural element
     */
    public function getUnionType() : UnionType
    {
        return $this->type;
    }

    /**
     * @param UnionType $type
     * Set the type of this element
     *
     * @return void
     */
    public function setUnionType(UnionType $type)
    {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getFlags() : int
    {
        return $this->flags;
    }

    /**
     * @param int $flags
     *
     * @return void
     */
    public function setFlags(int $flags)
    {
        $this->flags = $flags;
    }

    /**
     * @return Context
     * The context in which this structural element exists
     */
    public function getContext() : Context
    {
        return $this->context;
    }

    /**
     * @return FileRef
     * A reference to where this element was found
     */
    public function getFileRef() : FileRef
    {
        // TODO: Kill the context and make this a pure
        //       FileRef.
        return $this->context;
    }

    /**
     * @return bool
     * True if this element is marked as deprecated
     */
    public function isDeprecated() : bool
    {
        return Flags::bitVectorHasState(
            $this->flags,
            Flags::IS_DEPRECATED
        );
    }

    /**
     * @param bool $is_deprecated
     * Set this element as deprecated
     *
     * @return void
     */
    public function setIsDeprecated(bool $is_deprecated)
    {
        $this->flags = Flags::bitVectorWithState(
            $this->flags,
            Flags::IS_DEPRECATED,
            $is_deprecated
        );
    }

    /**
     * @param string[] $suppress_issue_list
     * Set the set of issue names to suppress
     *
     * @return void
     */
    public function setSuppressIssueList(array $suppress_issue_list)
    {
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
    public function hasSuppressIssue(string $issue_name) : bool
    {
        return isset($this->suppress_issue_list[$issue_name]);
    }

    /**
     * @return bool
     * True if this was an internal PHP object
     */
    public function isInternal() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            Flags::IS_INTERNAL
        );
    }

    /**
     * @return void
     */
    private function setIsInternal(bool $is_internal)
    {
        $this->setFlags(
            Flags::bitVectorWithState(
                $this->getFlags(),
                Flags::IS_INTERNAL,
                $is_internal
            )
        );
    }

    /**
     * This method must be called before analysis
     * begins.
     *
     * @return void
     */
    public function hydrate(CodeBase $code_base) {
        return $this->memoize(__METHOD__, function() use ($code_base) {
            $this->hydrateOnce($code_base );
        });
    }

    /**
     * This method must be called before analysis
     * begins.
     *
     * @return void
     */
    protected function hydrateOnce(CodeBase $code_base) {
        // Do nothing unless overridden
    }

}
