<?php

declare(strict_types=1);

namespace PreferNamespaceUsePlugin;

use Microsoft\PhpParser;
use Microsoft\PhpParser\FunctionLike;
use Microsoft\PhpParser\Node\Expression\AnonymousFunctionCreationExpression;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Statement\FunctionDeclaration;
use Phan\AST\TolerantASTConverter\NodeUtils;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;

/**
 * This plugin implements --automatic-fix for PreferNamespaceUsePlugin
 */
class Fixers
{

    /**
     * Generate an edit to replace a fully qualified return type with a shorter equivalent representation.
     * @unused-param $code_base
     */
    public static function fixReturnType(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $params = $instance->getTemplateParameters();
        $shorter_return_type = \ltrim((string)$params[1], '?');
        $method_name = $params[0];
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $declaration = self::findFunctionLikeDeclaration($contents, $instance->getLine(), $method_name);
        if (!$declaration) {
            return null;
        }
        return self::computeEditsForReturnTypeDeclaration($declaration, $shorter_return_type);
    }

    /**
     * Generate an edit to replace a fully qualified param type with a shorter equivalent representation.
     * @unused-param $code_base
     */
    public static function fixParamType(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $params = $instance->getTemplateParameters();
        $shorter_return_type = \ltrim((string)$params[2], '?');
        $method_name = (string)$params[1];
        $param_name = (string)$params[0];
        $declaration = self::findFunctionLikeDeclaration($contents, $instance->getLine(), $method_name);
        if (!$declaration) {
            return null;
        }
        return self::computeEditsForParamTypeDeclaration($contents, $declaration, $param_name, $shorter_return_type);
    }

    /**
     * @suppress PhanThrowTypeAbsentForCall
     */
    private static function computeEditsForReturnTypeDeclaration(
        FunctionLike $declaration,
        string $shorter_return_type
    ): ?FileEditSet {
        // @phan-suppress-next-line PhanUndeclaredProperty
        $return_type_node = $declaration->returnType;
        if (!$return_type_node instanceof PhpParser\Node) {
            return null;
        }
        // Generate an edit to replace the long return type with the shorter return type
        // Long return types are always Nodes instead of Tokens.
        $file_edit = new FileEdit(
            $return_type_node->getStartPosition(),
            $return_type_node->getEndPosition(),
            $shorter_return_type
        );
        return new FileEditSet([$file_edit]);
    }

    private static function computeEditsForParamTypeDeclaration(
        FileCacheEntry $contents,
        FunctionLike $declaration,
        string $param_name,
        string $shorter_param_type
    ): ?FileEditSet {
        // @phan-suppress-next-line PhanUndeclaredProperty
        $return_type_node = $declaration->returnType;
        if (!$return_type_node) {
            return null;
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        $parameter_node_list = $declaration->parameters->children ?? [];
        foreach ($parameter_node_list as $param) {
            if (!$param instanceof PhpParser\Node\Parameter) {
                continue;
            }
            $declaration_name = (new NodeUtils($contents->getContents()))->tokenToString($param->variableName);
            if ($declaration_name !== $param_name) {
                continue;
            }
            $token = $param->typeDeclarationList;
            if (!$token) {
                return null;
            }
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall php-parser is not expected to throw here
            $start = $token->getStartPosition();
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall php-parser is not expected to throw here
            $file_edit = new FileEdit($start, $token->getEndPosition(), $shorter_param_type);
            return new FileEditSet([$file_edit]);
        }
        return null;
    }

    // TODO: Move this into a reusable function
    private static function findFunctionLikeDeclaration(
        FileCacheEntry $contents,
        int $line,
        string $name
    ): ?FunctionLike {
        $candidates = [];
        foreach ($contents->getNodesAtLine($line) as $node) {
            if ($node instanceof FunctionDeclaration || $node instanceof MethodDeclaration) {
                $name_node = $node->name;
                if (!$name_node) {
                    continue;
                }
                $declaration_name = (new NodeUtils($contents->getContents()))->tokenToString($name_node);
                if ($declaration_name === $name) {
                    $candidates[] = $node;
                }
            } elseif ($node instanceof AnonymousFunctionCreationExpression) {
                if ($name === '{closure}') {
                    $candidates[] = $node;
                }
            }
        }
        if (\count($candidates) === 1) {
            return $candidates[0];
        }
        return null;
    }
}
