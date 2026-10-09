<?php

declare(strict_types=1);

namespace Phan\Tests\AST;

use ast\Node;
use Phan\AST\ASTNormalizer;
use Phan\AST\Parser;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Tests\TestBase;

/**
 * Tests that clone expressions are normalized to AST_CLONE nodes.
 *
 * Parser only walks the AST for this when the source contains "clone" (case-insensitively),
 * so this also checks that the clone keyword is found when it isn't written in lowercase.
 */
final class ASTNormalizerTest extends TestBase
{
    private const SOURCE = '<?php CLONE $a; clone($b); $c = Clone $d;';

    /** This has no lowercase "clone", so a case-sensitive check would wrongly skip the normalization. */
    private const SOURCE_WITHOUT_LOWERCASE_CLONE = '<?php CLONE $a; CLONE($b); $c = Clone $d;';

    private const EXPECTED_KINDS = ['AST_CLONE', 'AST_CLONE', 'AST_CLONE'];

    /**
     * @return list<string> the kind names of the top-level statements (or of the right-hand side of assignments)
     */
    private static function getStatementKindNames(Node $node): array
    {
        $kinds = [];
        foreach ($node->children as $statement) {
            self::assertInstanceOf(Node::class, $statement);
            if ($statement->kind === \ast\AST_ASSIGN) {
                $statement = $statement->children['expr'];
                self::assertInstanceOf(Node::class, $statement);
            }
            $kinds[] = \ast\get_kind_name($statement->kind);
        }
        return $kinds;
    }

    public function testNormalizeCloneNodes(): void
    {
        $node = ASTNormalizer::normalizeCloneNodes(\ast\parse_code(self::SOURCE, Config::AST_VERSION));
        $this->assertSame(self::EXPECTED_KINDS, self::getStatementKindNames($node));
    }

    /**
     * @suppress PhanThrowTypeAbsentForCall
     */
    public function testParseCodeNormalizesCloneInAnyCase(): void
    {
        $code_base = new CodeBase([], [], [], [], []);
        $node = Parser::parseCode($code_base, new Context(), null, 'clone.php', self::SOURCE_WITHOUT_LOWERCASE_CLONE, false);
        $this->assertSame(self::EXPECTED_KINDS, self::getStatementKindNames($node));
    }

    /**
     * @suppress PhanThrowTypeAbsentForCall
     */
    public function testParseCodePolyfillNormalizesCloneInAnyCase(): void
    {
        $code_base = new CodeBase([], [], [], [], []);
        $errors = [];
        $node = Parser::parseCodePolyfill($code_base, new Context(), 'clone.php', self::SOURCE_WITHOUT_LOWERCASE_CLONE, false, null, $errors);
        $this->assertSame([], $errors);
        $this->assertSame(self::EXPECTED_KINDS, self::getStatementKindNames($node));
    }
}
