<?php

declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Comment\NullComment;
use Phan\Library\StringUtil;
use Phan\PluginV3;
use Phan\PluginV3\AfterAnalyzeFileCapability;

/**
 * This plugin checks for the use of phpdoc annotations in non-phpdoc comments
 * (e.g. starting with `/*` or `//`)
 *
 * Note that this is slow due to needing token_get_all.
 *
 * TODO: Cache and reuse the results
 */
class PHPDocInWrongCommentPlugin extends PluginV3 implements
    AfterAnalyzeFileCapability
{
    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context @phan-unused-param
     * A context with the file name for $file_contents and the scope after analyzing $node.
     *
     * @param string $file_contents the unmodified file contents @phan-unused-param
     * @param Node $node the node @phan-unused-param
     * @override
     * @throws Error if a process fails to shut down
     */
    public function afterAnalyzeFile(
        CodeBase $code_base,
        Context $context,
        string $file_contents,
        Node $node
    ): void {
        try {
            $tokens = PhpToken::tokenize($file_contents);
        } catch (\Throwable) {
            // If tokenization fails, skip analysis
            return;
        }
        foreach ($tokens as $token) {
            if (!$token->is(T_COMMENT)) {
                continue;
            }
            // This is a comment, not T_DOC_COMMENT
            $comment_string = $token->text;
            if (strncmp($comment_string, '/*', 2) !== 0) {
                if ($comment_string[0] === '#' && substr($comment_string, 1, 1) !== '[') {
                    $this->emitIssue(
                        $code_base,
                        (clone $context)->withLineNumberStart($token->line),
                        'PhanPluginPHPDocHashComment',
                        'Saw comment starting with {COMMENT} in {COMMENT} - consider using {COMMENT} instead to avoid confusion with {COMMENT} attributes',
                        ['#', StringUtil::jsonEncode(self::truncate(trim($comment_string))), '//', '#[']
                    );
                }
                continue;
            }
            if (!str_contains($comment_string, '@')) {
                continue;
            }
            $lineno = $token->line;

            // @phan-suppress-next-line PhanAccessClassConstantInternal
            $comment = Comment::fromStringInContext("/**" . $comment_string, $code_base, $context, $lineno, Comment::ON_ANY);

            if ($comment instanceof NullComment) {
                continue;
            }
            $this->emitIssue(
                $code_base,
                (clone $context)->withLineNumberStart($token->line),
                'PhanPluginPHPDocInWrongComment',
                'Saw possible phpdoc annotation in ordinary block comment {COMMENT}. PHPDoc comments should start with "/**" (followed by whitespace), not "/*"',
                [StringUtil::jsonEncode(self::truncate($comment_string))]
            );
        }
    }

    private static function truncate(string $token): string
    {
        if (strlen($token) > 200) {
            return mb_substr($token, 0, 200) . "...";
        }
        return $token;
    }
}
return new PHPDocInWrongCommentPlugin();
