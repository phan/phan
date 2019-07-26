<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast;
use ast\flags;
use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Type\StringType;
use Phan\Library\Paths;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

use function file_exists;
use function is_file;

/**
 * Analyzes require/include/require_once/include_once statements to check if the file exists
 */
class RequireExistsPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return RequireExistsVisitor::class;
    }
}

/**
 * Visits require/include/require_once/include_once statements to check if the file exists
 */
class RequireExistsVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @override
     */
    public function visitIncludeOrEval(Node $node) : void
    {
        if ($node->flags === ast\flags\EXEC_EVAL) {
            $this->analyzeEval($node);
            return;
        }
        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            $path = (new ContextNode($this->code_base, $this->context, $expr))->getEquivalentPHPScalarValue();
        } else {
            $path = $expr;
        }

        if (!\is_string($path)) {
            $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $expr);
            if (!$type->canCastToUnionType(StringType::instance(false)->asPHPDocUnionType())) {
                $this->emitIssue(
                    Issue::TypeInvalidRequire,
                    $expr->lineno ?? $node->lineno,
                    $type
                );
            }
            return;
        }
        $this->checkPathExistsInContext($node, $path);
    }

    private function analyzeEval(Node $node) : void
    {
        $expr = $node->children['expr'];
        $type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $expr);
        if (!$type->canCastToUnionType(StringType::instance(false)->asPHPDocUnionType())) {
            $this->emitIssue(
                Issue::TypeInvalidEval,
                $expr->lineno ?? $node->lineno,
                $type
            );
        }
    }

    /**
     * Check if the path provided to include()/require_once()/etc is valid.
     */
    private function checkPathExistsInContext(Node $node, string $relative_path) : void
    {
        $absolute_path = $this->getAbsolutePath($node, $relative_path);
        if (!file_exists($absolute_path)) {
            $this->emitIssue(
                Issue::MissingRequireFile,
                $node->children['expr']->lineno ?? $node->lineno,
                Paths::escapePathForIssue($relative_path)
            );
            return;
        }
        if (!is_file($absolute_path)) {
            $this->emitIssue(
                Issue::InvalidRequireFile,
                $node->children['expr']->lineno ?? $node->lineno,
                Paths::escapePathForIssue($relative_path)
            );
            return;
        }
    }

    const EXEC_NODE_FLAG_NAMES = [
        flags\EXEC_EVAL => 'eval',
        flags\EXEC_INCLUDE => 'include',
        flags\EXEC_INCLUDE_ONCE => 'include_once',
        flags\EXEC_REQUIRE => 'require',
        flags\EXEC_REQUIRE_ONCE => 'require_once',
    ];

    private function getAbsolutePath(Node $node, string $relative_path) : string
    {
        if (Paths::isAbsolutePath($relative_path)) {
            return $relative_path;
        }

        if (Config::getValue('warn_about_relative_include_statement')) {
            $this->emitIssue(
                Issue::RelativePathUsed,
                $node->children['exec']->lineno ?? $node->lineno,
                self::EXEC_NODE_FLAG_NAMES[$node->flags] ?? 'unknown',
                Paths::escapePathForIssue($relative_path)
            );
        }

        $first_absolute_path = null;
        foreach (Config::getValue('include_paths') ?: ['.'] as $include_path) {
            if (!Paths::isAbsolutePath($include_path)) {
                $include_path = Paths::toAbsolutePath(\dirname(Config::projectPath($this->context->getFile())), $include_path);
            }
            $absolute_path = Paths::toAbsolutePath($include_path, $relative_path);
            if (file_exists($absolute_path)) {
                return $absolute_path;
            }
            $first_absolute_path = $first_absolute_path ?? $absolute_path;
        }
        // If we searched every directory in include_paths, but none existed,
        // then give up and return the first (missing) resolved path.
        return $first_absolute_path ?? '(unknown)';
    }
}
