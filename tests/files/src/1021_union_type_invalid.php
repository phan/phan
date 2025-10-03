<?php
// Properly warn about invalid union type syntax in the polyfill
function test14(?int|false $invalid) {
}
test14('invalid');
