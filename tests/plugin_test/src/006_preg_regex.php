<?php
$x = 'some value with foo';
if (preg_match('@foo', $x) > 0) {
    echo "Matched";
}
echo preg_replace('@foo(@', 'foobar', $x);
preg_match('/\w\+/', '  words', $matches);
var_export($matches);

/** @suppress PhanUnreferencedClosure */
call_user_func(function() {
    echo preg_replace_callback_array(['@a@' => function($x) { return 'i'; }], 'bad');
    echo preg_replace_callback_array(['@a' => function($x) { return 'i'; }], 'bad');
    echo preg_replace_callback('@a@', function($x) { return 'i'; }, 'bad');
    echo preg_replace_callback('^a', function($x) { return 'i'; }, 'bad');
    var_export(preg_split('/\s//', 'word another word'));
    var_export(preg_match_all('/\w/i/', 'word another word'));
    var_export(preg_grep('/\w/i/', ['word another word', '[]']));

    // test invalid inputs
    echo preg_replace_callback_array([function($x) { return 'i'; }], 'bad');
    echo preg_replace_callback_array([false => function($x) { return 'i'; }], 'bad');

    $str = 'arg';
    // Test that this plugin warns and the regex analyzer does not crash
    if (preg_match('/^(a/', $str, $matches) > 0) {
        var_export($matches);
    }
    if (preg_match('/^(a/', $str, $matches, PREG_OFFSET_CAPTURE) > 0) {
        var_export($matches);
    }
});
