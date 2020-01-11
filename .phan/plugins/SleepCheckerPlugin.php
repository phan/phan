<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\Config;
use Phan\Language\Type\StringType;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks uses of __sleep()
 *
 * It assumes that the body of the __sleep() implementation is simple,
 * and just returns array literals directly.
 * This plugin does not analyze building up arrays, array_merge(), variables, etc.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 */
class SleepCheckerPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return SleepCheckerVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class SleepCheckerVisitor extends PluginAwarePostAnalysisVisitor
{

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitMethod(Node $node): void
    {
        if (strcasecmp('__sleep', (string)$node->children['name']) !== 0) {
            return;
        }
        $sleep_properties = [];
        $this->analyzeStatementsOfSleep($node, $sleep_properties);
        $this->warnAboutTransientSleepProperties($sleep_properties);
    }

    /**
     * Warn about instance properties that aren't mentioned in __sleep()
     * and don't have (at)transient or (at)phan-transient
     *
     * @param array<string,true> $sleep_properties
     */
    private function warnAboutTransientSleepProperties(array $sleep_properties): void
    {
        if (count($sleep_properties) === 0) {
            // Give up, failed to extract property names
            return;
        }
        $class = $this->context->getClassInScope($this->code_base);
        $class_fqsen = $class->getFQSEN();
        foreach ($class->getPropertyMap($this->code_base) as $property_name => $property) {
            if ($property->isStatic()) {
                continue;
            }
            if ($property->isFromPHPDoc()) {
                continue;
            }
            if ($property->isDynamicProperty()) {
                continue;
            }
            if (isset($sleep_properties[$property_name])) {
                continue;
            }
            if ($property->getRealDefiningFQSEN()->getFullyQualifiedClassName() !== $class_fqsen) {
                continue;
            }
            $doc_comment = $property->getDocComment() ?? '';
            $has_transient = preg_match('/@(phan-)?transient\b/', $doc_comment) > 0;
            if (!$has_transient) {
                $regex = Config::getValue('plugin_config')['sleep_transient_warning_blacklist_regex'] ?? null;
                if (is_string($regex) && preg_match($regex, $property_name)) {
                    continue;
                }
                $this->emitPluginIssue(
                    $this->code_base,
                    $property->getContext(),
                    'SleepCheckerPropertyMissingTransient',
                    'Property {PROPERTY} that is not serialized by __sleep should be annotated with @transient or @phan-transient',
                    [$property->__toString()]
                );
            }
        }
    }

    /**
     * @param Node|int|string|float|null $node
     * @param array<string,true> $sleep_properties
     */
    private function analyzeStatementsOfSleep($node, array &$sleep_properties = []): void
    {
        if (!($node instanceof Node)) {
            if (is_array($node)) {
                foreach ($node as $child_node) {
                    $this->analyzeStatementsOfSleep($child_node, $sleep_properties);
                }
            }
            return;
        }
        switch ($node->kind) {
            case ast\AST_RETURN:
                $this->analyzeReturnValue($node->children['expr'], $node->lineno, $sleep_properties);
                return;
            case ast\AST_CLASS:
            case ast\AST_CLOSURE:
            case ast\AST_FUNC_DECL:
                return;
            default:
                foreach ($node->children as $child_node) {
                    $this->analyzeStatementsOfSleep($child_node, $sleep_properties);
                }
        }
    }

    private const RESOLVE_SETTINGS =
        ContextNode::RESOLVE_ARRAYS |
        ContextNode::RESOLVE_ARRAY_VALUES |
        ContextNode::RESOLVE_CONSTANTS;

    /**
     * @param Node|string|int|float|null $expr_node
     * @param int $lineno
     * @param array<string,true> $sleep_properties
     */
    private function analyzeReturnValue($expr_node, int $lineno, array &$sleep_properties): void
    {
        $context = clone($this->context)->withLineNumberStart($lineno);
        if (!($expr_node instanceof Node)) {
            $this->emitPluginIssue(
                $this->code_base,
                $context,
                'SleepCheckerInvalidReturnStatement',
                '__sleep must return an array of strings. This is definitely not an array.'
            );
            return;
        }
        $code_base = $this->code_base;

        $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr_node);
        if (!$union_type->hasArray()) {
            $this->emitPluginIssue(
                $this->code_base,
                $context,
                'SleepCheckerInvalidReturnType',
                '__sleep is returning {TYPE}, expected {TYPE}',
                [(string)$union_type, 'string[]']
            );
            return;
        }
        if (!$context->isInClassScope()) {
            return;
        }

        $kind = $expr_node->kind;
        if (!\in_array($kind, [ast\AST_CONST, ast\AST_ARRAY, ast\AST_CLASS_CONST], true)) {
            return;
        }

        $value = (new ContextNode($code_base, $context, $expr_node))->getEquivalentPHPValue(self::RESOLVE_SETTINGS);
        if (!is_array($value)) {
            return;
        }
        $class = $context->getClassInScope($code_base);

        foreach ($value as $prop_name) {
            if (!is_string($prop_name)) {
                $prop_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $prop_name);
                if (!$prop_type->isType(StringType::instance(false))) {
                    $this->emitPluginIssue(
                        $this->code_base,
                        $context,
                        'SleepCheckerInvalidPropNameType',
                        '__sleep is returning an array with a value of type {TYPE}, expected {TYPE}',
                        [(string)$prop_type, 'string']
                    );
                }
                continue;
            }
            $sleep_properties[$prop_name] = true;

            if (!$class->hasPropertyWithName($code_base, $prop_name)) {
                $this->emitPluginIssue(
                    $this->code_base,
                    $context,
                    'SleepCheckerInvalidPropName',
                    '__sleep is returning an array that includes {PROPERTY}, which cannot be found',
                    [$prop_name]
                );
                continue;
            }
            $prop = $class->getPropertyByName($code_base, $prop_name);
            if ($prop->isFromPHPDoc()) {
                $this->emitPluginIssue(
                    $this->code_base,
                    $context,
                    'SleepCheckerMagicPropName',
                    '__sleep is returning an array that includes {PROPERTY}, which is a magic property',
                    [$prop_name]
                );
                continue;
            }
            if ($prop->isDynamicProperty()) {
                $this->emitPluginIssue(
                    $this->code_base,
                    $context,
                    'SleepCheckerDynamicPropName',
                    '__sleep is returning an array that includes {PROPERTY}, which is a dynamically added property (but not a declared property)',
                    [$prop_name]
                );
                continue;
            }
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new SleepCheckerPlugin();
