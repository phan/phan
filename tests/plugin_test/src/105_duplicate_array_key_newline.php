<?php
/**
 * @suppress PhanUnreferencedFunction
 */
function handle105($str) {
    switch($str) {
        case "help\n":
            echo 'extra line';
            break;
        case 'h':
            echo 'short form';
            break;
        case "help\n":
            echo 'extra line';
            break;
        default:
            echo 'unknown';
            break;
    }
}
return [
    "a\na" => 1,
    "a\nb" => 2,
    "a\nb" => 3,
];
