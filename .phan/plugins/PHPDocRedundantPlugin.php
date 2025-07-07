<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Language\Element\Comment;
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
 * If the doc comment as a whole is not redundant, this will also check if the (at)return annotation or
 * the (at)param annotations are redundant. Note, the parameter list is only considered redundant as a whole;
 * individual parameters are not flagged independently.
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
    private const RedundantParameterListComment = 'PhanPluginRedundantParameterListComment';
    private const RedundantReturnComment = 'PhanPluginRedundantReturnComment';

    public function analyzeFunction(CodeBase $code_base, Func $function): void
    {
        self::analyzeFunctionLike($code_base, $function);
    }

    public function analyzeMethod(CodeBase $code_base, Method $method): void
    {
        if ($method->isPHPInternal()) {
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
    private static function checkFunctionComment(
        CodeBase $code_base,
        FunctionInterface $method,
        Comment $comment,
        string $comment_str
    ): void {
        $lines = explode("\n", $comment_str);
        $has_redundant_comment = true;
        $has_redundant_param_list = true;
        $has_redundant_return = true;
        $seen_param_lines = [];
        $return_line = null;
        foreach ($lines as $line) {
            $line = trim($line, " \r\n\t*/");
            if ($line === '') {
                continue;
            }
            if ($line[0] !== '@') {
                $has_redundant_comment = false;
                if ($return_line !== null) {
                    // Text that might belong to the (at)return annotation
                    $has_redundant_return = false;
                }
                if ($seen_param_lines && $return_line ===null) {
                    // Text that might belong to an (at)param annotation
                    $has_redundant_param_list = false;
                }
            }
            if (!preg_match('/^@(phan-)?(param|return)\s/', $line)) {
                $has_redundant_comment = false;
                if ($seen_param_lines && $return_line ===null) {
                    // A tag, other than (at)param and (at)return, after the start of the parameter list but before
                    // the (at) return tag. Assume it might belong to an (at)param annotation.
                    $has_redundant_param_list = false;
                }
            }
            if (preg_match(Builder::PARAM_COMMENT_REGEX, $line, $matches)) {
                $seen_param_lines[] = $line;
                if ($matches[0] !== $line) {
                    // There's a description after the (at)param annotation
                    $has_redundant_comment = false;
                    $has_redundant_param_list = false;
                }
                if ($return_line !== null) {
                    // (at)param after (at)return. Avoid false positives and a potentially complicated fix.
                    $has_redundant_param_list = false;
                }
            } elseif (preg_match(Builder::RETURN_COMMENT_REGEX, $line, $matches)) {
                $return_line = $line;
                if ($matches[0] !== $line) {
                    // There's a description after the (at)return annotation
                    $has_redundant_comment = false;
                    $has_redundant_return = false;
                }
            } else {
                // This is not a valid annotation. It might be documentation.
                $has_redundant_comment = false;
            }

            if (!$has_redundant_comment && !$has_redundant_param_list && !$has_redundant_return) {
                // Nothing else to check.
                return;
            }
        }

        $comment_return_type = null;
        if ($comment->hasReturnUnionType()) {
            $comment_return_type = $comment->getReturnType();
            if (!$comment_return_type->isEmpty() && !$comment_return_type->asNormalizedTypes()->isEqualTo($method->getRealReturnType())) {
                $has_redundant_comment = false;
                $has_redundant_return = false;
            }
        } else {
            $has_redundant_return = false;
        }
        if (count($comment->getParameterList()) > 0) {
            $has_redundant_comment = false;
        }
        $comment_parameter_map = $comment->getParameterMap();
        foreach ($comment_parameter_map as $comment_param_name => $param) {
            $comment_param_type = $param->getUnionType()->asNormalizedTypes();
            if ($comment_param_type->isEmpty()) {
                // @phan-suppress-next-line PhanUnusedVariable Probably not understanding the `continue 2` below.
                $has_redundant_comment = false;
                // @phan-suppress-next-line PhanUnusedVariable Probably not understanding the `continue 2` below.
                $has_redundant_param_list = false;
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
            $has_redundant_comment = false;
            $has_redundant_param_list = false;
        }

        if ($has_redundant_comment) {
            self::emitRedundantCommentIssue($code_base, $method, $comment_str);
            return;
        }
        if ($return_line !== null && $has_redundant_return && $comment_return_type) {
            // Note, checking `$comment_return_type` is redundant but phan can't infer that it's not null when
            // `$has_redundant_return` is true.
            preg_match('/^@(phan-)?return/', $return_line, $return_matches);
            $return_tag = $return_matches[0];
            self::emitIssue(
                $code_base,
                (clone $method->getContext())->withLineNumberStart($comment->getReturnLineno()),
                self::RedundantReturnComment,
                'Redundant {COMMENT} {TYPE} on function {FUNCTION}. Either add a description or remove the {COMMENT} annotation: {COMMENT}',
                [$return_tag, $comment_return_type, $method->getNameForIssue(), $return_tag, $return_line]
            );
        }
        if ($has_redundant_param_list && $seen_param_lines && $comment_parameter_map) {
            $param_lines_text = StringUtil::encodeValue(implode("\n", $seen_param_lines));
            self::emitIssue(
                $code_base,
                (clone $method->getContext())->withLineNumberStart(reset($comment_parameter_map)->getLineno()),
                self::RedundantParameterListComment,
                'Redundant parameter list doc comment on function {FUNCTION}. Either add a description or remove the @param annotations: {COMMENT}',
                [$method->getNameForIssue(), $param_lines_text]
            );
        }
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
        $commentObj = $method->getComment();
        if (!$commentObj) {
            // unparseable?
            return;
        }
        self::checkFunctionComment($code_base, $method, $commentObj, $comment);
    }

    private static function emitRedundantCommentIssue(CodeBase $code_base, FunctionInterface $method, string $comment): void
    {
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
            self::RedundantParameterListComment => Closure::fromCallable([Fixers::class, 'fixRedundantParameterListComment']),
            self::RedundantReturnComment => Closure::fromCallable([Fixers::class, 'fixRedundantReturnComment']),
        ];
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PHPDocRedundantPlugin();
