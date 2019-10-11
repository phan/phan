<?php declare(strict_types=1);

use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\Exception\CodeBaseException;
use Phan\Language\Element\FunctionInterface;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * A plugin that checks if calls to a function or method pass in arguments in a suspicious order.
 * E.g. calling `function example($offset, $count)` as `example($count, $offset)`
 */
class SuspiciousParamOrderPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return SuspiciousParamOrderVisitor::class;
    }
}

/**
 * Checks for invocations of functions/methods where the return value should be used.
 * Also, gathers statistics on how often those functions/methods are used.
 */
class SuspiciousParamOrderVisitor extends PluginAwarePostAnalysisVisitor
{
    // phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    // this is deliberate for issue names
    const SuspiciousParamOrderInternal = 'PhanPluginSuspiciousParamOrderInternal';
    const SuspiciousParamOrder = 'PhanPluginSuspiciousParamOrder';
    // phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

    /**
     * @param Node $node a node of type AST_CALL
     * @override
     */
    public function visitCall(Node $node) : void
    {
        $args = $node->children['args']->children;
        if (count($args) < 2) {
            // Can't have a suspicious param order if there are less than 2 params
            return;
        }
        $expression = $node->children['expr'];
        try {
            $function_list_generator = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getFunctionFromNode();

            foreach ($function_list_generator as $function) {
                // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                $this->checkCall($function, $args, $node);
            }
        } catch (CodeBaseException $_) {
        }
    }

    /**
     * @param Node|string|int|float|null $arg_node
     */
    private static function extractName($arg_node) : ?string
    {
        if (!$arg_node instanceof Node) {
            return null;
        }
        switch ($arg_node->kind) {
            case ast\AST_VAR:
                $name = $arg_node->children['name'];
                break;
                /*
            case ast\AST_CONST:
                $name = $arg_node->children['name']->children['name'];
                break;
                 */
            case ast\AST_PROP:
            case ast\AST_STATIC_PROP:
                $name = $arg_node->children['prop'];
                break;
            case ast\AST_METHOD_CALL:
            case ast\AST_STATIC_CALL:
                $name = $arg_node->children['method'];
                break;
            case ast\AST_CALL:
                $name = $arg_node->children['expr'];
                break;
            default:
                return null;
        }
        return is_string($name) ? $name : null;
    }

    /**
     * Returns a distance in the range 0..1, inclusive.
     *
     * A distance of 0 means they are similar (e.g. foo and getFoo()),
     * and 1 means there are no letters in common (bar and foo)
     */
    private static function computeDistance(string $a, string $b) : float
    {
        $la = strlen($a);
        $lb = strlen($b);
        return (levenshtein($a, $b) - abs($la - $lb)) / max(1, min($la, $lb));
    }

    /**
     * @param list<Node|string|int|float|null> $args
     */
    private function checkCall(FunctionInterface $function, array $args, Node $node) : void
    {
        $arg_names = [];
        foreach ($args as $i => $arg_node) {
            $name = self::extractName($arg_node);
            if (!is_string($name)) {
                return;
            }
            $arg_names[$i] = strtolower($name);
        }
        if (count($arg_names) < 2) {
            return;
        }
        $parameters = $function->getParameterList();
        $parameter_names = [];
        foreach ($arg_names as $i => $_) {
            if (!isset($parameters[$i])) {
                unset($arg_names[$i]);
                continue;
            }
            $parameter_names[$i] = strtolower($parameters[$i]->getName());
        }
        if (count($arg_names) < 2) {
            return;
        }
        $best_destination_map = [];
        foreach ($arg_names as $i => $name) {
            // To even be considered, the distance metric must be less than 60% (100% would have nothing in common)
            $best_distance = min(
                0.6,
                self::computeDistance($name, $parameter_names[$i])
            );
            $best_destination = null;
            // echo "Distances for $name to $parameter_names[$i] is $best_distance\n";

            foreach ($parameter_names as $j => $parameter_name_j) {
                if ($j === $i) {
                    continue;
                }
                $d_swap_j = self::computeDistance($name, $parameter_name_j);
                // echo "Distances for $name to $parameter_name_j is $d_swap_j\n";
                if ($d_swap_j < $best_distance) {
                    $best_destination = $j;
                    $best_distance = $d_swap_j;
                }
            }
            if ($best_destination !== null) {
                $best_destination_map[$i] = $best_destination;
            }
        }
        if (count($best_destination_map) < 2) {
            return;
        }
        foreach (self::findCycles($best_destination_map) as $cycle) {
            // To reduce false positives, don't warn unless we know the parameter $j would be compatible with what was used at $i
            foreach ($cycle as $array_index => $i) {
                $j = $cycle[($array_index + 1) % count($cycle)];
                $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $args[$i]);
                // echo "Checking if $type can cast to $parameters[$j]\n";
                if (!$type->asExpandedTypes($this->code_base)->canCastToUnionType($parameters[$j]->getUnionType())) {
                    continue 2;
                }
            }
            $arg_details = implode(' and ', array_map(static function (int $i) use ($args) : string {
                return self::extractName($args[$i]) ?? 'unknown';
            }, $cycle));
            $param_details = implode(' and ', array_map(static function (int $i) use ($parameters) : string {
                $param = $parameters[$i];
                return '#' . ($i + 1) . ' (' . trim($param->getUnionType() . ' $' . $param->getName()) . ')';
            }, $cycle));
            if ($function->isPHPInternal()) {
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($node->lineno),
                    self::SuspiciousParamOrderInternal,
                    'Suspicious order for arguments named {DETAILS} - These are being passed to parameters {DETAILS} of {FUNCTION}',
                    [
                        $arg_details,
                        $param_details,
                        $function->getRepresentationForIssue(true),
                    ]
                );
            } else {
                $this->emitPluginIssue(
                    $this->code_base,
                    clone($this->context)->withLineNumberStart($node->lineno),
                    self::SuspiciousParamOrder,
                    'Suspicious order for arguments named {DETAILS} - These are being passed to parameters {DETAILS} of {FUNCTION} defined at {FILE}:{LINE}',
                    [
                        $arg_details,
                        $param_details,
                        $function->getRepresentationForIssue(true),
                        $function->getContext()->getFile(),
                        $function->getContext()->getLineNumberStart(),
                    ]
                );
            }
        }
    }

    /**
     * @param list<int> $values
     * @return list<int> the same values of the cycle, rearranged to start with the smallest value.
     */
    private static function normalizeCycle(array $values, int $next) : array
    {
        $pos = array_search($next, $values, true);
        $values = array_slice($values, $pos ?: 0);
        $min_pos = 0;
        foreach ($values as $i => $value) {
            if ($value < $values[$min_pos]) {
                $min_pos = $values[$i];
            }
        }
        return array_merge(array_slice($values, $min_pos), array_slice($values, 0, $min_pos));
    }

    /**
     * Given [1 => 2, 2 => 3, 3 => 1, 4 => 5, 5 => 6, 6 => 5]], return [[1,2,3],[5,6]]
     * @param array<int,int> $destination_map
     * @return array<int,array<int,int>>
     */
    public static function findCycles(array $destination_map) : array
    {
        $result = [];
        while (count($destination_map) > 0) {
            reset($destination_map);
            $key = (int) key($destination_map);
            $values = [];
            while (count($destination_map) > 0) {
                $values[] = $key;
                $next = $destination_map[$key];
                unset($destination_map[$key]);
                if (in_array($next, $values, true)) {
                    $values = self::normalizeCycle($values, $next);
                    if (count($values) >= 2) {
                        $result[] = $values;
                    }
                    $values = [];
                    break;
                }
                if (!isset($destination_map[$next])) {
                    break;
                }
                $key = $next;
            }
        }
        return $result;
    }

    /**
     * @param Node $node a node of type AST_METHOD_CALL
     * @override
     */
    public function visitMethodCall(Node $node) : void
    {
        $args = $node->children['args']->children;
        if (count($args) < 2) {
            // Can't have a suspicious param order if there are less than 2 params
            return;
        }

        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, false);
        } catch (Exception $_) {
            return;
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $this->checkCall($method, $args, $node);
    }

    /**
     * @param Node $node a node of type AST_STATIC_CALL
     * @override
     */
    public function visitStaticCall(Node $node) : void
    {
        $args = $node->children['args']->children;
        if (count($args) < 2) {
            // Can't have a suspicious param order if there are less than 2 params
            return;
        }

        $method_name = $node->children['method'];

        if (!\is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod($method_name, true, true);
        } catch (Exception $_) {
            return;
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $this->checkCall($method, $args, $node);
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new SuspiciousParamOrderPlugin();
