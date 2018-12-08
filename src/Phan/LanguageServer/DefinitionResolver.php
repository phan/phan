<?php declare(strict_types=1);

namespace Phan\LanguageServer;

use AssertionError;
use ast;
use ast\Node;
use Closure;
use Phan\Analysis\ScopeVisitor;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * This implements closures for finding definitions for nodes where isSelected is set
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class DefinitionResolver
{
    /**
     * @return Closure(Context,Node):void
     * NOTE: The helper methods distinguish between "Go to definition"
     * and "go to type definition" in their implementations,
     * based on $request->getIsTypeDefinitionRequest()
     */
    public static function createGoToDefinitionClosure(GoToDefinitionRequest $request, CodeBase $code_base)
    {
        return function (Context $context, Node $node) use ($request, $code_base) {
            // @phan-suppress-next-line PhanUndeclaredProperty this is overridden
            $selected_fragment = $node->selectedFragment ?? null;
            if (is_string($selected_fragment)) {
                self::locateCommentDefinition($request, $code_base, $context, $selected_fragment);
                return;
            }
            // TODO: Better way to be absolutely sure this $node is in the same requested file path?
            // I think it's possible that we'll have more than one Node to check against (with simplify_ast)


            // $location = new Location($go_to_definition_request->getUri(), $node->lineno);

            // Log as strings in case TolerantASTConverter generates the wrong type
            Logger::logInfo(sprintf("Saw a node of kind %s at line %s", (string)$node->kind, (string)$node->lineno));

            switch ($node->kind) {
                case ast\AST_NAME:
                    self::locateClassDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_STATIC_PROP:
                case ast\AST_PROP:
                    self::locatePropDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_STATIC_CALL:
                case ast\AST_METHOD_CALL:
                    self::locateMethodDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CALL:
                    self::locateFuncDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CLASS_CONST:
                    self::locateClassConstDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CONST:
                    self::locateGlobalConstDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_VAR:
                    // NOTE: Only implemented for "go to type definition" and "hover" right now.
                    // TODO: Add simple heuristics to check for assignments and references within the function/global scope?
                    self::locateVariableDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_USE:
                    self::locateNamespaceUseDefinition($request, $code_base, $node);
                    return;
            }
            // $go_to_definition_request->recordDefinitionLocation(...)
        };
    }

    private static function locateCommentDefinition(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        Context $context,
        string $selected_fragment
    ) {
        // fprintf(STDERR, "locateCommentDefinition called for %s\n", $selected_fragment);
        // TODO: Handle method references in doc comments, global functions, etc.
        try {
            $union_type = UnionType::fromStringInContext($selected_fragment, $context, Type::FROM_PHPDOC);
        } catch (\Exception $e) {
            fprintf(STDERR, "Unexpected error in " . __METHOD__ . ": " . $e->getMessage() . "\n");
            return;
        }
        self::locateClassDefinitionForUnionType($request, $code_base, $union_type);
    }

    /**
     * Record information about this definition, to send back to the language client after all possible definitions were found.
     *
     * @return void
     */
    public static function locateClassDefinition(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node
    ) {
        try {
            $union_type = UnionTypeVisitor::unionTypeFromClassNode($code_base, $context, $node);
        } catch (FQSENException $_) {
            // Hopefully warn elsewhere
            return;
        }
        self::locateClassDefinitionForUnionType($request, $code_base, $union_type);
    }

    private static function locateClassDefinitionForUnionType(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        UnionType $union_type
    ) {
        foreach ($union_type->getTypeSet() as $type) {
            if ($type->isNativeType()) {
                continue;
            }
            $class_fqsen = FullyQualifiedClassName::fromType($type);
            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                continue;
            }
            $class = $code_base->getClassByFQSEN($class_fqsen);
            // Note: Does the same thing (Return the class)
            // both for "Go To Definition" and "Go To Type Definition"
            $request->recordDefinitionElement($code_base, $class, false);
        }
    }

    /**
     * @return void
     */
    public static function locatePropDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        $is_static = $node->kind === ast\AST_STATIC_PROP;
        try {
            $property = (new ContextNode($code_base, $context, $node))->getProperty($is_static);
        } catch (NodeException $_) {
            return; // ignore
        } catch (IssueException $_) {
            return; // ignore
        } catch (CodeBaseException $_) {
            return; // ignore
        }
        $request->recordDefinitionElement($code_base, $property, true);
    }

    /**
     * @param Node $node a node of type AST_CLASS_CONST
     * @return void
     */
    public static function locateClassConstDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        $name = $node->children['const'];
        if (!is_string($name)) {
            return;
        }
        if (strtolower($name) === 'class') {
            self::locateClassDefinition($request, $code_base, $context, $node->children['class']);
            return;
        }
        try {
            $class_const = (new ContextNode($code_base, $context, $node))->getClassConst();
        } catch (NodeException $_) {
            return; // ignore
        } catch (IssueException $_) {
            return; // ignore
        } catch (CodeBaseException $_) {
            return; // ignore
        }
        // Class constants can't be objects, so there's no point in "Go To Type Definition" for now.
        // TODO: There's a rare case of callable strings or `const HANDLER = MyClass::class`.
        $request->recordDefinitionElement($code_base, $class_const, false);
    }

    /**
     * @return void
     */
    public static function locateGlobalConstDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        try {
            $global_const = (new ContextNode($code_base, $context, $node))->getConst();
        } catch (NodeException $_) {
            return; // ignore
        } catch (IssueException $_) {
            return; // ignore
        } catch (CodeBaseException $_) {
            return; // ignore
        }
        $request->recordDefinitionElement($code_base, $global_const, false);
    }

    /**
     * @return void
     */
    public static function locateVariableDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        $name = $node->children['name'];
        if (!is_string($name)) {
            return;
        }
        if (!$context->getScope()->hasVariableWithName($name)) {
            return;
        }
        if (!$request->getIsTypeDefinitionRequest() && !$request->getIsHoverRequest()) {
            // TODO: Implement "Go To Definition" for variables with heuristics or create a new plugin
            return;
        }
        $variable = $context->getScope()->getVariableByName($name);

        $request->recordDefinitionOfVariableType($code_base, $context, $variable);
    }

    /**
     * @return void
     */
    public static function locateMethodDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        $is_static = $node->kind === ast\AST_STATIC_CALL;
        $method_name = $node->children['method'];
        if (!is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode($code_base, $context, $node))->getMethod($method_name, $is_static);
        } catch (NodeException $_) {
            // ignore
            return;
        } catch (IssueException $_) {
            // ignore
            return;
        }
        $request->recordDefinitionElement($code_base, $method, true);
    }

    /**
     * @return void
     */
    public static function locateFuncDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        try {
            foreach ((new ContextNode($code_base, $context, $node->children['expr']))->getFunctionFromNode() as $function_interface) {
                $request->recordDefinitionElement($code_base, $function_interface, true);
            }
        } catch (NodeException $_) {
            // ignore
            return;
        } catch (IssueException $_) {
            // ignore
            return;
        }
    }

    /**
     * @param Node $node a node of type AST_USE to find the definition of
     * @return void
     */
    public static function locateNamespaceUseDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Node $node)
    {
        // TODO: Support GroupUse (See ScopeVisitor->visitGroupUse)
        $targets = ScopeVisitor::aliasTargetMapFromUseNode($node);
        if (count($targets) !== 1) {
            // TODO: Support group use
            return;
        }
        $use_elem = $node->children[0];
        foreach ($targets as $target_array) {
            $target_fqsen = $target_array[1];
            if ($target_fqsen instanceof FullyQualifiedClassName) {
                // This **could** be a namespace or a class name.
                // If we see the class for that name in the code base, treat that as the definition
                if ($code_base->hasClassWithFQSEN($target_fqsen)) {
                    $class = $code_base->getClassByFQSEN($target_fqsen);
                    $request->recordDefinitionElement($code_base, $class, false);
                }
            } elseif ($target_fqsen instanceof FullyQualifiedFunctionName) {
                if ($code_base->hasFunctionWithFQSEN($target_fqsen)) {
                    $func = $code_base->getFunctionByFQSEN($target_fqsen);
                    $request->recordDefinitionElement($code_base, $func, false);
                }
            } elseif ($target_fqsen instanceof FullyQualifiedGlobalConstantName) {
                if ($code_base->hasGlobalConstantWithFQSEN($target_fqsen)) {
                    $global_constant = $code_base->getGlobalConstantByFQSEN($target_fqsen);
                    $request->recordDefinitionElement($code_base, $global_constant, false);
                }
            }
        }
        if ($node->flags === \ast\flags\USE_NORMAL) {
            $name = $use_elem->children['name'];
            if (is_string($name)) {
                try {
                    $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\' . ltrim($name, '\\'));
                } catch (AssertionError $_) {
                    return;  // ignore, probably still typing the requested definition
                } catch (FQSENException $_) {
                    return;  // ignore, probably still typing the requested definition
                }
                if ($code_base->hasClassWithFQSEN($class_fqsen)) {
                    $class = $code_base->getClassByFQSEN($class_fqsen);
                    $request->recordDefinitionElement($code_base, $class, false);
                }
            }
            return;
        }
    }
}
