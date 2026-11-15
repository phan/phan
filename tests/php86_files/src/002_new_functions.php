<?php

namespace NS86Functions;

use ReflectionProperty;
use SortDirection;
use Time\Duration;

// Functions, methods and classes added in PHP 8.6.
// Only extensions that are always enabled are used here (not intl, gmp or openssl).

function test(int $i, float $f, string $s): void
{
    // clamp() returns one of its arguments
    echo strlen(clamp($i, 0, 10));
    echo strlen(clamp($f, 0.0, 1.0));
    echo count(clamp($s, 'a', 'z'));
    echo clamp($i);

    // list<StreamError>
    echo strlen(stream_last_errors());
    stream_clear_errors();

    echo strlen((new ReflectionProperty(\Exception::class, 'message'))->isReadable(null));

    // Classes that Phan only knows from Reflection: the declared return types are used.
    echo strlen(Duration::fromSeconds(5));
    echo strlen(Duration::fromSeconds(5)->negate());
    echo strlen(SortDirection::Ascending);

    // Changed signature: new optional parameter
    echo strlen(stream_is_local($s, null));
}
test(1, 1.5, 'x');
