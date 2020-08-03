<?php
define('B191', rand(0, 1));
function main191() {
    while (B191) { echo "."; }  // should warn
    while (!B191) { echo "x"; }  // should warn
}
main191();
