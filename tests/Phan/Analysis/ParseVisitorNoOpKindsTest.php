<?php

declare(strict_types=1);

namespace Phan\Tests\Analysis;

use AssertionError;
use Phan\Analysis\ScopeVisitor;
use Phan\AST\Visitor\Element;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\Parse\ParseVisitor;
use Phan\Tests\TestBase;
use ReflectionMethod;

use function array_keys;
use function array_slice;
use function file;
use function implode;
use function in_array;
use function is_array;
use function preg_replace;
use function sort;
use function strpos;
use function strrpos;
use function substr;
use function token_get_all;
use function trim;

/**
 * Checks ParseVisitor::NO_OP_NODE_KINDS, the node kinds for which Analysis::parseNodeInContext() skips creating a ParseVisitor.
 */
final class ParseVisitorNoOpKindsTest extends TestBase
{
    /**
     * Returns the code between the outermost braces of a method body, without comments and with whitespace normalized.
     */
    private static function getMethodBody(ReflectionMethod $method): string
    {
        $file_name = $method->getFileName();
        self::assertIsString($file_name);
        $start_line = (int)$method->getStartLine();
        $end_line = (int)$method->getEndLine();
        $source = implode('', array_slice(file($file_name) ?: [], $start_line - 1, $end_line - $start_line + 1));
        $start = strpos($source, '{');
        $end = strrpos($source, '}');
        if (!\is_int($start) || !\is_int($end)) {
            throw new AssertionError("Could not find the body of {$method->getName()}");
        }
        $code = '';
        foreach (token_get_all('<?php ' . substr($source, $start + 1, $end - $start - 1)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [\T_OPEN_TAG, \T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[0] === \T_WHITESPACE ? ' ' : $token[1];
            } else {
                $code .= $token;
            }
        }
        return trim(preg_replace('/\s+/', ' ', $code));
    }

    /**
     * A kind is a no-op kind exactly when ParseVisitor's handler for it returns $this->context and does nothing else:
     * either the handler is inherited from KindVisitorImplementation (which calls ScopeVisitor::visit()),
     * or it is overridden with a method that only returns $this->context.
     */
    public function testNoOpNodeKindsMatchTheHandlersOfParseVisitor(): void
    {
        $visit = new ReflectionMethod(ParseVisitor::class, 'visit');
        $this->assertSame(ScopeVisitor::class, $visit->getDeclaringClass()->getName());
        $this->assertSame('return $this->context;', self::getMethodBody($visit));

        $expected = [];
        foreach (Element::VISIT_LOOKUP_TABLE as $kind => $method_name) {
            $method = new ReflectionMethod(ParseVisitor::class, $method_name);
            $body = self::getMethodBody($method);
            if ($method->getDeclaringClass()->getName() === KindVisitorImplementation::class) {
                $this->assertSame('return $this->visit($node);', $body, "unexpected body of $method_name");
                $expected[] = $kind;
            } elseif ($body === 'return $this->context;') {
                $expected[] = $kind;
            }
        }
        $actual = array_keys(ParseVisitor::NO_OP_NODE_KINDS);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, 'ParseVisitor::NO_OP_NODE_KINDS must list exactly the kinds with a no-op handler');
    }

    /**
     * The handlers of these kinds record declarations and other facts, so nodes of these kinds must be visited.
     */
    public function testKindsWithEffectsAreVisited(): void
    {
        foreach ([
            \ast\AST_CALL,
            \ast\AST_CLASS,
            \ast\AST_CLOSURE,
            \ast\AST_ARROW_FUNC,
            \ast\AST_DECLARE,
            \ast\AST_FUNC_DECL,
            \ast\AST_GROUP_USE,
            \ast\AST_METHOD,
            \ast\AST_NAMESPACE,
            \ast\AST_RETURN,
            \ast\AST_STATIC,
            \ast\AST_STATIC_CALL,
            \ast\AST_STMT_LIST,
            \ast\AST_USE,
            \ast\AST_YIELD,
            \ast\AST_YIELD_FROM,
        ] as $kind) {
            $this->assertArrayNotHasKey($kind, ParseVisitor::NO_OP_NODE_KINDS, \ast\get_kind_name($kind));
        }
    }
}
