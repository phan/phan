<?php
declare(strict_types=1);

namespace TestFieldAssertions;

/**
 * @param array<string,string|array> $options
 */
function foo(array $options)
{
    if (is_array($options['hooks'])) {
        echo strlen($options['hooks']);
        foreach ($options['hooks'] as $hook) {
            var_export($hook);
        }
    }
}

/**
 * @param array<string,string|array> $options
 */
function foo2(array $options)
{
    if (is_string($options['hooks'][0])) {
        echo intdiv($options['hooks'], 2);  // should infer string|array - both can have an integer field
    }
}

/**
 * @param array{hooks:string|array} $options
 */
function foo3(array $options)
{
    if (is_string($options['hooks'])) {
        echo intdiv($options['hooks'], 2);  // should infer string and warn
    } else {
        echo strlen($options['hooks']);  // should infer array and warn
    }
    echo strlen($options);  // should still infer array{hooks:string|array}
}
