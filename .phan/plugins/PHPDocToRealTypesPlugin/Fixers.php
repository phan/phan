<?php

declare(strict_types=1);

namespace PHPDocToRealTypesPlugin;

use Microsoft\PhpParser;
use Microsoft\PhpParser\FunctionLike;
use Microsoft\PhpParser\Node\Expression\AnonymousFunctionCreationExpression;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Statement\FunctionDeclaration;
use Microsoft\PhpParser\Token;
use Phan\AST\TolerantASTConverter\NodeUtils;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;

/**
 * This plugin implements --automatic-fix for PHPDocToRealTypesPlugin
 */
class Fixers
{

    /**
     * Add a missing return type to the real signature
     * @param CodeBase $code_base @unused-param
     */
    public static function fixReturnType(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $params = $instance->getTemplateParameters();
        $return_type = $params[0];
        $name = $params[1];
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $declaration = self::findFunctionLikeDeclaration($contents, $instance->getLine(), $name);
        if (!$declaration) {
            return null;
        }
        return self::computeEditsForReturnTypeDeclaration($declaration, (string)$return_type);
    }

    /**
     * Add a missing param type to the real signature
     * @unused-param $code_base
     */
    public static function fixParamType(
        CodeBase $code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ): ?FileEditSet {
        $params = $instance->getTemplateParameters();
        $param_type = $params[0];
        $param_name = $params[1];
        $method_name = $params[2];
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $declaration = self::findFunctionLikeDeclaration($contents, $instance->getLine(), $method_name);
        if (!$declaration) {
            return null;
        }
        return self::computeEditsForParamTypeDeclaration($contents, $declaration, (string)$param_name, (string)$param_type);
    }

    private static function computeEditsForReturnTypeDeclaration(FunctionLike $declaration, string $return_type): ?FileEditSet
    {
        if ($return_type === '') {
            return null;
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        $close_bracket = $declaration->anonymousFunctionUseClause->closeParen ?? $declaration->closeParen;
        if (!$close_bracket instanceof Token) {
            return null;
        }
        // get the byte where the `)` of the argument list ends
        $last_byte_index = $close_bracket->getEndPosition();
        $file_edit = new FileEdit($last_byte_index, $last_byte_index, " : $return_type");
        return new FileEditSet([$file_edit]);
    }

    private static function computeEditsForParamTypeDeclaration(FileCacheEntry $contents, FunctionLike $declaration, string $param_name, string $param_type): ?FileEditSet
    {
        if ($param_type === '') {
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
            $token = $param->byRefToken ?? $param->dotDotDotToken ?? $param->variableName;
            $token_start_index = $token->start;
            $file_edit = new FileEdit($token_start_index, $token_start_index, "$param_type ");
            return new FileEditSet([$file_edit]);
        }
        return null;
    }

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
