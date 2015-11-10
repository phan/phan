<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\CodeBase;

/**
 * A Fully-Qualified Structural Element Name
 */
class FQSEN {
    /**
     * @var string
     * The namespace in an object's scope
     */
    private $namespace = '\\';

    /**
     * @var string
     * A class name if one is in scope or the empty
     * string otherwise.
     */
    private $class_name = '';

    /**
     * @var string
     * A method name if one is in scope or the empty
     * string otherwise.
     */
    private $method_name = '';
    /**
     *
     * @var string
     * A closure name if one is in scope or the empty
     * string otherwise.
     */
    private $closure_name = '';

    /**
     * @var int
     * An identifier for which alternative form
     * of this FQSEN.
     */
    private $alternate_id = 0;

    /**
     * @param string $namespace
     * The namespace in an object's scope
     *
     * @param string $class_name
     * A class name if one is in scope or the empty
     * string otherwise.
     *
     * @param string $method_name
     * A method name if one is in scope or the empty
     * string otherwise.
     *
     * @param string $closure_name
     * A closure name if one is in scope or the empty
     * string otherwise.
     */
    public function __construct(
        string $namespace = '\\',
        string $class_name = '',
        string $method_name = '',
        string $closure_name = ''
    ) {
        $this->namespace = self::cleanNamespace($namespace);
        $this->class_name = $class_name;
        $this->method_name = $method_name;
        $this->closure_name = $closure_name;
    }

    /**
     * @return FQSEN
     * A fully-qualified structural element name describing
     * the given Context.
     */
    public static function fromContext(Context $context) : FQSEN {
        return $context->getScopeFQSEN();
    }

    /**
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @return FQSEN
     */
    public static function fromContextAndString(
        Context $context,
        string $fqsen_string
    ) : FQSEN {
        $elements =
            explode('::', $fqsen_string);

        $fq_class_name = $elements[0] ?? '';

        $method_elements = $elements[1] ?? '';

        $matches = [];
        preg_match('/^([^{]*)({(.*)})?$/', $method_elements, $matches);

        $method_name = $matches[1] ?? '';
        $closure_name = $matches[3] ?? '';

        $fq_class_name_elements =
            array_filter(explode('\\', $fq_class_name));

        $class_name =
            array_pop($fq_class_name_elements);

        $namespace =
            '\\' . implode('\\', $fq_class_name_elements);

        // If we're not fully qualified, check to see if its mapped
        if (0 !== strpos($fqsen_string, '\\')) {
            // Check for a class name map
            if ($class_name
                && $context->hasNamespaceMapFor(T_CLASS, $class_name)
            ) {
                $namespace =
                    (string)$context->getNamespaceMapFor(
                        T_CLASS,
                        $class_name
                    );
            }

            // Check for a method map
            if ($method_name
                && $context->hasNamespaceMapFor(T_FUNCTION, $method_name)
            ) {
                $namespace =
                    (string)$context->getNamespaceMapFor(T_FUNCTION, $method_name);
            }
        }

        // Clean it on up
        $namespace = self::cleanNamespace($namespace);

        return new FQSEN(
            $namespace ?: '\\',
            $class_name ?: '',
            $method_name ?: '',
            $closure_name ?: ''
        );
    }

    /**
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class::method' or
     * 'Class' or 'Class::method'.
     *
     * @return FQSEN
     */
    public static function fromFullyQualifiedString(string $fqsen_string) : FQSEN {
        return self::fromContextAndString(
            new Context(new CodeBase([], [], [], [])),
            $fqsen_string
        );
    }

    /**
     * @return FQSEN[]
     * An infinite list of alternative FQSENs
     */
    public function alternateFQSENInfiniteList() : \Iterator {
        $i = 0;
        while(true) {
            yield (new FQSEN(
                $this->namespace,
                $this->class_name,
                $this->method_name
            ))->withAlternateId(++$i);
        }
    }

    /**
     * @return FQSEN
     * A clone of this FQSEN with the given namespace
     */
    public function withNamespace(string $namespace) : FQSEN {
        $fqsen = clone($this);

        $fqsen->namespace =
            self::cleanNamespace($namespace);

        return $fqsen;
    }

    /**
     * @return string
     * The namespace associated with this FQSEN
     * or null if not defined
     */
    public function getNamespace() : string {
        return $this->namespace;
    }

    /**
     * @return FQSEN
     * A clone of this FQSEN with the given class name
     */
    public function withClassName(
        Context $context,
        string $class_name
    ) : FQSEN {
        $fqsen = clone($this)
            ->withMethodName($context, '')
            ->withClosureName($context, '');

        // Check to see if this is a qualified class name
        if(0 === strpos($class_name, '\\')) {
            $fq_class_name_elements =
                array_filter(explode('\\', $class_name));

            $class_name =
                array_pop($fq_class_name_elements);

            $namespace =
                '\\' . implode('\\', $fq_class_name_elements);

            $fqsen = $fqsen->withNamespace($namespace);

        // If its not fully qualified already, see if we have
        // a mapped NS for it.
        } else if ($context->hasNamespaceMapFor(T_CLASS, $class_name)) {
            $fqsen = $context->getNamespaceMapFor(T_CLASS, $class_name);
        }

        // Set the class name
        $fqsen->class_name = $class_name;

        return $fqsen;
    }

    /**
     * @return string
     * The class associated with this FQSEN or
     * null if not defined
     */
    public function getClassName() : string {
        return $this->class_name;
    }

    /**
     * @return FQSEN
     * A clone of this FQSEN with the given method name
     */
    public function withFunctionName(
        Context $context,
        string $method_name
    ) : FQSEN {
        $fqsen = clone($this);

        // If its not fully qualified already, see if we have
        // a mapped NS for it.
        if(0 !== strpos($method_name, '\\')) {
            if ($context->hasNamespaceMapFor(T_FUNCTION, $method_name)) {
                return
                    $context->getNamespaceMapFor(
                        T_FUNCTION,
                        $method_name
                    );
            }
        }

        // Check again to see if its fully qualified, and if so
        // extract the namespace
        if(0 == strpos($method_name, '\\')) {
            $fq_method_name_elements =
                array_filter(explode('\\', $method_name));

            $method_name =
                array_pop($fq_method_name_elements);

            $namespace =
                '\\' . implode('\\', $fq_method_name_elements);

            $fqsen = $fqsen->withNamespace($namespace);
        }

        $fqsen->method_name = $method_name;

        return $fqsen;
    }

    /**
     * @return FQSEN
     * A clone of this FQSEN with the given method name
     */
    public function withMethodName(
        Context $context,
        string $method_name
    ) : FQSEN {
        $fqsen = clone($this)
            ->withClosureName($context, '');
        $fqsen->method_name = $method_name;
        return $fqsen;
    }

    /**
     * @return string
     * The method name associated with this
     * FQSEN or null if not defined.
     */
    public function getMethodName() : string {
        return $this->method_name;
    }

    /**
     * @return FQSEN
     * A clone of this FQSEN with the given closure
     */
    public function withClosureName(
        Context $context,
        string $closure_name
    ) : FQSEN {
        $fqsen = clone($this);
        $fqsen->closure_name = $closure_name;
        return $fqsen;
    }

    /**
     * @return string
     * The closure name associated with this FQSEN
     * or null if not defined.
     */
    public function getClosureName() : string {
        return $this->closure_name;
    }

    /**
     * @return FQSEN
     * A new FQSEN with the given alternate_id set
     */
    public function withAlternateId(int $alternate_id) : FQSEN {
        $fqsen = clone($this);
        $fqsen->alternate_id = $alternate_id;
        return $fqsen;
    }

    /**
     * @return int
     * An alternate identifier associated with this
     * FQSEN or zero if none if this is not an
     * alternative.
     */
    public function getAlternateId() : int {
        return $this->alternate_id;
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public function __toString() : string {
        // Append the namespace
        $fqsen_string = $this->namespace;

        // If we have a class name, append it
        if ($this->class_name) {
            if ($fqsen_string && $fqsen_string !== '\\') {
                $fqsen_string .= '\\';
            }

            $fqsen_string .= strtolower($this->class_name);
        }

        // If there's a method, append it
        if ($this->method_name || $this->closure_name) {
            $fqsen_string .= '::';

            if ($this->method_name) {
                $fqsen_string .= $this->method_name;
            }

            if ($this->closure_name) {
                $fqsen_string .= '{' . $this->closure_name . '}';
            }
        }

        // Append an alternate ID if we need to disambiguate
        // multiple definitions
        if ($this->alternate_id) {
            $fqsen_string .= ' ' . $this->alternate_id;
        }

        assert(!empty($fqsen_string),
            "FQSENs should be non-empty" );

        return $fqsen_string;
    }

    /**
     * @return UnionType
     * A string representing this fully-qualified structural
     * element name.
     */
    // TODO: Make this 'UnionType'
    public function asUnionType() : UnionType {
        return new UnionType([$this->__toString()]);
    }

    /**
     * @return bool
     * True if this FQSEN is an alternate FQSEN
     */
    public function isAlternate() : bool {
        return (bool)$this->alternate_id;
    }

    /**
     * @return FQSEN
     * Get the canonical (non-alternate) FQSEN for
     * this FQSEN or this FQSEN if it is not an
     * alternate.
     */
    public function getCanonicalFQSEN() : FQSEN {
        if ($this->isAlternate()) {
            return clone($this)->withAlternateId(0);
        }

        return $this;
    }

    /**
     * @param string|null $namespace
     *
     * @return string
     * A cleaned version of the given namespace such that
     * its always prefixed with a '\' and never ends in a
     * '\', and is the string "\" if there is no namespace.
     */
    private static function cleanNamespace(string $namespace) : string {
        if (!$namespace
            || empty($namespace)
            || $namespace === '\\'
        ) {
            return '\\';
        }

        // Ensure that the first character of the namespace
        // is always a '\'
        if (0 !== strpos($namespace, '\\')) {
            $namespace = '\\' . $namespace;
        }

        // Ensure that we don't have a trailing '\' on the
        // namespace
        if ('\\' === substr($namespace, -1)) {
            $namespace = substr($namespace, 0, -1);
        }

        return $namespace;
    }
}
