<?php

declare(strict_types=1);

namespace Phan\Debug;

/**
 * A debugger used by the original Phan maintainers for debugging Phan.
 * (By manually inserting require_once '.../Breakpoint.php' into the code to be debugged?)
 * Currently unused.
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny
 */

/**
 * @return list<string>
 */

\readline_completion_function(static function (string $input): array {
    $matches = [];
    foreach (\get_declared_classes() as $class_name) {
        if (\strpos($class_name, $input) === 0) {
            $matches[] = $class_name;
        }
    }
    return $matches;
});

print "\n";
do {
    /** @var string|null */
    $input = \readline("breakpoint> ");

    if (\is_string($input)) {
        \readline_add_history($input);
    }

    if (\in_array($input, [
        'quit',
        'exit',
        'continue',
        'run',
        'c'
    ], true)) {
        break;
    }
    try {
        // @phan-suppress-next-line PhanPluginUnsafeEval this is only used ever manually for debugging
        eval($input . ';');
    } catch (\ParseError $exception) {
        print "Parse error in `$input`\n";
    } catch (\CompileError $exception) {
        print "Compile error in `$input`\n";
    } catch (\Throwable $exception) {
        print $exception->getMessage() . "\n";
        print $exception->getTraceAsString() . "\n";
    }
    print "\n";
} while (true);
