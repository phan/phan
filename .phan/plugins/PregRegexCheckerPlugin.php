<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeFunctionCallCapability;
use ast\Node;

/**
 * This plugin checks for invalid regexes in preg_match.
 *
 * - getAnalyzeFunctionCallClosures
 *   This method returns a map from function/method FQSEN to closures that are called on invocations of those closures.
 */
class PregRegexCheckerPlugin extends PluginV2 implements AnalyzeFunctionCallCapability {
    private function analyzePattern(CodeBase $code_base, Context $context, Func $function, string $pattern)
    {
        $old_error_reporting = error_reporting();
        \error_reporting(0);
        \ob_start();
        \error_clear_last();
        try {
            $result = @\preg_match($pattern, '');
        } finally {
            \ob_end_clean();
            \error_reporting($old_error_reporting);
        }
        if ($result === false) {
            $err = \error_get_last() ?? [];
            $this->emitIssue(
                $code_base,
                $context,
                'PhanPluginInvalidPregRegex',
                'Call to {FUNCTION} was passed an invalid regex {STRING_LITERAL}: {DETAILS}',
                [(string)$function->getFQSEN(), \var_export($pattern, true), \preg_replace('@^preg_match\(\): @', '', $err['message'] ?? 'unknown error')]
            );
            return;
        }
    }

    /**
     * @return \Closure[]
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        /**
         * @return void
         */
        $preg_pattern_callback = function(
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) {
            if (\count($args) < 1) {
                return;
            }
            // TODO: Resolve global constants and class constants?
            $pattern = $args[0];
            if (\is_string($pattern)) {
                $this->analyzePattern($code_base, $context, $function, $pattern);
            }
        };

        /**
         * @return void
         */
        $preg_pattern_or_array_callback = function(
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) {
            if (\count($args) < 1) {
                return;
            }
            // TODO: Resolve global constants and class constants?
            $pattern = $args[0];
            if (\is_string($pattern)) {
                $this->analyzePattern($code_base, $context, $function, $pattern);
                return;
            }
            if ($pattern instanceof Node && $pattern->kind === ast\AST_ARRAY) {
                foreach ($pattern->children as $child) {
                    $pattern = $child->children['value'];
                    if (\is_string($pattern)) {
                        $this->analyzePattern($code_base, $context, $function, $pattern);
                    }
                }
                return;
            }
        };

        /**
         * @return void
         */
        $preg_replace_callback_array_callback = function(
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) {
            if (\count($args) < 1) {
                return;
            }
            // TODO: Resolve global constants and class constants?
            $pattern = $args[0];
            if ($pattern instanceof Node && $pattern->kind === ast\AST_ARRAY) {
                foreach ($pattern->children as $child) {
                    $pattern = $child->children['key'];
                    if (\is_string($pattern)) {
                        $this->analyzePattern($code_base, $context, $function, $pattern);
                    }
                }
                return;
            }
        };

        return [
            // call
            'preg_filter'                 => $preg_pattern_or_array_callback,
            'preg_grep'                   => $preg_pattern_callback,
            'preg_match'                  => $preg_pattern_callback,
            'preg_match_all'              => $preg_pattern_callback,
            'preg_replace_callback_array' => $preg_replace_callback_array_callback,
            'preg_replace_callback'       => $preg_pattern_or_array_callback,
            'preg_replace'                => $preg_pattern_or_array_callback,
            'preg_split'                  => $preg_pattern_callback,
        ];
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new PregRegexCheckerPlugin;
