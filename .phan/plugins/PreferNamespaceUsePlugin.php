<?php declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\AutomaticFixCapability;
use PreferNamespaceUsePlugin\Fixers;

/**
 * This plugin checks for redundant doc comments on functions, closures, and methods.
 *
 * This treats a doc comment as redundant if
 *
 * 1. It is exclusively annotations (0 or more), e.g. (at)return void
 * 2. Every annotation repeats the real information in the signature.
 *
 * It does not check if the change is safe to make.
 */
class PreferNamespaceUsePlugin extends PluginV3 implements
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    AutomaticFixCapability
{
    const PreferNamespaceUseParamType = 'PhanPluginPreferNamespaceUseParamType';
    const PreferNamespaceUseReturnType = 'PhanPluginPreferNamespaceUseReturnType';

    public function analyzeFunction(CodeBase $code_base, Func $function) : void
    {
        self::analyzeFunctionLike($code_base, $function);
    }

    public function analyzeMethod(CodeBase $code_base, Method $method) : void
    {
        if ($method->isMagic() || $method->isPHPInternal()) {
            return;
        }
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            return;
        }
        self::analyzeFunctionLike($code_base, $method);
    }

    private static function analyzeFunctionLike(CodeBase $code_base, FunctionInterface $method) : void
    {
        $node = $method->getNode();
        if (!$node) {
            return;
        }
        $return_type = $node->children['returnType'];
        if ($return_type instanceof Node) {
            self::analyzeFunctionLikeReturn($code_base, $method, $return_type);
        }
        foreach ($node->children['params']->children ?? [] as $param_node) {
            if (!($param_node instanceof Node)) {
                // impossible?
                continue;
            }
            self::analyzeFunctionLikeParam($code_base, $method, $param_node);
        }
    }

    private static function analyzeFunctionLikeReturn(CodeBase $code_base, FunctionInterface $method, Node $return_type) : void
    {
        $is_nullable = false;
        if ($return_type->kind === ast\AST_NULLABLE_TYPE) {
            $return_type = $return_type->children['type'];
            if (!($return_type instanceof Node)) {
                // should not happen
                return;
            }
            $is_nullable = true;
        }
        $shorter_return_type = self::determineShorterType($method->getContext(), $return_type);
        if (is_string($shorter_return_type)) {
            $prefix = $is_nullable ? '?' : '';
            self::emitIssue(
                $code_base,
                $method->getContext(),
                self::PreferNamespaceUseReturnType,
                'Could write return type of {FUNCTION} as {TYPE} instead of {TYPE}',
                [$method->getName(), $prefix . $shorter_return_type, $prefix . '\\' . $return_type->children['name']]
            );
        }
    }

    private static function analyzeFunctionLikeParam(CodeBase $code_base, FunctionInterface $method, Node $param_node) : void
    {
        $param_type = $param_node->children['type'];
        if (!$param_type instanceof Node) {
            return;
        }
        $is_nullable = false;
        if ($param_type->kind === ast\AST_NULLABLE_TYPE) {
            $param_type = $param_type->children['type'];
            if (!($param_type instanceof Node)) {
                // should not happen
                return;
            }
            $is_nullable = true;
        }
        $shorter_param_type = self::determineShorterType($method->getContext(), $param_type);
        if (is_string($shorter_param_type)) {
            $param_name = $param_node->children['name'];
            if (!is_string($param_name)) {
                // should be impossible
                return;
            }

            $prefix = $is_nullable ? '?' : '';
            self::emitIssue(
                $code_base,
                $method->getContext(),
                self::PreferNamespaceUseParamType,
                'Could write param type of ${PARAMETER} of {FUNCTION} as {TYPE} instead of {TYPE}',
                [$param_name, $method->getName(), $prefix . $shorter_param_type, $prefix . '\\' . $param_type->children['name']]
            );
        }
    }

    /**
     * Given a node with a parameter or return type, return a string with a shorter represented of the type (if possible), or return null if this is not possible.
     *
     * This does not try all possibilities, and only affects fully qualified types.
     */
    private static function determineShorterType(Context $context, Node $type_node) : ?string
    {
        if ($type_node->kind !== ast\AST_NAME) {
            return null;
        }

        if ($type_node->flags !== ast\flags\NAME_FQ) {
            return null;
        }
        $name = $type_node->children['name'];
        if (!is_string($name)) {
            return null;
        }
        $parts = explode('\\', $name);
        $name_end = (string)array_pop($parts);
        $namespace = implode('\\', $parts);

        if ($context->hasNamespaceMapFor(ast\flags\USE_NORMAL, $name_end)) {
            $fqsen = $context->getNamespaceMapFor(ast\flags\USE_NORMAL, $name_end);
            if ($fqsen->getName() === $name_end && strcasecmp(ltrim($fqsen->getNamespace(), '\\'), $namespace) === 0) {
                // found `use Bar\Something` when looking for `\Bar\Something`, so suggest `Something`
                return $name_end;
            }
            // TODO: Could look for `use \Foo\Bar as FB;`
        } elseif (strcasecmp($namespace, ltrim($context->getNamespace(), "\\")) === 0) {
            // Foo\Bar\Baz in Foo\Bar is Baz unless there is another namespace use shadowing it.
            return $name_end;
        }
        return null;
    }

    /**
     * @return array<string,Closure(CodeBase,FileCacheEntry,IssueInstance):(?FileEditSet)>
     */
    public function getAutomaticFixers() : array
    {
        require_once __DIR__ .  '/PreferNamespaceUsePlugin/Fixers.php';
        return [
            self::PreferNamespaceUseReturnType => Closure::fromCallable([Fixers::class, 'fixReturnType']),
            self::PreferNamespaceUseParamType => Closure::fromCallable([Fixers::class, 'fixParamType']),
            //self::RedundantClosureComment => $function_like_fixer,
        ];
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PreferNamespaceUsePlugin();
