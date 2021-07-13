<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Exception;
use Phan\AST\AnalysisVisitor;
use Phan\AST\FallbackUnionTypeVisitor;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\NodeException;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Parse\ParseVisitor;

use function array_map;
use function is_scalar;
use function json_encode;

/**
 * Conservatively determines types set for a variable anywhere in a function as a fallback.
 * This only attempts to analyze expressions with known real types.
 *
 * This is useful when analyzing loops for the first time,
 * when the types set later in the loop aren't known.
 * @phan-file-suppress PhanThrowTypeAbsent
 */
class FallbackMethodTypesVisitor extends AnalysisVisitor
{
    /** @var associative-array<mixed, list<UnionType>> the list of union types assigned to a variable */
    public $known_types = [];
    /** @var associative-array<mixed, true> the set of variables with unknown types */
    public $unknowns = [];

    public function __construct(CodeBase $code_base, Context $context)
    {
        $this->code_base = $code_base;
        $this->context = $context;
    }

    /**
     * Conservatively infers types from all assignments seen and parameters/closure use variables.
     * Gives up when non-literals are seen.
     * @return array<mixed, UnionType>
     */
    public static function inferTypes(CodeBase $code_base, FunctionInterface $func): array
    {
        $function_node = $func->getNode();
        if (!$function_node instanceof Node) {
            // XXX this won't work in --quick due to not storing nodes.
            return [];
        }
        $stmts = $function_node->children['stmts'] ?? null;
        if (!$stmts instanceof Node) {
            return [];
        }
        try {
            $visitor = new self($code_base, $func->getContext());
            $visitor->visit($stmts);
            foreach ($func->getParameterList() as $param) {
                $visitor->associateType($param->getName(), $param->getUnionType());
            }
            foreach ($function_node->children['uses']->children ?? [] as $use) {
                // @phan-suppress-next-line PhanPossiblyUndeclaredProperty
                $visitor->unknowns[$use->children['name']] = true;
            }
            $result = [];
            foreach (\array_diff_key($visitor->known_types, $visitor->unknowns) as $key => $union_types) {
                $result[$key] = UnionType::merge($union_types)->asNormalizedTypes();
            }
            // echo json_encode(array_map('strval', $result));
            return $result;
        } catch (NodeException $_) {
            return [];
        }
    }

    /**
     * @override
     * @unused-param $node
     * @return void
     */
    public function visitClass(Node $node)
    {
    }

    /**
     * @override
     * @unused-param $node
     * @return void
     */
    public function visitFuncDecl(Node $node)
    {
    }

    /**
     * @override
     * @return void
     */
    public function visitClosure(Node $node)
    {
        // handle uses by ref - treat as having unknown types for now.
        // TODO: Could recurse for more accuracy
        foreach ($node->children['uses']->children ?? [] as $c) {
            if ($c instanceof Node && ($c->flags & ast\flags\CLOSURE_USE_REF)) {
                $this->unknowns[$c->children['name']] = true;
            }
        }
    }

    /**
     * @override
     * @unused-param $node
     * @return void
     */
    public function visitArrowFunc(Node $node)
    {
    }

    /**
     * @override
     * @return void
     */
    public function visit(Node $node)
    {
        foreach ($node->children as $c) {
            if ($c instanceof Node) {
                $this->__invoke($c);
            }
        }
    }

    /**
     * @override
     * @return void
     */
    public function visitAssign(Node $node)
    {
        // \Phan\Debug::printNode($node);
        $var = $node->children['var'];
        if (!($var instanceof Node) || $var->kind !== ast\AST_VAR) {
            $this->excludeReferencedVariables($node);
            return;
        }
        $var_name = $var->children['name'];
        if (!is_scalar($var_name)) {
            $this->excludeReferencedVariables($node);
            return;
        }
        $expr = $node->children['expr'];
        $this->associateTypeWithExpression($var_name, $expr);
        $this->visit($node);
    }

    /**
     * @override
     * @return void
     */
    public function visitAssignRef(Node $node)
    {
        $this->excludeReferencedVariables($node);
    }

    private function excludeReferencedVariables(Node $node): void
    {
        switch ($node->kind) {
            case ast\AST_VAR:
                $name = $node->children['name'];
                if (!is_scalar($name)) {
                    throw new NodeException($node, "Dynamic reference not supported");
                }
                $this->unknowns[$name] = true;
                return;
            case ast\AST_CLOSURE:
                $this->visitClosure($node);
                return;
            case ast\AST_FUNC_DECL:
            case ast\AST_ARROW_FUNC:
            case ast\AST_CLASS:
                return;
        }
    }

    /**
     * @param int|string|float|bool $var_name
     * @param int|string|float|Node $expr
     */
    private function associateTypeWithExpression($var_name, $expr): void
    {
        if (isset($this->unknowns[$var_name])) {
            // No point in checking.
            return;
        }
        if (!$expr instanceof Node) {
            $this->associateType($var_name, Type::fromObject($expr)->asRealUnionType());
            return;
        }
        $type = $this->determineUnionType($expr);
        if ($type instanceof UnionType) {
            $this->associateType($var_name, $type);
        } else {
            $this->unknowns[$var_name] = true;
        }
    }

    private function determineUnionType(Node $expr): ?UnionType
    {
        try {
            if (ParseVisitor::isConstExpr($expr, ParseVisitor::CONSTANT_EXPRESSION_FORBID_NEW_EXPRESSION)) {
                return (new UnionTypeVisitor($this->code_base, $this->context, false))->__invoke($expr);
            }
            return (new FallbackUnionTypeVisitor($this->code_base, $this->context))->__invoke($expr);
        } catch (Exception $_) {
        }
        // TODO: Handle binary ops such as %, >, ternary, etc.
        return null;
    }

    /**
     * @param int|string|float|bool $var_name
     */
    private function associateType($var_name, UnionType $type): void
    {
        if (!$type->isEmpty()) {
            $this->known_types[$var_name][] = $type;
        } else {
            $this->unknowns[$var_name] = true;
        }
    }

    /**
     * Returns a representation of this visitor suitable for debugging
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getDebugRepresentation(): string
    {
        return "FallbackTypeVisitor: " . json_encode([
            'known_types' => array_map(
                /**
                 * @param list<UnionType> $union_types
                 * @return list<string>
                 */
                static function (array $union_types): array {
                    return array_map(static function (UnionType $type): string {
                        return $type->getDebugRepresentation();
                    }, $union_types);
                },
                $this->known_types
            ),
            'unknowns' => $this->unknowns,
        ], \JSON_PRETTY_PRINT);
    }
}
