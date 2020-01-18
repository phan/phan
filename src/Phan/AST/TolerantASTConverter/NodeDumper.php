<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Exception;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

use function get_class;
use function gettype;
use function is_object;
use function substr;

/**
 * Source: https://github.com/TysonAndre/tolerant-php-parser-to-php-ast
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2017 Tyson Andre
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class NodeDumper
{
    /** @var string the contents of the file containing the tolerant-php-parser node to be dumped. */
    private $file_contents;
    /** @var bool whether we should print byte offsets along with the node */
    private $include_offset;
    /** @var bool whether we should print the node kind of tokens*/
    private $include_token_kind;
    /** @var string the indentation string to print before each level of nodes (whitespace) */
    private $indent;

    // TODO: Pass an options array instead, or add setters?
    public function __construct(string $file_contents, bool $include_offset = false, bool $include_token_kind = false, string $indent = '    ')
    {
        $this->file_contents = $file_contents;
        $this->include_offset = $include_offset;
        $this->include_token_kind = $include_token_kind;
        $this->indent = $indent;
    }

    /**
     * Should this include the byte offset in the file where the node occurred?
     */
    public function setIncludeOffset(bool $include_offset): void
    {
        $this->include_offset = $include_offset;
    }

    /**
     * Should this include the token kind (default is just the text of the token)
     */
    public function setIncludeTokenKind(bool $include_token_kind): void
    {
        $this->include_token_kind = $include_token_kind;
    }

    /**
     * Sets the text used for indentation (e.g. 4 spaces)
     * @suppress PhanUnreferencedPublicMethod
     */
    public function setIndent(string $indent): void
    {
        $this->indent = $indent;
    }

    /**
     * Converts the class name of $ast_node to a short string describing that class name.
     * Removes the common `Microsoft\\PhpParser\\` prefix
     */
    public static function dumpClassName(Node $ast_node): string
    {
        $name = get_class($ast_node);
        if (\stripos($name, 'Microsoft\\PhpParser\\') === 0) {
            // Remove the PhpParser namespace
            $name = (string)substr($name, 20);
        }
        return $name;
    }

    /**
     * Converts the class name of $token to a short string describing that class name.
     * Removes the common `Microsoft\\PhpParser\\` prefix
     */
    public static function dumpTokenClassName(Token $token): string
    {
        $name = get_class($token);
        if (\stripos($name, 'Microsoft\\PhpParser\\') === 0) {
            // Remove the PhpParser namespace
            $name = (string)substr($name, 20);
        }
        return $name;
    }

    /**
     * @param Node|Token|null $ast_node
     * @param string $padding (to be echoed before the current node
     * @throws Exception for invalid $ast_node values
     */
    public function dumpTreeAsString($ast_node, string $key = '', string $padding = ''): string
    {
        if ($ast_node instanceof Node) {
            $first_part = \sprintf(
                "%s%s%s%s\n",
                $padding,
                $key !== '' ? $key . ': ' : '',
                self::dumpClassName($ast_node),
                $this->include_offset ? ' (@' . $ast_node->getStart() . ')' : ''
            );

            $result = $first_part;
            foreach ($ast_node->getChildNodesAndTokens() as $name => $child) {
                $result .= $this->dumpTreeAsString($child, (string) $name, $padding . $this->indent);
            }
            return $result;
        } elseif ($ast_node instanceof Token) {
            return \sprintf(
                "%s%s%s: %s%s%s: %s\n",
                $padding,
                $key !== '' ? $key . ': ' : '',
                self::dumpTokenClassName($ast_node),
                $ast_node->getTokenKindNameFromValue($ast_node->kind),
                $this->include_token_kind ? '(' . $ast_node->kind . ')' : '',
                $this->include_offset ? ' (@' . $ast_node->start . ')' : '',
                \Phan\Library\StringUtil::jsonEncode(\substr($this->file_contents, $ast_node->fullStart, $ast_node->length))
            );
        } elseif (\is_scalar($ast_node)) {
            return \var_export($ast_node, true);
        } elseif ($ast_node === null) {
            return 'null';
        } else {
            $type = is_object($ast_node) ? get_class($ast_node) : gettype($ast_node);
            throw new \InvalidArgumentException("Unexpected type of \$ast_node was seen in dumper: " . $type);
        }
    }

    /**
     * @param Node|Token $ast_node
     * @param string $padding (to be echoed before the current node
     * @throws Exception for invalid $ast_node values
     * @suppress PhanUnreferencedPublicMethod
     */
    public function dumpTree($ast_node, string $key = '', string $padding = ''): void
    {
        echo $this->dumpTreeAsString($ast_node, $key, $padding);
    }
}
