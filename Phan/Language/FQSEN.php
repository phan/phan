<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\CodeBase;

/**
 * A Fully-Qualified Structural Element Name
 */
class FQSEN {
    use \Phan\Memoize;

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
     * @var string
     * A closure name if one is in scope or the empty
     * string otherwise.
     */
    private $closure_name = '';

    /**
     * @var string
     * The name of a constant
     */
    private $constant_name = '';

    /**
     * @var string
     * The name of a property
     */
    private $property_name = '';

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
     *
     * @param string $constant_name
     * The name of a constant
     *
     * @param string $property_name
     * The name of a property
     */
    public function __construct(
        string $namespace = '\\',
        string $class_name = '',
        string $method_name = '',
        string $closure_name = '',
        string $constant_name = '',
        string $property_name = ''
    ) {
        $this->namespace = self::cleanNamespace($namespace);
        $this->class_name = $class_name;
        $this->method_name = $method_name;
        $this->closure_name = $closure_name;
        $this->constant_name = $constant_name;
        $this->property_name = $property_name;
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

        $parts =
            explode('\'', $fqsen_string);

        $fqsen_string = $parts[0];
        $alternate_id = $parts[1] ?? null;

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

        $fqsen = new FQSEN(
            $namespace ?: '\\',
            $class_name ?: '',
            $method_name ?: '',
            $closure_name ?: ''
        );

        if ($alternate_id) {
            return $fqsen->withAlternateId((int)$alternate_id);
        }

        return $fqsen;
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
            new Context,
            $fqsen_string
        );
    }

    /**
     * @param Type $type
     * A type to get an FQSEN for
     *
     * @return FQSEN
     * An FQSEN representing the given type
     */
    public static function fromType(Type $type) : FQSEN {
        return FQSEN::fromFullyQualifiedString((string)$type);
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
     * @param Context $context
     * The context in which the function name appears
     *
     * @param string $method_name
     * The name of the function
     *
     * @param bool $is_function_declaration
     * This must be set to true if we're getting an FQSEN
     * for a function that is being declared and false if
     * we're getting an FQSEN for a function being called.
     *
     * @return FQSEN
     * A clone of this FQSEN with the given method name
     */
    public function withFunctionName(
        Context $context,
        string $method_name,
        bool $is_function_declaration = false
    ) : FQSEN {

        $fqsen = clone($this)
            ->withClassName($context, '');

        // Its fully qualified. Ship it.
        if(0 === strpos($method_name, '\\')) {
            $fq_method_name_elements =
                array_filter(explode('\\', $method_name));

            $method_name =
                array_pop($fq_method_name_elements);

            $namespace =
                '\\' . implode('\\', $fq_method_name_elements);

            return $fqsen
                ->withNamespace($namespace)
                ->withMethodName($method_name);
        }

        // See if we have a namespace map for it
        if ($context->hasNamespaceMapFor(T_FUNCTION, $method_name)) {
            return $context->getNamespaceMapFor(
                T_FUNCTION, $method_name
            );
        }

        $parts = explode('\\', $method_name);
        $method_name = array_pop($parts);
        $namespace = implode('\\', $parts);

        if ($namespace) {
            $fqsen = $fqsen->withNamespace(
                '\\' . $namespace
            );
        }

        // Otherwise, this is a top-level function
        $fqsen = $fqsen->withMethodName($context, $method_name);

        // If we're getting an FQSEN for a function that
        // is being declared, we inherit the namespace
        // of our context
        if ($is_function_declaration || $namespace) {
            return $fqsen;
        }

        // Otherwise, if we're calling a function which has
        // not been explicitly mapped, we have to assume
        // that its to the root namespace.
        return $fqsen->withNamespace('\\');
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
     * @return FQSEN
     * A clone of this FQSEN with the given constant
     */
    public function withConstantName(string $constant_name) : FQSEN {
        $fqsen = clone($this);
        $fqsen->constant_name = $constant_name;
        return $fqsen;
    }

    /**
     * @return string
     * The constant name associated with this FQSEN
     */
    public function getConstantName() : string {
        return $this->constant_name;
    }

    /**
     * @return FQSEN
     * A clone of this FQSEN with the given property
     */
    public function withPropertyName(string $property_name) : FQSEN {
        $fqsen = clone($this);
        $fqsen->property_name = $property_name;
        return $fqsen;
    }

    /**
     * @return string
     * The property name associated with this FQSEN
     */
    public function getPropertyName() : string {
        return $this->property_name;
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
     * @return UnionType
     * A string representing this fully-qualified structural
     * element name.
     */
    public function asUnionType() : UnionType {
        return new UnionType([(string)$this]);
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

        if ($this->constant_name) {
            $fqsen_string .= '::' . $this->constant_name;

        } else if ($this->property_name) {
            $fqsen_string .= '->' . $this->property_name;
        }

        // Append an alternate ID if we need to disambiguate
        // multiple definitions
        if ($this->alternate_id) {
            $fqsen_string .= ',' . $this->alternate_id;
        }

        assert(!empty($fqsen_string),
            "FQSENs should be non-empty" );

        return $fqsen_string;
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
