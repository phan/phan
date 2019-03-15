<?php

/**
 * @suppress Unused-Issue-In-Config
 */
function test103a() {
    echo "Hello, ";
}

/**
 * @suppress Unused-Issue-Not-In-Config
 */
function test103b() {
    echo "World!";
}
test103a();
test103b();
