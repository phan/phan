<?php

// Completely wrong, should warn.
function test363() : int {
    return ast\parse_code(22, []);
}
