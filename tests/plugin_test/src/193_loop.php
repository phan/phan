<?php
function test193() {
    $loop = true;
    for ($i = 0; $placeholder, $loop; $i++) {
        $loop = $i < 10;
    }
}
function test193b() {
    $loop = true;
    for ($i = 0; $loop; $i++) {
        $loop = $i < 10;
    }
}
test193();
test193b();
