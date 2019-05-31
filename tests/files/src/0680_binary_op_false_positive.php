<?php

function test680($x) {
    if (null !== $x) {
        $x .= 'suffix';
        echo count($x);  // should warn
    }
    // Should infer ?string
    echo strlen($x);
}
