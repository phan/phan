<?php

function f146($x) {
    return var_export($x);
}
function f146b($x) {
    return var_export($x, false);
}
function f146c($x) {
    return var_export($x, true);
}
f146('x');
f146b('x');
f146c('x');
