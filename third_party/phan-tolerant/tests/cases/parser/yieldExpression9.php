<?php

// TODO `return yield` should fail in php5
function gen() {
    return yield a();
}
