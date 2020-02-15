<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Library\FileCacheEntry;
use Phan\Library\StringUtil;
use Phan\Phan;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\AutomaticFixCapability;
use PHPDocRedundantPlugin\Fixers;

/**
 * This plugin checks for redundant doc comments on functions, closures, and methods.
 *
 * This treats a doc comment as redundant if
 *
 * 1. It is exclusively annotations (0 or more), e.g. (at)return void
 * 2. Every annotation repeats the real information in the signature.
 *
 * It does not check if the change is safe to make.
 */
class PHPDocRedundantPlugin extends PluginV3 implements
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    AutomaticFixCapability
{
    private const RedundantFunctionComment = 'PhanPluginRedundantFunctionComment';
    private const RedundantClosureComment = 'PhanPluginRedundantClosureComment';
    private const RedundantMethodComment = 'PhanPluginRedundantMethodComment';
    private const RedundantReturnComment = 'PhanPluginRedundantReturnComment';

    public function analyzeFunction(CodeBase $code_base, Func $function): void
    {
        self::analyzeFunctionLike($code_base, $function);
    }

    public function analyzeMethod(CodeBase $code_base, Method $method): void
    {
        if ($method->isMagic() || $method->isPHPInternal()) {
            return;
        }
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            return;
        }
        self::analyzeFunctionLike($code_base, $method);
    }

    /**
     * @suppress PhanAccessClassConstantInternal
     */
    private static function isRedundantFunctionComment(FunctionInterface $method, string $doc_comment): bool
    {
        $lines = explode("\n", $doc_comment);
        foreach ($lines as $line) {
            $line = trim($line, " \r\n\t*/");
            if ($line === '') {
                continue;
            }
            if ($line[0] !== '@') {
                return false;
            }
            if (!preg_match('/^@(phan-)?(param|return)\s/', $line)) {
                return false;
            }
            if (preg_match(Builder::PARAM_COMMENT_REGEX, $line, $matches)) {
                if ($matches[0] !== $line) {
                    // There's a description after the (at)param annotation
                    return false;
                }
            } elseif (preg_match(Builder::RETURN_COMMENT_REGEX, $line, $matches)) {
                if ($matches[0] !== $line) {
                    // There's a description after the (at)return annotation
                    return false;
                }
            } else {
                // This is not a valid annotation. It might be documentation.
                return false;
            }
        }
        $comment = $method->getComment();
        if (!$comment) {
            // unparseable?
            return false;
        }
        if ($comment->hasReturnUnionType()) {
            $comment_return_type = $comment->getReturnType();
            if (!$comment_return_type->isEmpty() && !$comment_return_type->asNormalizedTypes()->isEqualTo($method->getRealReturnType())) {
                return false;
            }
        }
        if (count($comment->getParameterList()) > 0) {
            return false;
        }
        foreach ($comment->getParameterMap() as $comment_param_name => $param) {
            $comment_param_type = $param->getUnionType()->asNormalizedTypes();
            if ($comment_param_type->isEmpty()) {
                return false;
            }
            foreach ($method->getRealParameterList() as $real_param) {
                if ($real_param->getName() === $comment_param_name) {
                    if ($real_param->getUnionType()->isEqualTo($comment_param_type)) {
                        // This is redundant, check remaining parameters.
                        continue 2;
                    }
                }
            }
            // could not find that comment param, Phan warns elsewhere.
            // Assume this is not redundant.
            return false;
        }
        return true;
    }

    private static function analyzeFunctionLike(CodeBase $code_base, FunctionInterface $method): void
    {
        if (Phan::isExcludedAnalysisFile($method->getContext()->getFile())) {
            // This has no side effects, so we can skip files that don't need to be analyzed
            return;
        }
        $comment = $method->getDocComment();
        if (!StringUtil::isNonZeroLengthString($comment)) {
            return;
        }
        if (!self::isRedundantFunctionComment($method, $comment)) {
            self::checkIsRedundantReturn($code_base, $method, $comment);
            return;
        }
        $encoded_comment = StringUtil::encodeValue($comment);
        if ($method instanceof Method) {
            self::emitIssue(
                $code_base,
                $method->getContext(),
                self::RedundantMethodComment,
                'Redundant doc comment on method {METHOD}(). Either add a description or remove the comment: {COMMENT}',
                [$method->getName(), $encoded_comment]
            );
        } elseif ($method instanceof Func && $method->isClosure()) {
            self::emitIssue(
                $code_base,
                $method->getContext(),
                self::RedundantClosureComment,
                'Redundant doc comment on closure {FUNCTION}. Either add a description or remove the comment: {COMMENT}',
                [$method->getNameForIssue(), $encoded_comment]
            );
        } else {
            self::emitIssue(
                $code_base,
                $method->getContext(),
                self::RedundantFunctionComment,
                'Redundant doc comment on function {FUNCTION}(). Either add a description or remove the comment: {COMMENT}',
                [$method->getName(), $encoded_comment]
            );
        }
    }

    private static function checkIsRedundantReturn(CodeBase $code_base, FunctionInterface $method, string $doc_comment): void
    {
        if (strpos($doc_comment, '@return') === false) {
            return;
        }
        $comment = $method->getComment();
        if (!$comment) {
            // unparseable?
            return;
        }
        if ($method->getRealReturnType()->isEmpty()) {
            return;
        }
        if (!$comment->hasReturnUnionType()) {
            return;
        }
        $comment_return_type = $comment->getReturnType();
        if (!$comment_return_type->asNormalizedTypes()->isEqualTo($method->getRealReturnType())) {
            return;
        }
        $lines = explode("\n", $doc_comment);
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            $line = trim($line, " \r\n\t*/");
            if ($line === '') {
                continue;
            }
            if ($line[0] !== '@') {
                return;
            }
            if (!preg_match('/^@(phan-)?return\s/', $line)) {
                continue;
            }
            // @phan-suppress-next-line PhanAccessClassConstantInternal
            if (!preg_match(Builder::RETURN_COMMENT_REGEX, $line, $matches)) {
                return;
            }
            if ($matches[0] !== $line) {
                // There's a description after the (at)return annotation
                return;
            }
            self::emitIssue(
                $code_base,
                $method->getContext()->withLineNumberStart($comment->getReturnLineno()),
                self::RedundantReturnComment,
                'Redundant @return {TYPE} on function {FUNCTION}. Either add a description or remove the @return annotation: {COMMENT}',
                [$comment_return_type, $method->getNameForIssue(), $line]
            );
            return;
        }
    }

    /**
     * @return array<string,Closure(CodeBase,FileCacheEntry,IssueInstance):(?FileEditSet)>
     */
    public function getAutomaticFixers(): array
    {
        require_once __DIR__ .  '/PHPDocRedundantPlugin/Fixers.php';
        $function_like_fixer = Closure::fromCallable([Fixers::class, 'fixRedundantFunctionLikeComment']);
        return [
            self::RedundantFunctionComment => $function_like_fixer,
            self::RedundantMethodComment => $function_like_fixer,
            self::RedundantClosureComment => $function_like_fixer,
            self::RedundantReturnComment => Closure::fromCallable([Fixers::class, 'fixRedundantReturnComment']),
        ];
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PHPDocRedundantPlugin();
