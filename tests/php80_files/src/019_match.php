<?php
$c = function() {
    return match(true) {
        default => $yes,
        default => $no,
    };
};
$c();
