<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use ast;
use Closure;
use Error;
use Exception;
use InvalidArgumentException;
use Microsoft\PhpParser;
use Microsoft\PhpParser\Token;

/**
 * This is a trait to be used multiple times to account for https://wiki.php.net/rfc/static_variable_inheritance changing behavior in php 8.1
 */
trait TolerantASTConverterTrait
{
    /**
     * @return array<string,Closure(object,int):(\ast\Node|int|string|float|null)>
     */
    abstract protected static function initHandleMap(): array;

    /**
     * @param PhpParser\Node|Token $n - The node from PHP-Parser
     * @return ast\Node|ast\Node[]|string|int|float|bool|null - whatever ast\parse_code would return as the equivalent.
     *                                                          This does not convert names to ast\AST_CONST.
     * @throws InvalidArgumentException if Phan doesn't know what $n is
     *
     * @suppress PhanAbstractStaticMethodCallInTrait
     */
    protected static function phpParserNonValueNodeToAstNode(\Microsoft\PhpParser\Node|Token $n): \ast\Node|array|bool|float|int|null|string
    {
        static $callback_map;
        static $fallback_closure;
        if (\is_null($callback_map)) {
            $callback_map = static::initHandleMap();
            /**
             * @return ast\Node - Not a real node, but a node indicating the TODO
             * @throws InvalidArgumentException for invalid node classes
             * @throws Error if the environment variable AST_THROW_INVALID is set (for debugging)
             */
            $fallback_closure = static function (\Microsoft\PhpParser\Node|Token $n, int $unused_start_line): \ast\Node {
                return TolerantASTConverter::astStub($n);
            };
        }
        $callback = $callback_map[\get_class($n)] ?? $fallback_closure;
        // @phan-suppress-next-line PhanThrowTypeMismatch
        return $callback($n, TolerantASTConverter::getStartLine($n));
    }

    /**
     * @param PhpParser\Node|Token $n - The node from PHP-Parser
     * @return ast\Node|ast\Node[]|string|int|float|null - whatever ast\parse_code would return as the equivalent.
     * @suppress PhanAbstractStaticMethodCallInTrait
     */
    protected static function phpParserNodeToAstNode(\Microsoft\PhpParser\Node|Token $n): \ast\Node|array|float|int|null|string
    {
        static $callback_map;
        static $fallback_closure;
        if (\is_null($callback_map)) {
            $callback_map = static::initHandleMap();
            /**
             * @return ast\Node - Not a real node, but a node indicating the TODO
             * @throws InvalidArgumentException|Exception for invalid node classes
             * @throws Error if the environment variable AST_THROW_INVALID is set to debug.
             */
            $fallback_closure = static function (\Microsoft\PhpParser\Node|Token $n, int $unused_start_line): \ast\Node {
                return TolerantASTConverter::astStub($n);
            };
        }
        $callback = $callback_map[\get_class($n)] ?? $fallback_closure;
        // @phan-suppress-next-line PhanThrowTypeAbsent
        $result = $callback($n, TolerantASTConverter::$file_position_map->getStartLine($n));
        if (($result instanceof ast\Node) && $result->kind === ast\AST_NAME) {
            return new ast\Node(ast\AST_CONST, 0, ['name' => $result], $result->lineno);
        }
        return $result;
    }
}
