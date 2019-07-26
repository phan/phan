<?php
declare(strict_types=1);

use ast\Node;
use Phan\Language\Element\Variable;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PluginAwarePreAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\PluginV3\PreAnalyzeNodeCapability;

/**
 * This plugin modifies Phan's analysis of code using FFI\CData variables.
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
 * - This plugin does mangle state because FFI\CData is typical
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class FFIAnalysisPlugin extends PluginV3 implements PostAnalyzeNodeCapability, PreAnalyzeNodeCapability
{
    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     * @override
     */
    public static function getPreAnalyzeNodeVisitorClassName() : string
    {
        return FFIPreAnalysisVisitor::class;
    }

    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return FFIPostAnalysisVisitor::class;
    }
}

/**
 * This visitor records FFI\CData types if the original value was FFI\CData
 */
class FFIPreAnalysisVisitor extends PluginAwarePreAnalysisVisitor
{
    /**
     * @override
     * @param Node $node a node of kind ast\AST_ASSIGN
     */
    public function visitAssign(Node $node) : void
    {
        $left = $node->children['var'];
        if (!($left instanceof Node)) {
            return;
        }
        if ($left->kind !== ast\AST_VAR) {
            return;
        }
        $var_name = $left->children['name'];
        if (!is_string($var_name)) {
            return;
        }
        $scope = $this->context->getScope();
        if (!$scope->hasVariableWithName($var_name)) {
            return;
        }
        $var = $scope->getVariableByName($var_name);
        $category = self::containsFFICDataType($var->getUnionType());
        if (!$category) {
            return;
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        $node->is_ffi = $category;
    }

    const PARTIALLY_FFI_CDATA = 1;
    const ENTIRELY_FFI_CDATA = 2;

    /**
     * Check if the type contains FFI\CData
     */
    private static function containsFFICDataType(UnionType $union_type) : int
    {
        foreach ($union_type->getTypeSet() as $type) {
            if (strcasecmp('\FFI', $type->getNamespace()) !== 0) {
                continue;
            }
            if (strcasecmp('CData', $type->getName()) !== 0) {
                continue;
            }
            if ($type->isNullable()) {
                return self::PARTIALLY_FFI_CDATA;
            }
            if ($union_type->typeCount() > 1) {
                return self::PARTIALLY_FFI_CDATA;
            }
            return self::ENTIRELY_FFI_CDATA;
        }
        return 0;
    }
}

/**
 * This visitor restores FFI\CData types after assignments if the original value was FFI\CData
 */
class FFIPostAnalysisVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @override
     */
    public function visitAssign(Node $node) : void
    {
        // @phan-suppress-next-line PhanUndeclaredProperty
        if (isset($node->is_ffi)) {
            $this->analyzeFFIAssign($node);
        }
    }

    private function analyzeFFIAssign(Node $node) : void
    {
        $var_name = $node->children['var']->children['name'] ?? null;
        if (!is_string($var_name)) {
            return;
        }
        $cdata_type = UnionType::fromFullyQualifiedPHPDocString('\FFI\CData');
        $scope = $this->context->getScope();
        // @phan-suppress-next-line PhanUndeclaredProperty
        if ($node->is_ffi !== FFIPreAnalysisVisitor::ENTIRELY_FFI_CDATA) {
            if ($scope->hasVariableWithName($var_name)) {
                $cdata_type = $cdata_type->withUnionType($scope->getVariableByName($var_name)->getUnionType());
            }
        }
        $this->context->getScope()->addVariable(
            new Variable($this->context, $var_name, $cdata_type, 0)
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.

return new FFIAnalysisPlugin();
