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
        echo intdiv($options['hooks'], 2);  // TODO(optional): could infer string|array|array{0:string} instead. - both can have an integer field. This isn't a common edge case in practice.
    }
}

/**
 * @param array{hooks:string|array} $options
 */
function foo3(array $options)
{
    if (is_string($options['hooks'])) {
        '@phan-debug-var $options';  // TODO: More precise
        echo intdiv($options['hooks'], 2);  // should infer string and warn
    } else {
        echo strlen($options['hooks']);  // should infer array and warn
        '@phan-debug-var $options';
    }
    echo strlen($options);  // should still infer array{hooks:string|array}
}
