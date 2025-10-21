<?php

// should fail
function gen() {
    yield from &$a;
}
