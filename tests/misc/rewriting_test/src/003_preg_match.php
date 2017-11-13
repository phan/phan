<?php
// This is a false positive that was worked around when simplify_ast was enabled.
// See https://github.com/phan/phan/issues/1066
// If the root cause is fixed even with simplify_ast disabled, move this to tests/files

if (!preg_match('/foo/', 'foobar', $matches))
{
    echo 'no matches';
}
else
{
    var_dump($matches);
    echo intdiv($matches, 2);  // Should warn, has array type
}
