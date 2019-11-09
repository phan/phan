<?php declare(strict_types=1);

use ast\Node;
use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\ElementContext;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\FinalizeProcessCapability;

/**
 * This file checks if a method can be made static without causing any errors.
 *
 * It hooks into these events:
 *
 * - analyzeMethod
 *   Once all classes are parsed, this method will be called
 *   on every method in the code base
 *
 * - analyzeFunction
 *   Once all classes and functions are parsed, this method will be called
 *   on every function in the code base
 *
 * - finalizeProcess
 *   Once the analysis phase is complete, this method will be called
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class PossiblyStaticMethodPlugin extends PluginV3 implements
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    FinalizeProcessCapability
{

    /**
     * @var array<string,FunctionInterface> a list of functions and methods where checks were postponed
     */
    private $methods_for_postponed_analysis = [];

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param FunctionInterface $method
     * A function or method being analyzed
     */
    private static function analyzePostponedMethod(
        CodeBase $code_base,
        FunctionInterface $method
    ) : void {
        if ($method instanceof Method) {
            if ($method->isOverride()) {
                // This method can't be static unless its parent is also static.
                return;
            }
            if ($method->isOverriddenByAnother()) {
                // Changing this method causes a fatal error.
                return;
            }
        }

        $stmts_list = self::getStatementListToAnalyze($method);
        if ($stmts_list === null) {
            // check for abstract methods, etc.
            return;
        }
        if (self::nodeCanBeStatic($code_base, $method, $stmts_list)) {
            if ($method instanceof Method) {
                $visibility_upper = ucfirst($method->getVisibilityName());
                self::emitIssue(
                    $code_base,
                    $method->getContext(),
                    "PhanPluginPossiblyStatic${visibility_upper}Method",
                    "$visibility_upper method {METHOD} can be static",
                    [$method->getRepresentationForIssue()]
                );
            } else {
                self::emitIssue(
                    $code_base,
                    $method->getContext(),
                    "PhanPluginPossiblyStaticClosure",
                    "{FUNCTION} can be static",
                    [$method->getRepresentationForIssue()]
                );
            }
        }
    }

    /**
     * @param FunctionInterface $method
     * @return ?Node - returns null if there's no statement list to analyze
     */
    private static function getStatementListToAnalyze(FunctionInterface $method) : ?Node
    {
        $node = $method->getNode();
        if (!$node) {
            return null;
        }
        return $node->children['stmts'];
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Node|int|string|float|null $node
     * @return bool - returns true if the node allows its method to be static
     */
    private static function nodeCanBeStatic(CodeBase $code_base, FunctionInterface $method, $node) : bool
    {
        if (!($node instanceof Node)) {
            if (is_array($node)) {
                foreach ($node as $child_node) {
                    if (!self::nodeCanBeStatic($code_base, $method, $child_node)) {
                        return false;
                    }
                }
            }
            return true;
        }
        switch ($node->kind) {
            case ast\AST_VAR:
                if ($node->children['name'] === 'this') {
                    return false;
                }
                // Handle edge cases such as `${$this->varName}`
                break;
            case ast\AST_CLASS:
            case ast\AST_FUNC_DECL:
                return true;
            case ast\AST_STATIC_CALL:
                if (self::isSelfOrParentCallUsingObject($code_base, $method, $node)) {
                    return false;
                }
                // Check code such as `static::someMethod($this->prop)`
                break;
            case ast\AST_CLOSURE:
            case ast\AST_ARROW_FUNC:
                if ($node->flags & \ast\flags\MODIFIER_STATIC) {
                    return true;
                }
                break;
        }
        foreach ($node->children as $child_node) {
            if (!self::nodeCanBeStatic($code_base, $method, $child_node)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the calling instance method exists
     *
     * @param Node $node a node of kind ast\AST_STATIC_CALL
     *                   (e.g. SELF::someMethod(), parent::someMethod(), SomeClass::staticMethod())
     *
     * @return bool true if the AST_STATIC_CALL node is really calling an instance method
     */
    private static function isSelfOrParentCallUsingObject(CodeBase $code_base, FunctionInterface $method, Node $node) : bool
    {
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node && $class_node->kind === ast\AST_NAME)) {
            return false;
        }
        $class_name = $class_node->children['name'];
        if (!is_string($class_name)) {
            return false;
        }
        if (!in_array(strtolower($class_name), ['self', 'parent'], true)) {
            return false;
        }
        $method_name = $node->children['method'];
        if (!is_string($method_name)) {
            // This is uninferable
            return true;
        }
        if (!$method instanceof AddressableElement) {
            // should be impossible
            return true;
        }
        try {
            $method = (new ContextNode($code_base, new ElementContext($method), $node))->getMethod($method_name, true, false);
        } catch (Exception $_) {
            // This might be an instance method if we don't know what it is
            return true;
        }
        return !$method->isStatic();
    }

    /**
     * @param CodeBase $unused_code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     * @override
     */
    public function analyzeMethod(
        CodeBase $unused_code_base,
        Method $method
    ) : void {
        // 1. Perform any checks that can be done immediately to rule out being able
        //    to convert this to a static method
        if ($method->isStatic()) {
            // This is what we want.
            return;
        }
        if ($method->isMagic()) {
            // Magic methods can't be static.
            return;
        }
        if ($method->getFQSEN() !== $method->getRealDefiningFQSEN()) {
            // Only warn once for the original definition of this method.
            // Don't warn about subclasses inheriting this method.
            return;
        }
        $method_filter = Config::getValue('plugin_config')['possibly_static_method_ignore_regex'] ?? null;
        if (is_string($method_filter)) {
            $fqsen_string = ltrim((string)$method->getFQSEN(), '\\');
            if (preg_match($method_filter, $fqsen_string) > 0) {
                return;
            }
        }
        if (!$method->hasNode()) {
            // There's no body to check - This is abstract or can't be checked
            return;
        }
        $fqsen = $method->getFQSEN();

        // 2. Defer remaining checks until we have all the necessary information
        //    (is this method overridden/an override, is parent::foo() referring to a static or an instance method, etc.)
        $this->methods_for_postponed_analysis[(string) $fqsen] = $method;
    }

    /**
     * @param CodeBase $unused_code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     * @override
     */
    public function analyzeFunction(
        CodeBase $unused_code_base,
        Func $function
    ) : void {
        if (!$function->isClosure()) {
            return;
        }
        if ($function->isStatic()) {
            return;
        }
        if (!$function->hasNode()) {
            // There's no body to check - This is abstract or can't be checked
            return;
        }
        // NOTE: The possibly_static_method_ignore_regex isn't used because there's no way to apply it to closures
        $fqsen = $function->getFQSEN();

        // 2. Defer remaining checks until we have all the necessary information
        //    (is this method overridden/an override, is parent::foo() referring to a static or an instance method, etc.)
        $this->methods_for_postponed_analysis[(string) $fqsen] = $function;
    }

    /**
     * @param CodeBase $code_base
     * The code base being analyzed
     *
     * @override
     */
    public function finalizeProcess(CodeBase $code_base) : void
    {
        foreach ($this->methods_for_postponed_analysis as $method) {
            self::analyzePostponedMethod($code_base, $method);
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PossiblyStaticMethodPlugin();
