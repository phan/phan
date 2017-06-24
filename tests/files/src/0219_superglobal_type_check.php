<?php
// Sanity checking phan's checks on the types of built in superglobals.
function superglobal_sanity_check(bool $exec = false) {
    global $argc, $argv;
    var_dump(strlen($_POST));
    var_dump(count($_POST));
    var_dump(count($_GET));
    var_dump(intdiv($argc, $argc + 1));
    var_dump(count($argv));
    var_dump(strlen($argv));
    var_dump(strlen($argv[0]));  // valid
    var_dump(intdiv($argv[0], 2));  // invalid
    var_dump(strlen($_COOKIE));  // invalid
    var_dump(count($_COOKIE));
    var_dump(strlen($_REQUEST));  // invalid
    var_dump(count($_REQUEST));
    $_SERVER = 'foo';  // Invalid for the purpose of type checking, but still valid php.
    var_dump(strlen($_SERVER));  // normally invalid, but valid here?
    var_dump(count($_SERVER));  // invalid because it's now a string.
    var_dump(strlen($_ENV));
    var_dump(count($_ENV));
    var_dump(count($_ENV['HOME'])); // invalid, this is a string

    var_dump(strlen($_ENV['HOME'])); // valid


    var_dump(strlen($GLOBALS)); // invalid
    var_dump(count($GLOBALS)); // valid

    // https://secure.php.net/manual/en/features.file-upload.post-method.php
    // https://secure.php.net/manual/en/features.file-upload.multiple.php - Can have array of files with the same label.
    var_dump(strlen($_FILES['foo.txt']['name'][0])); // Possibly valid
    var_dump(strlen($_FILES['bar.txt']['name'])); // Also possibly valid
    var_dump(strlen($_FILES['bar.txt'])); // Invalid
    var_dump(intdiv($_FILES['foo.txt']['size'][0], 1000)); // Possibly valid. We don't actually process the file size
    var_dump(intdiv($_FILES['bar.txt']['size'], 1000)); // Also possibly valid
    var_dump(intdiv($_FILES['bar.txt'], 1000)); // Invalid
    if ($exec) {
        file_get_contents('http://127.0.0.1:1234');
        var_export(strlen($http_response_header));  // invalid
        var_export(strlen($http_response_header[0]));  // valid
        var_export(count($http_response_header));  // Valid
        $http_response_headers = null;  // Valid - Want to assert that http_response_headers being null is a valid state.
    }
}
superglobal_sanity_check();

function builtin_global_sanity_check() {
    var_dump(intdiv($argc, 1));  // emits warning about undefined $argc
    var_dump(count($argv));  // emits warning about undefined $argv
}
builtin_global_sanity_check();
