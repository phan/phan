<?php declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Element\Method;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeMethodCapability;

/**
 * This file checks if a method can be made static without causing any errors.
 *
 * It hooks into these events:
 *
 * - analyzeMethod
 *   Once all classes are parsed, this method will be called
 *   on every method in the code base
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV2
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class PossiblyStaticMethodPlugin extends PluginV2 implements
    AnalyzeMethodCapability
{
    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        if ($method->isStatic()) {
            // This is what we want.
            return;
        }
        if ($method->getIsMagic()) {
            // Magic methods can't be static.
            return;
        }
        if ($method->getFQSEN() !== $method->getRealDefiningFQSEN()) {
            // Only warn once for the original definition of this method.
            // Don't warn about subclasses inheriting this method.
            return;
        }
        if ($method->getIsOverride()) {
            // This method can't be static unless its parent is also static.
            return;
        }
        if ($method->getIsOverriddenByAnother()) {
            // Changing this method causes a fatal error.
            return;
        }
        $method_filter = Config::getValue('plugin_config')['possibly_static_method_ignore_regex'] ?? null;
        if (is_string($method_filter)) {
            $fqsen_string = ltrim((string)$method->getFQSEN(), '\\');
            if (preg_match($method_filter, $fqsen_string) > 0) {
                return;
            }
        }

        $stmts_list = $this->getStatementListToAnalyze($method);
        if ($stmts_list === null) {
            // check for abstract methods, etc.
            return;
        }
        if ($this->nodeCanBeStatic($stmts_list)) {
            $visibility_upper = ucfirst($method->getVisibilityName());
            self::emitIssue(
                $code_base,
                $method->getContext(),
                "PhanPluginPossiblyStatic${visibility_upper}Method",
                "$visibility_upper method {METHOD} can be static",
                [$method->getFQSEN()]
            );
        }
    }

    /**
     * @param Method $method
     * @return ?Node - returns null if there's no statement list to analyze
     */
    private function getStatementListToAnalyze(Method $method)
    {
        if (!$method->hasNode()) {
            return null;
        }
        $node = $method->getNode();
        if (!$node) {
            return null;
        }
        return $node->children['stmts'];
    }

    /**
     * @param Node|int|string|float|null $node
     * @return bool - returns true if the node allows its method to be static
     */
    private function nodeCanBeStatic($node)
    {
        if (!($node instanceof Node)) {
            if (is_array($node)) {
                foreach ($node as $child_node) {
                    if (!$this->nodeCanBeStatic($child_node)) {
                        return false;
                    }
                }
            }
            return true;
        }
        switch ($node->kind) {
            case ast\AST_VAR:
                return $node->children['name'] !== 'this';
            case ast\AST_CLASS:
            case ast\AST_FUNC_DECL:
                return true;
            case ast\AST_CLOSURE:
                if ($node->flags & \ast\flags\MODIFIER_STATIC) {
                    return true;
                }
                // fall through
            default:
                foreach ($node->children as $child_node) {
                    if (!$this->nodeCanBeStatic($child_node)) {
                        return false;
                    }
                }
                return true;
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PossiblyStaticMethodPlugin();
