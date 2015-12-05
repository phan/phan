<?php
function do_something($var, callable $callback = NULL) {
        if ($callback) {
                    $callback(3);
                        }
}

do_something(3, function($callvar) {
            var_dump($callvar);
});
