<?php

declare(strict_types=1);

namespace Phan\LanguageServer;

use ast;
use ast\Node;
use Closure;
use Phan\AST\ContextNode;
use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\LanguageServer\Protocol\CompletionItem;
use Phan\LanguageServer\Protocol\CompletionItemKind;

use function is_string;

/**
 * This implements closures for finding completions for valid/invalid nodes where isSelected is set
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class CompletionResolver
{
    /**
     * @return Closure(Context,Node, list<Node>):void
     * NOTE: The helper methods distinguish between "Go to definition"
     * and "go to type definition" in their implementations,
     * based on $request->isTypeDefinitionRequest()
     */
    public static function createCompletionClosure(CompletionRequest $request, CodeBase $code_base): Closure
    {
        /**
         * @param list<Node> $parent_node_list
         */
        return static function (Context $context, Node $node, array $parent_node_list) use ($request, $code_base): void {
            // @phan-suppress-next-line PhanUndeclaredProperty this is overridden
            $selected_fragment = $node->selectedFragment ?? null;
            if (is_string($selected_fragment)) {
                // We don't support completions in code comments
                return;
            }
            // TODO: Better way to be absolutely sure this $node is in the same requested file path?
            // I think it's possible that we'll have more than one Node to check against (if the config overrides simplify_ast)


            // $location = new Location($go_to_definition_request->getUri(), $node->lineno);

            // Log as strings in case TolerantASTConverter generates the wrong type
            Logger::logInfo(\sprintf("Saw a node of kind %s at line %s", (string)$node->kind, (string)$node->lineno));

            $kind = $node->kind;

            switch ($kind) {
                case ast\AST_STATIC_PROP:
                case ast\AST_PROP:
                    // fwrite(STDERR, \Phan\Debug::nodeToString($node));
                    $prop_name = $node->children['prop'];
                    if ($prop_name === TolerantASTConverter::INCOMPLETE_PROPERTY) {
                        $prop_name = '';
                    }
                    self::locatePropertyCompletion(
                        $request,
                        $code_base,
                        $context,
                        $node,
                        $kind === ast\AST_STATIC_PROP,
                        $prop_name
                    );
                    if ($kind === ast\AST_PROP) {
                        self::locateMethodCompletion(
                            $request,
                            $code_base,
                            $context,
                            $node,
                            false,
                            $prop_name
                        );
                    }
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
                    self::locateMiscellaneousTokenCompletion($request, $code_base, $context, $node, $const_name, $parent_node_list);
                    self::locateGlobalConstantCompletion($request, $code_base, $context, $node, $const_name);
                    self::locateClassCompletion($request, $code_base, $context, $node, $const_name);
                    self::locateGlobalFunctionCompletion($request, $code_base, $context, $node, $const_name);
                    return;
                case ast\AST_VAR:
                    $var_name = $node->children['name'];
                    if (!is_string($var_name)) {
                        return;
                    }
                    if ($var_name === TolerantASTConverter::INCOMPLETE_VARIABLE) {
                        $var_name = '';
                    }
                    self::locateVariableCompletion($request, $code_base, $context, $var_name);
                    return;
            }
            // $go_to_definition_request->recordDefinitionLocation(...)
        };
    }

    /**
     * @param string|mixed $incomplete_prop_name
     */
    public static function locatePropertyCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        bool $is_static,
        $incomplete_prop_name
    ): void {
        if (!is_string($incomplete_prop_name)) {
            return;
        }

        // Find all of the classes on the left-hand side
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
            // @phan-suppress-next-line PhanAccessMethodInternal
            $visible_properties = IssueFixSuggester::filterSimilarProperties($code_base, $context, $class->getPropertyMap($code_base), $is_static);

            foreach ($visible_properties as $prop) {
                // fprintf(STDERR, "Looking for %s in '%s'\n", $prop->getName(), $incomplete_prop_name);
                if ($incomplete_prop_name !== '' && \stripos($prop->getName(), $incomplete_prop_name) === false) {
                    continue;
                }
                // fprintf(STDERR, "Adding %s", $prop->getName());

                // A prefix for the prefix to remove from the completion element
                $prefixPrefix = $is_static ? '$' : '';
                $request->recordCompletionElement($code_base, $prop, $prefixPrefix . $incomplete_prop_name);
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
    ): void {
        if (!is_string($constant_name)) {
            return;
        }

        $class_node = $node->children['class'];
        if (!$class_node instanceof Node) {
            return;
        }
        $is_static = $class_node->kind === ast\AST_NAME;

        // Find all of the classes on the left-hand side
        // TODO: Filter by properties that match $node->children['prop']
        $class_list_generator = (new ContextNode(
            $code_base,
            $context,
            $class_node
        ))->getClassList(
            true,
            ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME
        );

        // And find all of the instance/static properties that can be used as completions
        foreach ($class_list_generator as $class) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $visible_constant_map = IssueFixSuggester::filterSimilarConstants(
                $code_base,
                $context,
                $class->getConstantMap($code_base)
            );
            foreach ($visible_constant_map as $name => $constant) {
                if (!$is_static && \strcasecmp($name, 'class') === 0) {
                    // Dynamic class names are not allowed in compile-time ::class fetch, it's a fatal error
                    continue;
                }
                // TODO: What about ::class?  (Exclude it for not static?)
                // TODO: Check if visible, the same way as the suggestion utility would
                $request->recordCompletionElement($code_base, $constant, $name);
            }
        }
    }

    /**
     * @param string|mixed $incomplete_method_name
     */
    private static function locateMethodCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        bool $is_static,
        $incomplete_method_name
    ): void {
        if (!is_string($incomplete_method_name)) {
            return;
        }

        // Find all of the classes on the left-hand side
        // TODO: Filter by properties that match $node->children['prop']
        $class_list_generator = (new ContextNode(
            $code_base,
            $context,
            $node->children['class'] ?? $node->children['expr']
        ))->getClassList(
            true,
            ContextNode::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME
        );

        // And find all of the instance/static properties that can be used as completions
        foreach ($class_list_generator as $class) {
            $methods = $class->getMethodMap($code_base);
            // @phan-suppress-next-line PhanAccessMethodInternal
            $filtered_methods = IssueFixSuggester::filterSimilarMethods($code_base, $context, $methods, $is_static);
            foreach ($filtered_methods as $method) {
                if ($incomplete_method_name !== '' && \stripos($method->getName(), $incomplete_method_name) === false) {
                    // Skip suggestions that don't have the original method as a substring
                    continue;
                }
                $request->recordCompletionElement($code_base, $method, $method->getName());
            }
        }
    }

    /**
     * Tokens that should be suggested when the parent node is a statement list.
     */
    private const COMPLETING_STATEMENT_TOKENS = [
        'try',
        'catch',
        'finally',
        'throw',
        'if',
        'elseif',
        'endif',
        'else',
        'while',
        'endwhile',
        'do',
        'for',
        'endfor',
        'foreach',
        'endforeach',
        'declare',
        'enddeclare',
        'switch',
        'endswitch',
        'break',
        'continue',
        'goto',
        'echo',
        'class',
        'interface',
        'trait',
        'use',  // Only support use statements for now, not closure use
        'static',
        'unset',
        'list',
        // '__halt_compiler',  // Too rarely used to suggest
    ];

    /**
     * Tokens that should be used as suggestions for places in which a generic name is suggested.
     */
    private const COMPLETING_TOKENS = [
        'exit',
        'die',
        'function',
        // 'yield', TODO check if in function
        // 'instanceof',
        // 'as',
        // 'case',
        // 'default',
        // 'extends',
        // 'implements',
        'print',
        'new',
        'clone',
        // 'var',
        'eval',
        'include',
        'include_once',
        'require',
        'require_once',
        'namespace',  // namespace-relative identifiers such as namespace\foo()
        // 'insteadof',
        'global',
        'isset',
        'empty',
        // 'abstract',
        // 'static',
        // 'final',
        // 'private',
        // 'protected',
        // 'public',
        'array',
        // 'callable',
        // 'OR',
        // 'AND',
        // 'XOR',
        '__CLASS__',
        '__TRAIT__',
        '__FUNCTION__',
        '__METHOD__',
        '__LINE__',
        '__FILE__',
        '__DIR__',
    ];

    /**
     * @param string $incomplete_constant_name
     * @param list<Node> $parent_node_list
     * @suppress PhanUnusedPrivateMethodParameter
     */
    private static function locateMiscellaneousTokenCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        string $incomplete_constant_name,
        array $parent_node_list
    ): void {
        if ($node->kind === ast\AST_CONST && ($node->children['name']->flags ?? null) !== ast\flags\NAME_NOT_FQ) {
            // Don't suggest completions for namespace\keyword or \keyword, it's generally not valid.
            return;
        }
        $parent_node = \end($parent_node_list);
        $token_candidates = self::COMPLETING_TOKENS;
        if (($parent_node->kind ?? null) === ast\AST_STMT_LIST) {
            \array_push($token_candidates, ...self::COMPLETING_STATEMENT_TOKENS);
        }
        // Suggest additional tokens that can be used in all supported php versions of this project.
        if (Config::get_closest_minimum_target_php_version_id() >= 80000) {
            $token_candidates[] = 'match';
        }
        if (Config::get_closest_minimum_target_php_version_id() >= 70400) {
            $token_candidates[] = 'fn';
        }
        \sort($token_candidates);
        foreach ($token_candidates as $token) {
            if (\stripos($token, $incomplete_constant_name) !== 0) {
                // Don't bother suggesting tokens containing the name that aren't prefixes.
                // It's less useful than it is for constants/functions.
                continue;
            }
            $item = new CompletionItem();
            $item->label = $token;
            $item->kind = CompletionItemKind::KEYWORD;
            $item->detail = null; // TODO: Better summary
            $item->documentation = null;
            $insert_text = null;
            if (!CompletionRequest::useVSCodeCompletion()) {
                if (\stripos($token, $incomplete_constant_name) === 0) {
                    $insert_text = (string)\substr($token, \strlen($incomplete_constant_name));
                    if (\preg_match('/[a-zA-Z]/', $incomplete_constant_name, $match)) {
                        $is_upper = $match[0] >= 'A' && $match[0] <= 'Z';
                        $insert_text = $is_upper ? \strtoupper($insert_text) : \strtolower($insert_text);
                    }
                }
            }
            $item->insertText = $insert_text;
            $request->recordCompletionItem($item);
        }
    }

    /**
     * @param string $incomplete_constant_name
     * @suppress PhanUnusedPrivateMethodParameter
     */
    private static function locateGlobalConstantCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        string $incomplete_constant_name
    ): void {
        // TODO: Limit this check to constants that are visible from the current namespace, with the shortest name from the alias map
        // TODO: Use the alias map
        $current_namespace = \ltrim($context->getNamespace(), "\\");

        foreach ($code_base->getGlobalConstantMap() as $constant) {
            if (!$constant instanceof GlobalConstant) {
                // TODO: Make Map templatized, this is impossible
                continue;
            }
            $namespace = \ltrim($constant->getFQSEN()->getNamespace(), "\\");
            if ($namespace !== '' && \strcasecmp($namespace, $current_namespace) !== 0) {
                // Only allow accessing global constants in the same namespace or the global namespace
                continue;
            }
            $fqsen_string = (string)$constant->getFQSEN();
            if ($incomplete_constant_name !== '' && \stripos($fqsen_string, $incomplete_constant_name) === false) {
                continue;
            }
            $request->recordCompletionElement($code_base, $constant, $fqsen_string);
        }
    }

    /**
     * @suppress PhanUnusedPrivateMethodParameter TODO: Use $node and check if fully qualified
     */
    private static function locateClassCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        string $incomplete_class_name
    ): void {
        // TODO: Use the alias map
        // TODO: Remove the namespace
        // fwrite(STDERR, "Looking up classes in " . $context->getNamespace() . "\n");
        // Only check class names in the same namespace
        $class_names_in_namespace = $code_base->getClassNamesOfNamespace($context->getNamespace());

        foreach ($class_names_in_namespace as $class_name) {
            $class_name = \ltrim($class_name, "\\");
            // fwrite(STDERR, "Checking $class_name\n");
            if (\stripos($class_name, $incomplete_class_name) === false) {
                continue;
            }
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall should be impossible if found in codebase
            $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_name);
            // Call hasClassWithFQSEN to trigger loading the class as a side effect
            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                continue;
            }
            $request->recordCompletionElement(
                $code_base,
                $code_base->getClassByFQSEN($class_fqsen),
                $class_name
            );
        }
    }

    /**
     * @suppress PhanUnusedPrivateMethodParameter TODO: Use $node and check if fully qualified
     */
    private static function locateGlobalFunctionCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        Node $node,
        string $incomplete_function_name
    ): void {
        // TODO: Include FQSENs which have a namespace matching what was typed so far
        $current_namespace = \ltrim($context->getNamespace(), "\\");

        // TODO: Use the alias map
        // TODO: Remove the namespace
        foreach ($code_base->getFunctionMap() as $func) {
            if (!$func instanceof Func) {
                // TODO: Make Map templatized, this is impossible
                continue;
            }
            $fqsen = $func->getFQSEN();
            $namespace = \ltrim($fqsen->getNamespace(), "\\");
            if ($namespace !== '' && \strcasecmp($namespace, $current_namespace) !== 0) {
                // Only allow accessing global functions in the same namespace or the global namespace
                continue;
            }
            $function_name = $fqsen->getName();
            if ($incomplete_function_name !== '' && \stripos($function_name, $incomplete_function_name) === false) {
                continue;
            }
            $request->recordCompletionElement(
                $code_base,
                $func,
                $function_name
            );
        }
    }

    private static function locateVariableCompletion(
        CompletionRequest $request,
        CodeBase $code_base,
        Context $context,
        string $incomplete_variable_name
    ): void {
        $variable_candidates = $context->getScope()->getVariableMap();
        $prefix = CompletionRequest::useVSCodeCompletion() ? '$' : '';
        // TODO: Use the alias map
        // TODO: Remove the namespace
        foreach ($variable_candidates as $suggested_variable_name => $variable) {
            $suggested_variable_name = (string)$suggested_variable_name;
            if ($incomplete_variable_name !== '' && \stripos($suggested_variable_name, $incomplete_variable_name) === false) {
                continue;
            }
            $request->recordCompletionElement(
                $code_base,
                $variable,
                $prefix . $incomplete_variable_name
            );
        }
        $superglobal_names = \array_merge(\array_keys(Variable::_BUILTIN_SUPERGLOBAL_TYPES), Config::getValue('runkit_superglobals'));
        foreach ($superglobal_names as $superglobal_name) {
            if ($incomplete_variable_name !== '' && \stripos($superglobal_name, $incomplete_variable_name) === false) {
                continue;
            }
            $request->recordCompletionElement(
                $code_base,
                new Variable(
                    $context,
                    $superglobal_name,
                    // @phan-suppress-next-line PhanTypeMismatchArgumentNullable
                    Variable::getUnionTypeOfHardcodedGlobalVariableWithName($superglobal_name),
                    0
                ),
                $prefix . $incomplete_variable_name
            );
        }
    }
}
