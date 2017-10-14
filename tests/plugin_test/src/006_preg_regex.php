<?php
$x = 'some value with foo';
if (preg_match('@foo', $x) > 0) {
    echo "Matched";
}
echo preg_replace('@foo(@', 'foobar', $x);
preg_match('/\w\+/', '  words', $matches);
var_export($matches);

echo preg_replace_callback_array(['@a@' => function($x) { return 'i'; }], 'bad');
echo preg_replace_callback_array(['@a' => function($x) { return 'i'; }], 'bad');
echo preg_replace_callback('@a@', function($x) { return 'i'; }, 'bad');
echo preg_replace_callback('^a', function($x) { return 'i'; }, 'bad');
var_export(preg_split('/\s//', 'word another word'));
var_export(preg_match_all('/\w/i/', 'word another word'));
var_export(preg_grep('/\w/i/', ['word another word', '[]']));
