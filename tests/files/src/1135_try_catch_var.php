<?php

function getObjectOrThrow(string $text): stdClass {
    throw new Exception($text);
}

(function () {
    $var = 'foo';
    try {
        $var = getObjectOrThrow($var);
    } catch (Exception) {
        // accessing $var as string should be allowed because assignment may not have happened
        throw new Exception(substr($var, 0, 10));
    }
})();
