<?php
function testwg() {
    $GLOBALS['wgAutoloadClasses'] = [];
    $GLOBALS['wgAutoloadClasses'] += ['a','b'];
    echo "wgAutoloadClasses is " . json_encode($wgAutoloadClasses) . "\n";
}
function testwg2() {
    global $wgAutoloadClasses;
    echo spl_object_id($wgAutoloadClasses);
}
