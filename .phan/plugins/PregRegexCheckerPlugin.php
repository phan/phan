<?php declare(strict_types=1);

use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeFunctionCallCapability;
use ast\Node;

/**
 * This plugin checks for invalid regexes in calls to preg_match. (And all of the other internal PCRE functions).
 *
 * This plugin performs this check by attempting to match the empty string,
 * then checking if PHP emitted a warning (Instead of failing to match)
 * (PHP doesn't have preg_validate())
 *
 * - getAnalyzeFunctionCallClosures
 *   This method returns a map from function/method FQSEN to closures that are called on invocations of those closures.
 */
class PregRegexCheckerPlugin extends PluginV2 implements AnalyzeFunctionCallCapability
{
    // Skip over analyzing regex keys that couldn't be resolved.
    // Don't try to convert values to PHP data (should be closures)
    const RESOLVE_REGEX_KEY_FLAGS = (ContextNode::RESOLVE_DEFAULT | ContextNode::RESOLVE_KEYS_SKIP_UNKNOWN_KEYS) &
        ~(ContextNode::RESOLVE_KEYS_SKIP_UNKNOWN_KEYS | ContextNode::RESOLVE_ARRAY_VALUES);


    private function analyzePattern(CodeBase $code_base, Context $context, Func $function, string $pattern)
    {
        /**
         * @suppress PhanParamSuspiciousOrder 100% deliberate use of varying regex and constant $subject for preg_match
         */
        $err = with_disabled_phan_error_handler(function () use ($pattern) {
            $old_error_reporting = error_reporting();
            \error_reporting(0);
            \ob_start();
            \error_clear_last();
            try {
                $result = @\preg_match($pattern, '');
                if ($result === false) {
                    $err = \error_get_last() ?? [];
                    var_export($err);
                    return $err;
                }
                return null;
            } finally {
                \ob_end_clean();
                \error_reporting($old_error_reporting);
            }
        });
        if ($err !== null) {
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
        $preg_pattern_callback = function (
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
            if ($pattern instanceof Node) {
                $pattern = (new ContextNode($code_base, $context, $pattern))->getEquivalentPHPScalarValue();
            }
            if (\is_string($pattern)) {
                $this->analyzePattern($code_base, $context, $function, $pattern);
            }
        };

        /**
         * @return void
         */
        $preg_pattern_or_array_callback = function (
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
            if ($pattern instanceof Node) {
                $pattern = (new ContextNode($code_base, $context, $pattern))->getEquivalentPHPValue();
            }
            if (\is_string($pattern)) {
                $this->analyzePattern($code_base, $context, $function, $pattern);
                return;
            }
            if (\is_array($pattern)) {
                foreach ($pattern as $child_pattern) {
                    if (\is_string($child_pattern)) {
                        $this->analyzePattern($code_base, $context, $function, $child_pattern);
                    }
                }
                return;
            }
        };

        /**
         * @return void
         */
        $preg_replace_callback_array_callback = function (
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
            if ($pattern instanceof Node) {
                $pattern = (new ContextNode($code_base, $context, $pattern))->getEquivalentPHPValue(self::RESOLVE_REGEX_KEY_FLAGS);
            }
            if (\is_array($pattern)) {
                foreach ($pattern as $child_pattern => $_) {
                    if (\is_scalar($child_pattern)) {
                        $this->analyzePattern($code_base, $context, $function, (string)$child_pattern);
                    }
                }
                return;
            }
        };

        // TODO: Check that the callbacks have the right signatures in another PR?
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
