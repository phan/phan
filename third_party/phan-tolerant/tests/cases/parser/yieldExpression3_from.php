<?php

// Fails with the message "';' expected.", "Unexpected '=>'"
function gen() {
    yield from 1 => 2;
}