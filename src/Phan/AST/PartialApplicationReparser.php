<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use CompileError;
use Phan\AST\TolerantASTConverter\Shim;
use Phan\Config;
use PhpToken;

use function count;

/**
 * Recovers the arguments of php 8.6 partial function application (`f(1, ?)`, `f(1, ...)`, `f(a: ?)`)
 * when the installed php-ast does not support AST version 130.
 *
 * Before version 130, php-ast creates an AST_CALLABLE_CONVERT node without children for partial function application,
 * the same as for first-class callable syntax `f(...)`. For a file that contains placeholders, this replaces the placeholders
 * with constants, parses the result, and turns the argument lists with those constants back into the version 130 shape:
 * an AST_CALLABLE_CONVERT node with an 'args' child, in which the placeholders are nodes of kind ast\AST_PLACEHOLDER_ARG.
 *
 * @see PartialApplication
 */
final class PartialApplicationReparser
{
    /** The constant that replaces the placeholder `?` */
    private const PLACEHOLDER = '__PHAN_PLACEHOLDER__';
    /**
     * The start of the constant that replaces the variadic placeholder `...`.
     * It ends with the line of the token after the `...`, which php uses as the line of the placeholder:
     * the parser reads that token before it creates the node.
     */
    private const VARIADIC_PLACEHOLDER_PREFIX = '__PHAN_VARIADIC_PLACEHOLDER_';

    /**
     * Whitespace and comments between two tokens.
     * (`#[` starts an attribute, not a comment)
     */
    private const GAP = '(?:\s++|/\*.*?\*/|//[^\n]*+|#(?!\[)[^\n]*+)*+';

    /**
     * Matches the placeholders of partial function application in valid php 8.6 code: `?` in `(?`, `, ?`, `name: ?` followed by `,` or `)`,
     * and `...` with a `,` on either side. This also matches text in strings and comments, which the tokenizer then skips.
     * Plain first-class callable syntax `f(...)` does not match.
     */
    private const PREFILTER_REGEX = '@[(,:]' . self::GAP . '\?' . self::GAP . '[,)]|,' . self::GAP . '\.\.\.' . self::GAP . '[,)]|\(' . self::GAP . '\.\.\.' . self::GAP . ',@s';

    /**
     * @return bool whether partial function application must be recovered from the source:
     * php 8.6+ can parse it, and the php-ast version does not provide its arguments.
     */
    public static function isEnabled(): bool
    {
        return \PHP_VERSION_ID >= 80600 && Config::AST_VERSION < 130;
    }

    /**
     * @return bool whether $contents may contain placeholders of partial function application.
     * False for most files, which are then not tokenized.
     */
    public static function mayContainPlaceholders(string $contents): bool
    {
        return \preg_match(self::PREFILTER_REGEX, $contents) > 0;
    }

    /**
     * Replaces the placeholders of partial function application with constants.
     * The line numbers do not change.
     *
     * $contents must be valid php 8.6 code: in other code, a `?` or `...` in the place of an argument is not always a placeholder.
     *
     * The `...` of first-class callable syntax `f(...)` is also replaced, so that every AST_CALLABLE_CONVERT node in the file has the shape of version 130.
     *
     * @return ?array{0:string,1:int} the code with the placeholders replaced and the number of placeholders replaced,
     * or null if there is no partial function application.
     */
    public static function rewrite(string $contents): ?array
    {
        $tokens = PhpToken::tokenize($contents);
        /** @var list<int> $significant the indexes of the tokens that are not whitespace or comments */
        $significant = [];
        foreach ($tokens as $i => $token) {
            if (!$token->isIgnorable()) {
                $significant[] = $i;
            }
        }
        /** @var array<int,string> $replacements the replacement for the token at an index of $tokens */
        $replacements = [];
        // Whether there is a placeholder other than the `...` of first-class callable syntax `f(...)`
        $has_partial_application = false;
        $last = count($significant) - 1;
        for ($j = 1; $j < $last; $j++) {
            $token = $tokens[$significant[$j]];
            if ($token->id === \T_ELLIPSIS) {
                $previous_text = $tokens[$significant[$j - 1]]->text;
                if ($previous_text !== '(' && $previous_text !== ',') {
                    continue;
                }
            } elseif ($token->text === '?') {
                $previous_text = $tokens[$significant[$j - 1]]->text;
                if ($previous_text !== '(' && $previous_text !== ',' && $previous_text !== ':') {
                    continue;
                }
            } else {
                continue;
            }
            $next_token = $tokens[$significant[$j + 1]];
            if ($next_token->text !== ',' && $next_token->text !== ')') {
                continue;
            }
            if ($token->id === \T_ELLIPSIS) {
                $replacements[$significant[$j]] = '\\' . self::VARIADIC_PLACEHOLDER_PREFIX . $next_token->line;
                $has_partial_application = $has_partial_application || $previous_text !== '(' || $next_token->text !== ')';
            } else {
                $replacements[$significant[$j]] = '\\' . self::PLACEHOLDER;
                $has_partial_application = true;
            }
        }
        if (!$has_partial_application) {
            // There is nothing to recover: version 120 has the same nodes for `f(...)`, apart from the 'args' child.
            return null;
        }
        $result = '';
        foreach ($tokens as $i => $token) {
            $result .= $replacements[$i] ?? $token->text;
        }
        return [$result, count($replacements)];
    }

    /**
     * Parses $contents, which contains partial function application, with the arguments of partial function application
     * in the shape of AST version 130.
     *
     * @param int $ast_version the AST version to parse the rewritten code with (Config::AST_VERSION, or 120 in tests)
     * @return ?Node the root node, or null if there are no placeholders or the arguments could not be recovered
     */
    public static function reparse(string $contents, string $file_path, int $ast_version = Config::AST_VERSION): ?Node
    {
        $rewrite = self::rewrite($contents);
        if (!$rewrite) {
            return null;
        }
        [$rewritten, $placeholder_count] = $rewrite;
        try {
            $node = \ast\parse_code($rewritten, $ast_version, $file_path);
        } catch (CompileError) {
            return null;
        }
        if (self::restorePlaceholders($node) !== $placeholder_count) {
            // A constant was not in the place of an argument. This should not happen.
            return null;
        }
        return $node;
    }

    /**
     * Converts the argument lists in $node that contain the constants for placeholders to the shape of AST version 130.
     *
     * @return int the number of placeholders restored
     */
    private static function restorePlaceholders(Node $node): int
    {
        $count = 0;
        foreach ($node->children as $key => $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if ($key !== 'args' || $child->kind !== ast\AST_ARG_LIST) {
                $count += self::restorePlaceholders($child);
                continue;
            }
            $first_placeholder_lineno = null;
            foreach ($child->children as $i => $arg) {
                if (!$arg instanceof Node) {
                    continue;
                }
                $placeholder = self::toPlaceholder($arg);
                if ($placeholder) {
                    $child->children[$i] = $placeholder;
                } elseif ($arg->kind === ast\AST_NAMED_ARG && ($placeholder = self::toPlaceholder($arg->children['expr']))) {
                    $arg->children['expr'] = $placeholder;
                } else {
                    $count += self::restorePlaceholders($arg);
                    continue;
                }
                $count++;
                $first_placeholder_lineno ??= $placeholder->lineno;
            }
            if ($first_placeholder_lineno !== null) {
                // php uses the lower of the line of the first argument and the line of the token after it as the line of the list.
                // For a variadic placeholder, that is the line of the placeholder: the line of the token after the `...`.
                $first_arg = $child->children[0] ?? null;
                if ($first_arg instanceof Node && $first_arg->kind === ast\AST_PLACEHOLDER_ARG) {
                    $child->lineno = $first_arg->lineno;
                }
                // php creates this node when it adds the first placeholder to the list
                $node->children['args'] = new Node(ast\AST_CALLABLE_CONVERT, 0, ['args' => $child], $first_placeholder_lineno);
            }
        }
        return $count;
    }

    /**
     * @param Node|string|int|float|null $node an argument or the expression of a named argument
     * @return ?Node the placeholder that $node stands for, if it is one of the constants that replaced a placeholder
     */
    private static function toPlaceholder(Node|float|int|string|null $node): ?Node
    {
        if (!$node instanceof Node || $node->kind !== ast\AST_CONST) {
            return null;
        }
        $name_node = $node->children['name'];
        if (!$name_node instanceof Node || $name_node->flags !== ast\flags\NAME_FQ) {
            return null;
        }
        $name = $name_node->children['name'];
        if ($name === self::PLACEHOLDER) {
            return new Node(ast\AST_PLACEHOLDER_ARG, 0, [], $node->lineno);
        }
        if (!\is_string($name) || !\str_starts_with($name, self::VARIADIC_PLACEHOLDER_PREFIX)) {
            return null;
        }
        $lineno = \substr($name, \strlen(self::VARIADIC_PLACEHOLDER_PREFIX));
        if (!\ctype_digit($lineno)) {
            return null;
        }
        return new Node(ast\AST_PLACEHOLDER_ARG, ast\flags\PLACEHOLDER_VARIADIC, [], (int)$lineno);
    }
}

// Defines ast\AST_PLACEHOLDER_ARG and ast\flags\PLACEHOLDER_VARIADIC when php-ast is older than 1.1.5
Shim::load();
