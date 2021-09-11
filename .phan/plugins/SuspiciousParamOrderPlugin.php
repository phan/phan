<?php

declare(strict_types=1);

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
    public static function getPostAnalyzeNodeVisitorClassName(): string
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
    private const SuspiciousParamOrderInternal = 'PhanPluginSuspiciousParamOrderInternal';
    private const SuspiciousParamOrder = 'PhanPluginSuspiciousParamOrder';
    private const SuspiciousParamPosition = 'PhanPluginSuspiciousParamPosition';
    private const SuspiciousParamPositionInternal = 'PhanPluginSuspiciousParamPositionInternal';
    // phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

    /**
     * @param Node $node a node of type AST_CALL
     * @override
     */
    public function visitCall(Node $node): void
    {
        $args = $node->children['args']->children;
        if (count($args) < 1) {
            // Can't have a suspicious param order/position if there are no params
            // (or for AST_CALLABLE_CONVERT)
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
    private static function extractName($arg_node): ?string
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
    private static function computeDistance(string $a, string $b): float
    {
        $la = strlen($a);
        $lb = strlen($b);
        return (levenshtein($a, $b) - abs($la - $lb)) / max(1, min($la, $lb));
    }

    /**
     * @param list<Node|string|int|float> $args
     */
    private function checkCall(FunctionInterface $function, array $args, Node $node): void
    {
        $arg_names = [];
        foreach ($args as $i => $arg_node) {
            $name = self::extractName($arg_node);
            if (!is_string($name)) {
                continue;
            }
            $arg_names[$i] = strtolower($name);
        }
        if (count($arg_names) < 2) {
            if (count($arg_names) === 1) {
                $this->checkMovedArg($function, $args, $node, $arg_names);
            }
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
            // $arg_names and $parameter_names have the same keys
            $this->checkMovedArg($function, $args, $node, $arg_names);
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
            $this->checkMovedArg($function, $args, $node, $arg_names);
            return;
        }
        $places_set = [];
        foreach (self::findCycles($best_destination_map) as $cycle) {
            // To reduce false positives, don't warn unless we know the parameter $j would be compatible with what was used at $i
            foreach ($cycle as $array_index => $i) {
                $j = $cycle[($array_index + 1) % count($cycle)];
                $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $args[$i]);
                // echo "Checking if $type can cast to $parameters[$j]\n";
                if (!$type->canCastToUnionType($parameters[$j]->getUnionType(), $this->code_base)) {
                    continue 2;
                }
            }
            foreach ($cycle as $i) {
                $places_set[$i] = true;
            }
            $arg_details = implode(' and ', array_map(static function (int $i) use ($args): string {
                return self::extractName($args[$i]) ?? 'unknown';
            }, $cycle));
            $param_details = implode(' and ', array_map(static function (int $i) use ($parameters): string {
                $param = $parameters[$i];
                return '#' . ($i + 1) . ' (' . trim($param->getUnionType() . ' $' . $param->getName()) . ')';
            }, $cycle));
            if ($function->isPHPInternal()) {
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($node->lineno),
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
                    (clone $this->context)->withLineNumberStart($node->lineno),
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
        $this->checkMovedArg($function, $args, $node, $arg_names, $places_set);
    }

    /**
     * @param FunctionInterface $function the function being called
     * @param list<Node|string|int|float> $args
     * @param Node $node
     * @param associative-array<int,string> $arg_names
     * @param associative-array<int,true> $places_set the places that were already warned about being transposed.
     */
    private function checkMovedArg(FunctionInterface $function, array $args, Node $node, array $arg_names, array $places_set = []): void
    {
        $real_parameters = $function->getRealParameterList();
        $parameters = $function->getParameterList();
        /** @var associative-array<string,?int> maps lowercase param names to their unique index, or null */
        $parameter_names = [];
        foreach ($real_parameters as $i => $param) {
            if (isset($places_set[$i])) {
                continue;
            }
            $name_key = str_replace('_', '', strtolower($param->getName()));
            if (array_key_exists($name_key, $parameter_names)) {
                $parameter_names[$name_key] = null;
            } else {
                $parameter_names[$name_key] = $i;
            }
        }
        foreach ($arg_names as $i => $name) {
            $other_i = $parameter_names[str_replace('_', '', strtolower($name))] ?? null;
            if ($other_i === null || $other_i === $i) {
                continue;
            }
            $real_param = $real_parameters[$other_i];
            if ($real_param->isVariadic()) {
                // Skip warning about signatures such as var_dump($var, ...$args) or array_unshift($values, $arg, $arg2)
                //
                // NOTE: For internal functions, some functions such as implode() have alternate signatures where the real parameter is in a different place,
                // which is why this checks both $real_param and $param
                //
                // For user-defined functions, alternates are not supported.
                continue;
            }
            $param = $parameters[$other_i] ?? null;
            if ($param && $param->getName() === $real_param->getName()) {
                if ($param->isVariadic()) {
                    continue;
                }
                $real_param = $param;
            }
            $real_param_details = '#' . ($other_i + 1) . ' (' . trim($real_param->getUnionType() . ' $' . $real_param->getName()) . ')';
            $arg_details = self::extractName($args[$i]) ?? 'unknown';
            if ($function->isPHPInternal()) {
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($args[$i]->lineno ?? $node->lineno),
                    self::SuspiciousParamPositionInternal,
                    'Suspicious order for argument {DETAILS} - This is getting passed to parameter {DETAILS} of {FUNCTION}',
                    [
                        $arg_details,
                        $real_param_details,
                        $function->getRepresentationForIssue(true),
                    ]
                );
            } else {
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($args[$i]->lineno ?? $node->lineno),
                    self::SuspiciousParamPosition,
                    'Suspicious order for argument {DETAILS} - This is getting passed to parameter {DETAILS} of {FUNCTION} defined at {FILE}:{LINE}',
                    [
                        $arg_details,
                        $real_param_details,
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
    private static function normalizeCycle(array $values, int $next): array
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
    public static function findCycles(array $destination_map): array
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
     * @param Node $node a node of type AST_NULLSAFE_METHOD_CALL
     * @override
     */
    public function visitNullsafeMethodCall(Node $node): void
    {
        $this->visitMethodCall($node);
    }

    /**
     * @param Node $node a node of type AST_METHOD_CALL
     * @override
     */
    public function visitMethodCall(Node $node): void
    {
        $args = $node->children['args']->children;
        if (count($args) < 1) {
            // Can't have a suspicious param order/position if there are no params
            // (or for AST_CALLABLE_CONVERT)
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
            ))->getMethod($method_name, false, true);
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
    public function visitStaticCall(Node $node): void
    {
        $args = $node->children['args']->children;
        if (count($args) < 1) {
            // Can't have a suspicious param order/position if there are no params
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
