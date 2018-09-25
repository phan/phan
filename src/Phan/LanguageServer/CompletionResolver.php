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
}
