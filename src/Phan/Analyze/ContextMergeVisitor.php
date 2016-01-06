<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\AST\ContextNode;
use \Phan\AST\UnionTypeVisitor;
use \Phan\AST\Visitor\KindVisitorImplementation;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\Element\Variable;
use \Phan\Language\Scope;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

class ContextMergeVisitor extends KindVisitorImplementation {
    use \Phan\Profile;

    /**
     * @var CodeBase
     */
    private $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

    /**
     * @var Context[]
     * A list of the contexts returned after depth-first
     * parsing of all first-level children of this node
     */
    private $child_context_list;

    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Context[] $child_context_list
     * A list of the contexts returned after depth-first
     * parsing of all first-level children of this node
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        array $child_context_list
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->child_context_list = $child_context_list;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node) : Context {
        return end($this->child_context_list) ?: $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIf(Node $node) : Context {

        // Get the list of scopes for each branch of the
        //
        // conditional
        $scope_list = self::time('scope_list', function() {
            return array_map(function (Context $context) {
                return $context->getScope();
            }, $this->child_context_list);
        });

        $has_else = self::time('has_else', function() use ($node) {
            return array_reduce($node->children ?? [],
                function (bool $carry, $child_node) {
                    return $carry || (
                        $child_node instanceof Node
                        && empty($child_node->children['cond'])
                    );
                }, false);
        });

        // If we're not guaranteed to hit at least one
        // branch, mark the incoming scope as a possibility
        if (!$has_else) {
            $scope_list[] = $this->context->getScope();
        }

        // If there weren't multiple branches, continue on
        // as if the conditional never happened
        if (count($scope_list) < 2) {
            return array_values($this->child_context_list)[0];
        }

        // Get a list of all variables in all scopes
        $variable_map = self::time('variable_map', function() use ($scope_list) {
            $variable_map = [];
            foreach ($scope_list as $scope) {
                foreach ($scope->getVariableMap() as $name => $variable) {
                    $variable_map[$name] = $variable;
                }
            }
            return $variable_map;
        });

        // A function that determins if a variable is defined on
        // every branch
        $is_defined_on_all_branches =
            function (string $variable_name) use ($scope_list) {
                return self::time('is_defined_on_all_branches', function()
                    use ($variable_name, $scope_list)
                {
                    return array_reduce($scope_list,
                        function (bool $has_variable, Scope $scope)
                            use ($variable_name)
                        {
                            return (
                                $has_variable &&
                                $scope->hasVariableWithName($variable_name)
                            );
                        }, true);
                });
            };

        // Get the intersection of all types for all versions of
        // the variable from every side of the branch
        $common_union_type =
            function (string $variable_name) use ($scope_list) {
                return self::time('common_union_type', function()
                    use ($variable_name, $scope_list)
                {
                    // Get a list of all variables with the given name from
                    // each scope
                    $variable_list = self::time('variable_list', function() use ($scope_list, $variable_name) {
                        return array_filter(array_map(
                            function (Scope $scope) use ($variable_name) {
                                if (!$scope->hasVariableWithName($variable_name)) {
                                    return null;
                                }

                                return $scope->getVariableWithName($variable_name);
                            }, $scope_list));
                    });

                    // Get the list of types for each version of the variable
                    $type_list_list =
                        self::time('type_list_list', function() use ($variable_list) {
                            return array_map(function (Variable $variable) {
                                return $variable->getUnionType()->getTypeList();
                            }, $variable_list);
                        });

                    if (count($type_list_list) < 2) {
                        return new UnionType($type_list_list[0] ?? []);
                    }

                    return self::time('intersect', function() use ($type_list_list) {
                        return new UnionType(call_user_func_array(
                            'array_intersect',
                            $type_list_list
                        ));
                    });
                });
            };

        $scope = new Scope();
        foreach ($variable_map as $name => $variable) {
            // Skip variables that are only partially defined
            if (!$is_defined_on_all_branches($name)) {
                continue;
            }

            // Limit the type of the variable to the subset
            // of types that are common to all branches
            $variable = clone($variable);
            $variable->setUnionType(
                $common_union_type($name)
            );

            // Add the variable to the outgoing scope
            $scope->addVariable($variable);
        }

        // print '<'.implode("\t", $scope_list) . "\n";
        // print '>'.$scope."\n";

        // Set the new scope with only the variables and types
        // that are common to all branches
        return $this->context->withScope($scope);
    }
}
