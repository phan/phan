<?php declare(strict_types=1);

namespace Phan\LanguageServer;

use ast;
use ast\Node;
use Closure;
use Phan\AST\ContextNode;
use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * This implements closures for finding completions for valid/invalid nodes where isSelected is set
 * @phan-file-suppress PhanUnusedPublicMethodParameter
 */
class CompletionResolver
{
    /**
     * @return Closure(Context,Node):void
     * NOTE: The helper methods distinguish between "Go to definition"
     * and "go to type definition" in their implementations,
     * based on $request->getIsTypeDefinitionRequest()
     */
    public static function createCompletionClosure(CompletionRequest $request, CodeBase $code_base)
    {
        return function (Context $context, Node $node) use ($request, $code_base) {
            // @phan-suppress-next-line PhanUndeclaredProperty this is overridden
            $selected_fragment = $node->selectedFragment ?? null;
            if (is_string($selected_fragment)) {
                // We don't support completions in code comments
                return;
            }
            // TODO: Better way to be absolutely sure this $node is in the same requested file path?
            // I think it's possible that we'll have more than one Node to check against (with simplify_ast)


            // $location = new Location($go_to_definition_request->getUri(), $node->lineno);

            // Log as strings in case TolerantASTConverter generates the wrong type
            Logger::logInfo(sprintf("Saw a node of kind %s at line %s", (string)$node->kind, (string)$node->lineno));

            switch ($node->kind) {
                case ast\AST_STATIC_PROP:
                case ast\AST_PROP:
                    self::locatePropertyCompletion(
                        $request,
                        $code_base,
                        $context,
                        $node,
                        $node->kind === ast\AST_STATIC_PROP,
                        $node->children['prop']
                    );
                    return;
                case ast\AST_CLASS_CONST:
                    $const_name = $node->children['const'];
                    if ($const_name === TolerantASTConverter::INCOMPLETE_CLASS_CONST) {
                        $const_name = '';
                        self::locatePropertyCompletion($request, $code_base, $context, $node, true, '');
                    }
                    self::locateClassConstantCompletion($request, $code_base, $context, $node, $const_name);
                    self::locateMethodCompletion($request, $code_base, $context, $node, true, $const_name);
                    return;
                case ast\AST_CONST:
                    $const_name = $node->children['name']->children['name'] ?? null;
                    if (!is_string($const_name)) {
                        return;
                    }
                    self::locateGlobalConstantCompletion($request, $code_base, $context, $node, $const_name);
                    self::locateClassCompletion($request, $code_base, $context, $node, $const_name);
                    self::locateGlobalFunctionCompletion($request, $code_base, $context, $node, $const_name);
                    return;
            }
            // $go_to_definition_request->recordDefinitionLocation(...)
        };
    }

    /**
     * @param string|mixed $prop_name
     * @return void
     */
    public static function locatePropertyCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        bool $is_static,
        $prop_name
    ) {
        if (!is_string($prop_name)) {
            return;
        }

        // Find all of the classes on the left hand side
        // TODO: Filter by properties that match $node->children['prop']
        $expected_type_categories = $is_static ? ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME : ContextNode::CLASS_LIST_ACCEPT_OBJECT;
        $expected_issue = $is_static ? Issue::TypeExpectedObjectStaticPropAccess : Issue::TypeExpectedObjectPropAccess;

        $class_list_generator = (new ContextNode(
            $code_base,
            $context,
            $node->children['class'] ?? $node->children['expr']
        ))->getClassList(true, $expected_type_categories, $expected_issue);


        // And find all of the instance/static properties that can be used as completions
        foreach ($class_list_generator as $class) {
            foreach ($class->getPropertyMap($code_base) as $prop) {
                if ($prop->isStatic() !== $is_static) {
                    continue;
                }
                $request->recordCompletionElement($code_base, $prop, '$' . $prop_name);
            }
        }
    }

    /**
     * @param string|mixed $constant_name
     */
    private static function locateClassConstantCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        $constant_name
    ) {
        if (!is_string($constant_name)) {
            return;
        }

        // Find all of the classes on the left hand side
        // TODO: Filter by properties that match $node->children['prop']
        $class_list_generator = (new ContextNode(
            $code_base,
            $context,
            $node->children['class'] ?? $node->children['expr']
        ))->getClassList(
            true,
            ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME,
            Issue::UndeclaredClassConstant
        );

        // And find all of the instance/static properties that can be used as completions
        foreach ($class_list_generator as $class) {
            foreach ($class->getConstantMap($code_base) as $name => $constant) {
                // TODO: What about ::class?  (Exclude it for not static)
                // TODO: Check if visible, the same way as the suggestion utility would
                $request->recordCompletionElement($code_base, $constant, $name);
            }
        }
    }

    /**
     * @param string|mixed $method_name
     */
    private static function locateMethodCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        bool $is_static,
        $method_name
    ) {
        if (!is_string($method_name)) {
            return;
        }

        // Find all of the classes on the left hand side
        // TODO: Filter by properties that match $node->children['prop']
        $class_list_generator = (new ContextNode(
            $code_base,
            $context,
            $node->children['class'] ?? $node->children['expr']
        ))->getClassList(
            true,
            ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME,
            Issue::UndeclaredClassMethod
        );

        // And find all of the instance/static properties that can be used as completions
        foreach ($class_list_generator as $class) {
            foreach ($class->getMethodMap($code_base) as $name => $method) {
                if ($is_static && !$method->isStatic()) {
                    continue;
                }
                $request->recordCompletionElement($code_base, $method, $name);
            }
        }
    }

    /**
     * @param string $constant_name
     * @suppress PhanUnusedPrivateMethodParameter
     */
    private static function locateGlobalConstantCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        string $constant_name
    ) {
        // TODO: Limit this check to constants that are visible from the current namespace, with the shortest name from the alias map
        // TODO: Use the alias map
        $current_namespace = ltrim($context->getNamespace(), "\\");

        foreach ($code_base->getGlobalConstantMap() as $constant) {
            if (!$constant instanceof GlobalConstant) {
                // TODO: Make Map templatized, this is impossible
                continue;
            }
            $namespace = ltrim($constant->getFQSEN()->getNamespace(), "\\");
            if ($namespace !== '' && strcasecmp($namespace, $current_namespace) !== 0) {
                // Only allow accessing global constants in the same namespace or the global namespace
                continue;
            }
            $fqsen_string = (string)$constant->getFQSEN();
            if (stripos($fqsen_string, $constant_name) === false) {
                continue;
            }
            $request->recordCompletionElement($code_base, $constant, $fqsen_string);
        }
    }

    /**
     * @suppress PhanUnusedPrivateMethodParameter
     * @suppress PhanUnusedPrivateMethodParameter TODO: Use $node and check if fully qualified
     */
    private static function locateClassCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        string $uncompleted_class_name
    ) {
        // TODO: Use the alias map
        // TODO: Remove the namespace
        // fwrite(STDERR, "Looking up classes in " . $context->getNamespace() . "\n");
        // Only check class names in the same namespace
        $class_names_in_namespace = $code_base->getClassNamesOfNamespace($context->getNamespace());

        foreach ($class_names_in_namespace as $class_name) {
            $class_name = ltrim($class_name, "\\");
            // fwrite(STDERR, "Checking $class_name\n");
            if (stripos($class_name, $uncompleted_class_name) === false) {
                continue;
            }
            $constant_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_name);
            $request->recordCompletionElement(
                $code_base,
                $code_base->getClassByFQSEN($constant_fqsen),
                $class_name
            );
        }
    }

    private static function getNamespaceFromFQSENString(string $fqsen_name) : string
    {
        $fqsen_name = ltrim($fqsen_name, "\\");
        $last_backslash = strrpos($fqsen_name, "\\");
        if ($last_backslash !== false) {
            return (string)substr($fqsen_name, $last_backslash);
        }
        return $fqsen_name;
    }

    /**
     * @suppress PhanUnusedPrivateMethodParameter TODO: Use $node and check if fully qualified
     */
    private static function locateGlobalFunctionCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        string $uncompleted_function_name
    ) {
        // TODO: Include FQSENs which have a namespace matching what was typed so far
        $current_namespace = ltrim($context->getNamespace(), "\\");

        // TODO: Use the alias map
        // TODO: Remove the namespace
        foreach ($code_base->getFunctionMap() as $func) {
            if (!$func instanceof Func) {
                // TODO: Make Map templatized, this is impossible
                continue;
            }
            $fqsen = $func->getFQSEN();
            $namespace = ltrim($fqsen->getNamespace(), "\\");
            if ($namespace !== '' && strcasecmp($namespace, $current_namespace) !== 0) {
                // Only allow accessing global functions in the same namespace or the global namespace
                continue;
            }
            $function_name = $fqsen->getName();
            if (stripos($function_name, $uncompleted_function_name) === false) {
                continue;
            }
            $request->recordCompletionElement(
                $code_base,
                $func,
                $function_name
            );
        }
    }
}
