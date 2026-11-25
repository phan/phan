<?php

namespace NS5980;

/** A null offset that no array shape has is checked against the keys of the generic arrays */
function nullOffset(string $s): bool {
    $d = ['x' => true];
    $d[$s] = true;
    $k = null;
    return $d[$k];
}

/** The key '' is the key that php uses for null */
function nullOffsetOfEmptyKey(string $s): bool {
    $d = ['' => true];
    $d[$s] = true;
    $k = null;
    return $d[$k];
}

/**
 * !isset() of a key that no array shape has does not add the key as null
 * @param string[] $blog
 */
function negatedIsset(array $blog): int {
    $blog['domain'] = 'x';
    if (!isset($blog['email'])) {
        '@phan-debug-var $blog';
        echo "unset";
    }
    '@phan-debug-var $blog';
    return strlen($blog['email']);
}

/**
 * @param array<string,string> $a
 */
function issetOfVariableKey(array $a, string $key): int {
    $a['domain'] = 'x';
    if (!isset($a['first_name'])) {
        echo "unset";
    }
    if (isset($a[$key])) {
        return strlen($a[$key]);
    }
    return 0;
}

/**
 * !isset() of a key that an array shape has still makes the field null
 * @param array{email?:string} $blog
 * @param string[] $other
 */
function negatedIssetOfField(array $blog, array $other, bool $c): void {
    if ($c) {
        $blog = $other;
        $blog['domain'] = 'x';
    }
    if (!isset($blog['email'])) {
        '@phan-debug-var $blog';
    }
}

/**
 * !array_key_exists() of a key that no array shape has
 * @param string[] $blog
 */
function negatedArrayKeyExists(array $blog): int {
    $blog['domain'] = 'x';
    if (!array_key_exists('email', $blog)) {
        '@phan-debug-var $blog';
        return 0;
    }
    return strlen($blog['email']);
}
