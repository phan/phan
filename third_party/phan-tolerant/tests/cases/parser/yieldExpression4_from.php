<?php

// Fails with the messages "';' expected.", "Unexpected '=>'"
function gen() {
    yield from $i => $a;
}