<?php
function test757(array $additional) {
    if (empty($additional['product']['comment'])) {
        // Should not warn
        echo $additional['product']['listprice'] . "\n";
    }
    if (empty($additional['product'])) {

        // should warn
        echo $additional['product']['listprice'] . "\n";
    }
}
/**
 * @param int $x
 */
function test757b($x) {
    if (empty($x)) {
        if (is_object($x)) {
            echo "Impossible\n";
        } elseif (is_array($x)) {
            echo "The empty array\n";
        } elseif (is_resource($x)) {
            echo "Impossible\n";
        }
    }
}
