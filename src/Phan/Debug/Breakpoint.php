<?php declare(strict_types=1);
namespace Phan\Debug;

readline_completion_function(function ($input) {
    $matches = [];
    foreach (\get_declared_classes() as $className) {
        if (\strpos($className, $input) == 0) {
            $matches[] = $className;
        }
    }
    return $matches;
});

print "\n";
do {
    /** @var string|null */
    $input = readline("breakpoint> ");

    if (is_string($input)) {
        readline_add_history($input);
    }

    if (in_array($input, [
        'quit',
        'exit',
        'continue',
        'run',
        'c'
    ])) {
        break;
    }
    try {
        eval($input . ';');
    } catch (\ParseError $exception) {
        print "Parse error in `$input`\n";
    } catch (\Throwable $exception) {
        print $exception->getMessage() . "\n";
        print $exception->getTraceAsString() . "\n";
    }
    print "\n";
} while (true);
