<?php declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Context;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin enforces that loose equality is used for numeric operands (e.g. `2 == 2.0`),
 * and that strict equality is used for non-numeric operands (e.g. `"2" === "2e0"` is false).
 */
class NumericalComparisonPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     *
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return NumericalComparisonVisitor::class;
    }
}

/**
 * This visitor checks binary operators to check that
 * loose equality is used for numeric operands (e.g. `2 == 2.0`),
 * and that strict equality is used for non-numeric operands (e.g. `"2" === "2e0"` is false).
 */
class NumericalComparisonVisitor extends PluginAwarePostAnalysisVisitor
{
    /** define equal operator list */
    const BINARY_EQUAL_OPERATORS = [
        ast\flags\BINARY_IS_EQUAL,
        ast\flags\BINARY_IS_NOT_EQUAL,
    ];

    /** define identical operator list */
    const BINARY_IDENTICAL_OPERATORS = [
        ast\flags\BINARY_IS_IDENTICAL,
        ast\flags\BINARY_IS_NOT_IDENTICAL,
    ];

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @override
     */
    public function visitBinaryOp(Node $node) : Context
    {
        // get the types of left and right values
        $left_node = $node->children['left'];
        $left_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $left_node);
        $right_node = $node->children['right'];
        $right_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $right_node);

        // non numerical values are not allowed in the operator equal(==, !=)
        if (in_array($node->flags, self::BINARY_EQUAL_OPERATORS, true)) {
            if (!$left_type->isNonNullNumberType() &&
                !$right_type->isNonNullNumberType()
            ) {
                $this->emit(
                    'PhanPluginNumericalComparison',
                    "non numerical values compared by the operators '==' or '!='",
                    []
                );
            }
            // numerical values are not allowed in the operator identical('===', '!==')
        } elseif (in_array($node->flags, self::BINARY_IDENTICAL_OPERATORS, true)) {
            if ($left_type->isNonNullNumberType() ||
                $right_type->isNonNullNumberType()) {
                // TODO: different name for this issue type?
                $this->emit(
                    'PhanPluginNumericalComparison',
                    "numerical values compared by the operators '===' or '!=='",
                    []
                );
            }
        }
        return $this->context;
    }
}

return new NumericalComparisonPlugin();
