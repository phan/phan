<?php declare(strict_types=1);

namespace PHPDocToRealTypesPlugin;

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
 * This plugin implements --automatic-fix for PHPDocToRealTypesPlugin
 */
class Fixers
{

    /**
     * Add a missing return type to the real signature
     * @return ?FileEditSet
     */
    public static function fixReturnType(
        CodeBase $unused_code_base,
        FileCacheEntry $contents,
        IssueInstance $instance
    ) : ?\Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet {
        $params = $instance->getTemplateParameters();
        $return_type = $params[0];
        $name = $params[1];
        \fwrite(\STDERR, "TODO: Add $return_type to $name\n");
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $declaration = self::findFunctionLikeDeclaration($contents, $instance->getLine(), $name);
        if (!$declaration) {
            return null;
        }
        return self::computeEditsForReturnTypeDeclaration($declaration, (string)$return_type);
    }

    /**
     * @return ?FileEditSet
     */
    private static function computeEditsForReturnTypeDeclaration(FunctionLike $declaration, string $return_type) : ?\Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet
    {
        if (!$return_type) {
            return null;
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        $close_bracket = $declaration->anonymousFunctionUseClause->closeParen ?? $declaration->closeParen;
        if (!$close_bracket) {
            return null;
        }
        // get the byte where the `)` of the argument list ends
        $last_byte_index = $close_bracket->getEndPosition();
        $file_edit = new FileEdit($last_byte_index, $last_byte_index, " : $return_type");
        return new FileEditSet([$file_edit]);
    }

    /**
     * @return ?FunctionLike
     */
    private static function findFunctionLikeDeclaration(
        FileCacheEntry $contents,
        int $line,
        string $name
    ) : ?\Microsoft\PhpParser\FunctionLike {
        $candidates = [];
        foreach ($contents->getNodesAtLine($line) as $node) {
            \fwrite(\STDERR, "Saw " . \get_class($node) . "\n");
            if ($node instanceof FunctionDeclaration || $node instanceof MethodDeclaration) {
                echo "Processing node";
                $name_node = $node->name;
                if (!$name_node) {
                    continue;
                }
                $declaration_name = (new NodeUtils($contents->getContents()))->tokenToString($name_node);
                echo "Comparing $declaration_name to $name\n";
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
