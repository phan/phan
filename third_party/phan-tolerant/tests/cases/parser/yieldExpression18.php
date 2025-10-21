<?php

// This is invalid. But (yield) & ($x) would be valid in PHP 7.
function example($x) {
    yield & &$x;
}
