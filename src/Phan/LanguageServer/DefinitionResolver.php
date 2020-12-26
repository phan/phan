<?php

declare(strict_types=1);

namespace Phan\LanguageServer;

use AssertionError;
use ast;
use ast\Node;
use Closure;
use Exception;
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

use function count;
use function is_string;

/**
 * This implements closures for finding definitions for nodes where isSelected is set
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 * @phan-file-suppress PhanPluginNoCommentOnPublicMethod TODO: Add comments
 */
class DefinitionResolver
{
    /**
     * @return Closure(Context,Node,list<Node>):void
     * NOTE: The helper methods distinguish between "Go to definition"
     * and "go to type definition" in their implementations,
     * based on $request->isTypeDefinitionRequest()
     */
    public static function createGoToDefinitionClosure(GoToDefinitionRequest $request, CodeBase $code_base): Closure
    {
        /**
         * @param list<Node> $parent_node_list
         */
        return static function (Context $context, Node $node, array $parent_node_list = []) use ($request, $code_base): void {
            // @phan-suppress-next-line PhanUndeclaredProperty this is overridden
            $selected_fragment = $node->selectedFragment ?? null;
            if (is_string($selected_fragment)) {
                self::locateCommentDefinition($request, $code_base, $context, $selected_fragment);
                return;
            }

            $parent_node = \end($parent_node_list);
            if ($parent_node instanceof Node) {
                if ($node->kind === ast\AST_NAME && $parent_node->kind === ast\AST_NEW) {
                    $node = $parent_node;
                }
            }
            // TODO: Better way to be absolutely sure this $node is in the same requested file path?
            // I think it's possible that we'll have more than one Node to check against (if the config overrides simplify_ast)


            // $location = new Location($go_to_definition_request->getUri(), $node->lineno);

            // Log as strings in case TolerantASTConverter generates the wrong type
            Logger::logInfo(\sprintf("Saw a node of kind %s at line %s", (string)$node->kind, (string)$node->lineno));

            switch ($node->kind) {
                case ast\AST_NAME:
                    self::locateClassDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_STATIC_PROP:
                case ast\AST_PROP:
                case ast\AST_NULLSAFE_PROP:
                    self::locatePropDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_STATIC_CALL:
                case ast\AST_METHOD_CALL:
                case ast\AST_NULLSAFE_METHOD_CALL:
                    self::locateMethodDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_NEW:
                    self::locateNewDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CALL:
                    self::locateFuncDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CLASS_CONST:
                    self::locateClassConstDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CLASS_NAME:
                    $class_node = $node->children['class'];
                    if ($class_node instanceof Node) {
                        // handle (2)::class from the polyfill
                        self::locateClassDefinition($request, $code_base, $context, $class_node);
                    }
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

    /**
     * Locate an element from a fragment seen in a comment or string
     *
     * This can currently refer to
     *
     * 1. a class
     * 2. a global function
     * 3. a global constant
     *
     * Other types (e.g. class constants) aren't supported yet.
     */
    private static function locateCommentDefinition(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        Context $context,
        string $selected_fragment
    ): void {
        // fprintf(STDERR, "locateCommentDefinition called for %s\n", $selected_fragment);
        if (self::locateClassDefinitionFromComment($request, $code_base, $context, $selected_fragment)) {
            return;
        }
        if (self::locateGlobalFunctionDefinitionFromComment($request, $code_base, $context, $selected_fragment)) {
            return;
        }
        if (self::locateGlobalConstantDefinitionFromComment($request, $code_base, $context, $selected_fragment)) {
            return;
        }
    }

    private static function locateClassDefinitionFromComment(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        Context $context,
        string $selected_fragment
    ): bool {
        // TODO: Handle method references in doc comments, global functions, etc.
        try {
            $union_type = UnionType::fromStringInContext($selected_fragment, $context, Type::FROM_PHPDOC);
        } catch (Exception $_) {
            // fprintf(STDERR, "Unexpected error in " . __METHOD__ . ": " . $_->getMessage() . "\n");
            return false;
        }
        if ($union_type->isEmpty()) {
            return false;
        }
        // This is the name of a class
        return self::locateClassDefinitionForUnionType($request, $code_base, $union_type);
    }

    private static function locateGlobalFunctionDefinitionFromComment(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        Context $context,
        string $selected_fragment
    ): bool {
        // TODO: Handle method references in doc comments, global functions, etc.
        try {
            $fqsen = FullyQualifiedFunctionName::make('', $selected_fragment);
        } catch (Exception $_) {
            return false;
        }
        // fwrite(STDERR, "Looking up function with fqsen $fqsen\n");
        if (!$code_base->hasFunctionWithFQSEN($fqsen)) {
            if (\substr($selected_fragment, 0, 1) !== '\\') {
                try {
                    $fqsen = FullyQualifiedFunctionName::make($context->getNamespace(), $selected_fragment);
                } catch (Exception $_) {
                    return false;
                }
            }
            if (!$code_base->hasFunctionWithFQSEN($fqsen)) {
                return false;
            }
        }
        $request->recordDefinitionElement($code_base, $code_base->getFunctionByFQSEN($fqsen), true);
        return true;
    }

    private static function locateGlobalConstantDefinitionFromComment(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        Context $context,
        string $selected_fragment
    ): bool {
        // TODO: Handle method references in doc comments, global functions, etc.
        try {
            $fqsen = FullyQualifiedGlobalConstantName::make('', $selected_fragment);
        } catch (Exception $_) {
            return false;
        }
        // fwrite(STDERR, "Looking up function with fqsen $fqsen\n");
        if (!$code_base->hasGlobalConstantWithFQSEN($fqsen)) {
            if (\substr($selected_fragment, 0, 1) !== '\\') {
                try {
                    $fqsen = FullyQualifiedGlobalConstantName::make($context->getNamespace(), $selected_fragment);
                } catch (Exception $_) {
                    return false;
                }
            }
            if (!$code_base->hasGlobalConstantWithFQSEN($fqsen)) {
                return false;
            }
        }
        $request->recordDefinitionElement($code_base, $code_base->getGlobalConstantByFQSEN($fqsen), true);
        return true;
    }

    /**
     * Record information about this definition, to send back to the language client after all possible definitions were found.
     */
    public static function locateClassDefinition(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node
    ): void {
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
    ): bool {
        $found = false;
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
            $found = true;
        }
        return $found;
    }

    public static function locatePropDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node): void
    {
        $is_static = $node->kind === ast\AST_STATIC_PROP;
        try {
            $property = (new ContextNode($code_base, $context, $node))->getProperty($is_static);
        } catch (NodeException | IssueException | CodeBaseException $_) {
            return; // ignore
        }
        $request->recordDefinitionElement($code_base, $property, true);
    }

    /**
     * @param Node $node a node of type AST_CLASS_CONST
     */
    public static function locateClassConstDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node): void
    {
        $name = $node->children['const'];
        if (!is_string($name)) {
            return;
        }
        if (\strtolower($name) === 'class') {
            self::locateClassDefinition($request, $code_base, $context, $node->children['class']);
            return;
        }
        try {
            $class_const = (new ContextNode($code_base, $context, $node))->getClassConst();
        } catch (NodeException | IssueException | CodeBaseException $_) {
            return; // ignore
        }
        // Class constants can't be objects, so there's no point in "Go To Type Definition" for now.
        // TODO: There's a rare case of callable strings or `const HANDLER = MyClass::class`.
        $request->recordDefinitionElement($code_base, $class_const, false);
    }

    public static function locateGlobalConstDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node): void
    {
        try {
            $global_const = (new ContextNode($code_base, $context, $node))->getConst();
        } catch (NodeException | IssueException | CodeBaseException $_) {
            return; // ignore
        }
        $request->recordDefinitionElement($code_base, $global_const, false);
    }

    public static function locateVariableDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node): void
    {
        $name = $node->children['name'];
        if (!is_string($name)) {
            return;
        }
        if (!$request->isTypeDefinitionRequest() && !$request->isHoverRequest()) {
            // TODO: Implement "Go To Definition" for variables with heuristics or create a new plugin
            return;
        }
        // Get the variable or superglobal
        try {
            $variable = (new ContextNode($code_base, $context, $node))->getVariable();
        } catch (Exception $_) {
            return;
        }

        $request->recordDefinitionOfVariableType($code_base, $context, $variable);
    }

    /**
     * Given a node of type AST_NEW, locate the constructor definition (or class definition)
     */
    private static function locateNewDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node): void
    {
        try {
            $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node);
            self::locateConstructorDefinitionForUnionType($request, $code_base, $union_type);
        } catch (Exception $_) {
            // Hopefully warn elsewhere
            return;
        }
    }

    private static function locateConstructorDefinitionForUnionType(
        GoToDefinitionRequest $request,
        CodeBase $code_base,
        UnionType $union_type
    ): void {
        foreach ($union_type->getTypeSet() as $type) {
            if ($type->isNativeType()) {
                continue;
            }
            $class_fqsen = FullyQualifiedClassName::fromType($type);
            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                continue;
            }
            $class = $code_base->getClassByFQSEN($class_fqsen);
            $method = $class->getMethodByName($code_base, '__construct');
            if ($method->isPHPInternal() && !$class->isPHPInternal() && !$request->isHoverRequest()) {
                $request->recordDefinitionElement($code_base, $class, false);
                continue;
            }
            // Note: Does the same thing (Return the class)
            // both for "Go To Definition" and "Go To Type Definition"
            $request->recordDefinitionElement($code_base, $method, false);
        }
    }

    /**
     * Locate a definition given a direct call to an instance or static method
     */
    public static function locateMethodDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node): void
    {
        $is_static = $node->kind === ast\AST_STATIC_CALL;
        $method_name = $node->children['method'];
        if (!is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode($code_base, $context, $node))->getMethod($method_name, $is_static, true);
        } catch (IssueException | NodeException $_) {
            // ignore
            return;
        }
        $request->recordDefinitionElement($code_base, $method, true);
    }

    public static function locateFuncDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node): void
    {
        try {
            foreach ((new ContextNode($code_base, $context, $node->children['expr']))->getFunctionFromNode() as $function_interface) {
                $request->recordDefinitionElement($code_base, $function_interface, true);
            }
        } catch (NodeException | IssueException $_) {
            // ignore
            return;
        }
    }

    /**
     * @param Node $node a node of type AST_USE to find the definition of
     */
    public static function locateNamespaceUseDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Node $node): void
    {
        // TODO: Support GroupUse (See ScopeVisitor->visitGroupUse)
        $targets = ScopeVisitor::aliasTargetMapFromUseNode($node);
        if (count($targets) !== 1) {
            // TODO: Support group use
            return;
        }
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
            $name = $node->children[0]->children['name'] ?? null;
            if (is_string($name)) {
                try {
                    $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\' . \ltrim($name, '\\'));
                } catch (AssertionError | FQSENException $_) {
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
