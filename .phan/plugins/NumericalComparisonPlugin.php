<?php declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Context;
use Phan\PluginV2;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use Phan\PluginV2\PostAnalyzeNodeCapability;

/**
 * This plugin enforces that loose equality is used for numeric operands (e.g. `2 == 2.0`),
 * and that strict equality is used for non-numeric operands (e.g. `"2" === "2e0"` is false).
 */
class NumericalComparisonPlugin extends PluginV2 implements PostAnalyzeNodeCapability
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
        if (in_array($node->flags, self::BINARY_EQUAL_OPERATORS)) {
            if (!($this->isNumericalType($left_type->serialize())) &
                !($this->isNumericalType($right_type->serialize()))
            ) {
                $this->emit(
                    'PhanPluginNumericalComparison',
                    "non numerical values compared by the operators '==' or '!='",
                    []
                );
            }
            // numerical values are not allowed in the operator identical('===', '!==')
        } elseif (in_array($node->flags, self::BINARY_IDENTICAL_OPERATORS)) {
            if ($this->isNumericalType($left_type->serialize()) |
                $this->isNumericalType($right_type->serialize())
            ) {
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

    /**
     * Judge the argument is 'int', 'float' or not
     *
     * @param string $type serialized UnionType string
     * @return bool argument string indicates numerical type or not
     */
    private function isNumericalType(string $type) : bool
    {
        return $type === 'int' || $type === 'float';
    }
}

return new NumericalComparisonPlugin();
