<?php
echo preg_replace('/foo/i', '\{0}t', 'fooball') . "\n";  // NOTE: This is not a template
echo preg_replace('/foo/i', '${0}t', 'fooball') . "\n";
echo preg_replace('/foo/i', '\{00}t', 'fooball') . "\n";  // Not a template
echo preg_replace('/foo/i', '${00}t', 'fooball') . "\n";
echo preg_replace('/foo/i', '\{1}t', 'fooball') . "\n";  // Not a template, don't warn about missing reference
echo preg_replace('/foo/i', '${1}t', 'fooball') . "\n";
echo preg_replace('/foo/i', '\{01}t', 'fooball') . "\n";  // Not a template
echo preg_replace('/foo/i', '${01}t', 'fooball') . "\n";
echo preg_replace('/foo/i', '\{001}t', 'fooball') . "\n";  // Not a template
echo preg_replace('/foo/i', '${001}t', 'fooball') . "\n";  // Also not a template

echo preg_replace('/foo/i', '${99}', 'fooball') . "\n";

echo preg_replace('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)/i', '${11}', 'abcdefgHIJKLMNOP') . "\n";
echo preg_replace('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)/i', '${12}', 'abcdefgHIJKLMNOP') . "\n";  // should warn
echo preg_replace('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)/i', '$11', 'abcdefgHIJKLMNOP') . "\n";
echo preg_replace('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)/i', '$12', 'abcdefgHIJKLMNOP') . "\n";  // should warn
echo preg_replace('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)/i', '$111', 'abcdefgHIJKLMNOP') . "\n";
echo preg_replace('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)/i', '$121', 'abcdefgHIJKLMNOP') . "\n";  // should warn

echo preg_replace('/foo/i', '${', 'fooball') . "\n";  // not a template
echo preg_replace('/foo/i', '${}', 'fooball') . "\n";  // not a template
echo preg_replace('/foo/i', '\\\\1', 'fooball') . "\n";  // not a template
echo preg_replace('/foo/i', '\\\\\1', 'fooball') . "\n";  // a template
