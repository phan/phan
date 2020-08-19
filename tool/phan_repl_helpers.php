<?php

declare(strict_types=1);

use Phan\CLI;
use Phan\Language\Element\Comment;
use Phan\Language\Element\MarkupDescription;
use Phan\Library\StringUtil;

// On the off chance that php or an extension ever provides a global function called 'help',
// check for this so that other utilities will work.
if (!function_exists('help')) {

/**
 * tool/phan_repl_helpers.php is a utility that can be loaded after `php -a` is started.
 *
 * It provides the following:
 * - A prototype replacement for PHP's code completion, on platforms where readline was installed.
 *   **This does not take advantage of Phan's inference and only reads the last line of multi-line expressions/statements.**
 * - A prototype global function `help()` which will dump information about constants/functions/objects/classes.
 *
 *   The format of this will probably change.
 * - Access to an environment where Phan's bootstrapping and the project's autoloader already ran and Phan's classes can be autoloaded.
 *
 * Examples of how this can be loaded and used from a PHP shell:
 *
 * ```
 * php > require_once 'tool/phan_repl_helpers.php';
 *
 * php > help(\Phan\CLI::class);
 * Help for class Phan\CLI defined at /path/to/phan/src/Phan/CLI.php:79.
 *
 * Contains methods for parsing CLI arguments to Phan,
 * outputting to the CLI, as well as helper methods to retrieve files/folders
 * for the analyzed project.
 *
 * php > help('ast\AST_BINARY_OP');
 * Help for global constant ast\AST_BINARY_OP
 *
 * Value: 520
 *
 * A binary operation of the form `left op right`.
 * The operation is determined by the flags `ast\flags\BINARY_*`
 * (children: left, right)
 * ```
 *
 * tool/phan_repl_helpers.php also replaces the code completion capabilities
 * of `php -a` with an alternative with a different feature set.
 *
 * ```
 * php > require_once 'tool/phan_repl_helpers.php';
 * php > $object = new ArrayObject();
 * php > help($object);
 * Help for class ArrayObject defined by module SPL.
 *
 * This class allows objects to work as arrays.
 *
 * php > $object->a<TAB>
 * append  asort
 * ```
 *
 * @suppress PhanUnreferencedFunction this is meant to be used interactively and is currently untested
 */
    function help($value = "\x00extended_help"): void
    {
        phan_repl_help($value);
    }
} /* End function_exists('help') check */

/**
 * Actual implementation of help()
 */
function phan_repl_help($value = "\x00extended_help"): void
{
    if ($value === "\x00extended_help") {
        echo "Phan " . CLI::PHAN_VERSION . " CLI autocompletion utilities.\n";
        echo "Type help(\$value); or help('function or constant or class name'); for help.\n";
        echo "Type help('help'); for extended help.\n";
        return;
    }
    if ($value instanceof Closure || (is_string($value) && function_exists($value)) || $value instanceof ReflectionFunction) {
        // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal not sure why
        $reflection_function = $value instanceof ReflectionFunction ? $value : new ReflectionFunction($value);
        $function_name = $reflection_function->getName();
        $doc_comment = $reflection_function->getDocComment();
        if ($reflection_function->isUserDefined()) {
            $details = 'defined at ' . $reflection_function->getFileName() . ':' . $reflection_function->getStartLine();
        } else {
            $details = 'defined by module ' . $reflection_function->getExtensionName();
        }
        echo "Help for function $function_name $details.\n\n";
        // TODO: Use Phan's stub generation code and handle any issues caused by inheritance?
        // echo "$reflection_function\n";
        $description = '';
        if (is_string($doc_comment)) {
            $description = MarkupDescription::extractDocComment($doc_comment, Comment::ON_FUNCTION);
        }
        if (strlen($description) > 0) {
            echo rtrim($description) . "\n\n";
            return;
        }
        $function_documentation = MarkupDescription::loadFunctionDescriptionMap()[strtolower($function_name)] ?? '';
        if ($function_documentation !== '') {
            echo rtrim($function_documentation) . "\n\n";
            return;
        }
        echo "Could not find info on $function_name\n\n";
        return;
    }
    if (is_object($value) || (is_string($value) && (class_exists($value) || trait_exists($value) || interface_exists($value)))) {
        $class_name = is_string($value) ? $value : get_class($value);
        $reflection_class = new ReflectionClass($class_name);
        $class_name = ltrim($class_name, '\\');
        if ($reflection_class->isUserDefined()) {
            $details = 'defined at ' . $reflection_class->getFileName() . ':' . $reflection_class->getStartLine();
        } else {
            $details = 'defined by module ' . $reflection_class->getExtensionName();
        }
        echo "Help for class $class_name $details.\n\n";
        $doc_comment = $reflection_class->getDocComment();
        $description = '';
        if (is_string($doc_comment)) {
            $description = MarkupDescription::extractDocComment($doc_comment, Comment::ON_CLASS);
        }
        if (strlen($description) > 0) {
            echo rtrim($description) . "\n\n";
            return;
        }
        $class_documentation = MarkupDescription::loadClassDescriptionMap()[strtolower(ltrim($class_name, '\\'))] ?? '';
        if ($class_documentation !== '') {
            echo rtrim($class_documentation) . "\n\n";
            return;
        }
        echo "Could not find info on $class_name\n\n";
        return;
    }
    if (is_string($value) && defined($value)) {
        // TODO: Make this properly case sensitive for names but not namespaces
        // TODO: Support class constants
        echo "Help for global constant $value\n\n";
        echo "Value: " . StringUtil::jsonEncode(constant($value)) . "\n\n";
        $constant_documentation = MarkupDescription::loadConstantDescriptionMap()[strtolower($value)] ?? '';
        if ($constant_documentation !== '') {
            echo rtrim($constant_documentation) . "\n\n";
            return;
        }
        echo "Could not find info on $value\n\n";
        return;
    }
    echo "Unknown value for help(). Value was:\n";
    var_dump($value);
}

/**
 * TODOs:
 * - Take advantage of Phan's static analysis compatibilities for generating
 *   readline suggestions.
 *   Currently, this only reads the last 3 tokens and doesn't take advantage of Phan's inference.
 * - Support help() for remaining element types
 * - Look into alternative approaches
 * - Look into ways to get the previous lines contents when the expression/statement to be evaluated contains newlines.
 * - Integrate with other tools such as tool/phoogle to create a useful debugging environment
 */

// Currently used for signature info
require_once(__DIR__ . '/../src/Phan/Bootstrap.php');

// Phan's error handler terminates the process when there's an unexpected notice. This isn't helpful in an interactive shell.
restore_error_handler();

/**
 * Utilities such as completions to be added to `php -a` after launching it.
 *
 * This is written as a class with public/protected methods to make it easier to extend or to unit test.
 *
 * TODO: When possible, take advantage of the code that already exists
 * in Phan to generate completion for files, keywords, etc.
 *
 * TODO: Add unit tests
 * @phan-file-suppress PhanPluginRemoveDebugAny this is a debugging utility
 * @phan-file-suppress PhanAccessMethodInternal this is bundled with phan
 */
class PhanPhpShellUtils
{
    /** @var bool whether to emit debugging code */
    private $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Append a line to a logging file
     */
    public function appendToLogFile(string $line): void
    {
        if (!$this->debug) {
            return;
        }
        @file_put_contents('/tmp/phan_repl_helpers.php', $line, FILE_APPEND);
    }

    /**
     * Generate completions for the current token
     *
     * @param list<int|string> $candidates
     * @return list<string>
     */
    public function generateCompletionsFromCandidates(array $candidates, string $prefix, string $prefix_to_add_to_completion): array
    {
        $prefix_len = strlen($prefix);
        $completions = [];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $candidate_len = strlen($candidate);
            if ($candidate_len >= $prefix_len && strncmp($prefix, $candidate, $prefix_len) === 0) {
                $completions[] = $prefix_to_add_to_completion . $candidate;
            }
        }
        return $completions;
    }

    /**
     * Generate completions for a variable. TODO: Account for local variables
     * @return list<string>
     */
    public function generateVariableCompletions(string $last_token_str): array
    {
        $prefix = ltrim($last_token_str, '${');
        $keys = array_keys($GLOBALS);
        $keys[] = 'GLOBALS';
        $completions = self::generateCompletionsFromCandidates($keys, $prefix, '$');
        $this->appendToLogFile("generateVariableCompletions for $last_token_str = " . StringUtil::jsonEncode($completions) . "\n");

        return $completions;
    }

    /**
     * Convert a token to a string
     * @param array{0:int,1:string,2:int}|string|false $token
     */
    public static function tokenToString($token): string
    {
        return is_array($token) ? $token[1] : (string)$token;
    }

    /**
     * Generate completions for accessing instance property or methods where the object instance is known
     *
     * @return list<string>
     * @suppress PhanCompatibleObjectTypePHP71
     */
    public function generateCompletionsForInstancePropertyOfObject(object $object, string $instance_element_prefix): array
    {
        // Gets the accessible non-static properties of the given object according to scope.
        $property_candidates = array_keys(get_object_vars($object));
        $property_completions = self::generateCompletionsFromCandidates($property_candidates, $instance_element_prefix, '');
        $reflection_object = new ReflectionClass($object);
        $method_candidates = [];
        foreach ($reflection_object->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $method_candidates[] = $method->getName(); //  . '('; seems to cause extra whitespace to get added
        }
        $method_completions = self::generateCompletionsFromCandidates($method_candidates, $instance_element_prefix, '');
        $completions = array_merge($property_completions, $method_completions);
        if ($method_completions && !$property_completions) {
            $this->setReadlineConfig('completion_append_character', "(");
        }
        $this->appendToLogFile("generateCompletionsForInstancePropertyOfObject completions = " . StringUtil::jsonEncode($completions) . "\n");
        return $completions;
    }

    /**
     * Generate completions for accessing instance property or methods ($obj->prefix)
     *
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     * @return list<string>
     */
    public function generateInstanceObjectCompletions(array $tokens): array
    {
        $i = count($tokens) - 1;
        $this->appendToLogFile("generateInstanceObjectCompletions tokens = " . StringUtil::jsonEncode($tokens) . "\n");
        while (!is_array($tokens[$i]) || $tokens[$i][0] !== T_OBJECT_OPERATOR) {
            $i--;
            if ($i <= 0) {
                return [];
            }
        }
        $instance_element_prefix = self::tokenToString($tokens[$i + 1] ?? '');
        // Not definitely the expression - tolerant-php-parser would be a better way to fetch this.
        $expression = $tokens[$i - 1];
        $expression_str = self::tokenToString($expression);
        if (is_array($expression) && $expression[0] === T_VARIABLE) {
            $var_name = substr($expression_str, 1);
            $global_var = $GLOBALS[$var_name] ?? null;
            if (!is_object($global_var)) {
                return [];
            }
            return $this->generateCompletionsForInstancePropertyOfObject($global_var, $instance_element_prefix);
        }
        $this->appendToLogFile("instance_element_prefix = '$instance_element_prefix' expression=$expression_str\n");
        return [];
    }

    /**
     * Generate completions for SomeClass::$prefix or SomeClass::prefix
     * @return list<string>
     */
    public function generateStaticElementSuggestionsForClass(string $class, string $instance_element_prefix): array
    {
        // TODO support ::class
        if (!class_exists($class)) {
            return [];
        }
        $reflection_class = new ReflectionClass($class);
        $property_completions = [];
        if (($instance_element_prefix[0] ?? '$') === '$') {
            // Generate completions for static properties
            $property_candidates = [];
            foreach ($reflection_class->getProperties(ReflectionProperty::IS_STATIC | ReflectionProperty::IS_PUBLIC) as $prop) {
                $property_candidates[] = '$' . $prop->getName();
            }
            $property_completions = $this->generateCompletionsFromCandidates($property_candidates, $instance_element_prefix, '');
            if ($instance_element_prefix !== '') {
                return $property_completions;
            }
        }
        // TODO: PHP adds filtering by ReflectionClassConstant::IS_PUBLIC in 8.0
        // TODO: Make some of these case insensitive?

        $constant_candidates = ['class'];
        foreach ($reflection_class->getReflectionConstants() as $reflection_constant) {
            if (!$reflection_constant->isPublic()) {
                continue;
            }
            $constant_candidates[] = $reflection_constant->getName();
        }
        $constant_completions = $this->generateCompletionsFromCandidates($constant_candidates, $instance_element_prefix, '');

        $method_candidates = [];
        foreach ($reflection_class->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $reflection_method) {
            if (!$reflection_method->isPublic()) {
                continue;
            }
            $method_candidates[] = $reflection_method->getName();
        }
        $method_completions = $this->generateCompletionsFromCandidates($method_candidates, $instance_element_prefix, '');

        return array_merge(
            $property_completions,
            $constant_completions,
            $method_completions
        );
    }

    /**
     * Generate completions for accessing class constants, static properties or methods ($obj::prefix)
     *
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     * @param string $completed_text this is the `Foo::prefix` that returned values need to begin with
     * @return list<string>
     */
    public function generateStaticObjectCompletions(array $tokens, string $completed_text): array
    {
        $i = count($tokens) - 1;
        $this->appendToLogFile("generateStaticObjectCompletions tokens = " . StringUtil::jsonEncode($tokens) . "\n");
        while (!is_array($tokens[$i]) || $tokens[$i][0] !== T_DOUBLE_COLON) {
            $i--;
            if ($i <= 0) {
                return [];
            }
        }
        $instance_element_prefix = self::tokenToString($tokens[$i + 1] ?? '');
        // Not definitely the expression - tolerant-php-parser would be a better way to fetch this.
        $expression = $tokens[$i - 1];
        $expression_str = self::tokenToString($expression);
        if (is_array($expression) && $expression[0] === T_STRING) {
            // TODO: Check if this snippet is within a namespace block with uses, etc.
            // Or just reuse Phan's real completion abilities.
            $class_name = $expression[1];
            $pos = strrpos($completed_text, '::');
            if (!is_int($pos)) {
                return [];
            }
            $new_prefix = substr($completed_text, 0, $pos + 2);
            $completions = [];
            foreach ($this->generateStaticElementSuggestionsForClass($class_name, $instance_element_prefix) as $element_name) {
                $completions[] = $new_prefix . $element_name;
            }
            return $completions;
        }
        $this->appendToLogFile("instance_element_prefix = '$instance_element_prefix' expression=$expression_str\n");
        return [];
    }

    /**
     * @return list<string> a list of completions for a generic identifier
     */
    public function generateCompletionsForGlobalName(string $prefix): array
    {
        $function_candidates = array_values(array_merge(...array_values(get_defined_functions(true))));
        $function_completions = $this->generateCompletionsFromCandidates($function_candidates, $prefix, '');
        // @phan-suppress-next-line PhanRedundantArrayValuesCall
        $other_candidates = array_values(array_merge(
            get_declared_classes(),
            get_declared_traits(),
            get_declared_interfaces(),
            array_keys(get_defined_constants())
        ));
        $other_completions = $this->generateCompletionsFromCandidates($other_candidates, $prefix, '');
        if ($function_completions && !$other_completions) {
            $this->setReadlineConfig('completion_append_character', "(");
        }
        $result = array_merge($function_completions, $other_completions);
        $prefix_len = strlen($prefix);
        foreach ($result as &$val) {
            $i = strrpos(substr($val, 0, $prefix_len), '\\');
            if ($i !== false) {
                $val = substr($val, $i + 1);
            }
        }
        return $result;
    }

    /**
     * @param string|bool|int $value
     */
    protected function setReadlineConfig(string $key, $value): void
    {
        readline_info($key, $value);
    }

    /** Workaround to make readline not print any suggestions. Not sure how if this will work on all versions. */
    public const NO_AVAILABLE_COMPLETIONS = [''];

    /**
     * Generate completion for any token
     * @return list<string>
     */
    public function generateCompletions(string $text, int $start, int $end): array
    {
        $this->setReadlineConfig('completion_append_character', "\x00");
        try {
            // TODO: PHP's API only allows us to fetch the most recent line.
            $line_buffer = readline_info('line_buffer');
            $tokens = (@token_get_all('<' . '?php ' . $line_buffer)) ?: [''];  // Split up to fix vim syntax highlighting.
            $last_token = end($tokens);
            $last_token_str = self::tokenToString($last_token);
            $this->appendToLogFile("text='''$text''' start=$start end=$end line_buffer='''$line_buffer''' last_token_str='''$last_token_str'''\n");
            $c = $last_token_str[0] ?? '';
            $prev_token = prev($tokens);
            $prev_token_str = self::tokenToString($prev_token);
            if ($last_token_str === '::' || $prev_token_str === '::') {
                // Complete static members
                // (Must check if this is completing a static property instead of a variable)
                return $this->generateStaticObjectCompletions($tokens, $text) ?: self::NO_AVAILABLE_COMPLETIONS;
            } elseif ($c === '$') {
                return $this->generateVariableCompletions($last_token_str) ?: self::NO_AVAILABLE_COMPLETIONS;
            } elseif ($last_token_str === '->' || $prev_token_str === '->') {
                // TODO: Actually infer types for expressions other than variables
                return $this->generateInstanceObjectCompletions($tokens) ?: self::NO_AVAILABLE_COMPLETIONS;
            }
            if ($last_token_str === '\\') {
                if (is_array($prev_token)) {
                    $prev_token_kind = $prev_token[0];
                    // TODO: T_NAME_RELATIVE for namespace\
                    // @phan-suppress-next-line PhanUndeclaredConstant
                    if ($prev_token_kind === T_STRING || PHP_VERSION_ID >= 80000 && in_array($prev_token_kind, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $last_token_str = ltrim($prev_token[1], '\\') . $last_token_str;
                    }
                }
            }

            // TODO: Handle completions when the text is incorrectly tokenized (e.g. 'ast\parse_')
            // That would benefit from using tolerant-php-parser to identify identifiers that contain multiple tokens (e.g. `$x = ast\parse_<TAB>`)
            // Alternately, just look for T_STRING and T_BACKSLASH and T_WHITESPACE combinations
            return $this->generateCompletionsForGlobalName($last_token_str) ?: self::NO_AVAILABLE_COMPLETIONS;
            // TODO: $c === '#' for ini completions for ini_set().
        } catch (Throwable $e) {
            $this->appendToLogFile("Caught $e");
        }
        return self::NO_AVAILABLE_COMPLETIONS;
    }
}

if (function_exists('readline_completion_function')) {
    readline_completion_function([new PhanPhpShellUtils(true), 'generateCompletions']);
} else {
    echo __FILE__ . "could not install a readline_completion_function - the readline extension is unavailable\n";
}
