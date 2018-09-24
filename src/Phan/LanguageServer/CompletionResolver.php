<?php declare(strict_types=1);

namespace Phan\LanguageServer;

use ast;
use ast\Node;
use Closure;
use Phan\AST\ContextNode;
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
    public static function createGoToDefinitionClosure(CompletionRequest $request, CodeBase $code_base)
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
                    self::locatePropCompletion($request, $code_base, $context, $node);
                    return;
            }
            // $go_to_definition_request->recordDefinitionLocation(...)
        };
    }

    /**
     * @return void
     */
    public static function locatePropCompletion(CompletionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {

        // Find all of the classes on the left hand side
        // TODO: Filter by properties that match $node->children['prop']
        $is_static = $node->kind === ast\AST_STATIC_PROP;
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
                $request->recordCompletionElement($code_base, $prop);
            }
        }
    }
}
