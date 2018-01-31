<?php

function maybe_bool() : ?bool {
    switch (rand() % 3) {
    case 0: return true;
    case 1: return false;
    default: return null;
    }
}
/** @return array|bool */
function maybe_array() {
    switch (rand() % 3) {
    case 0: return true;
    case 1: return false;
    default: return [33];
    }
}
if (true) {}
if (FAlse) {}
if (PHP_VERSION_ID) {}
if (null) {
} else if ($x = maybe_bool()) {
} else if ($x = maybe_array()) {
}

if (maybe_bool() && (rand() % 2 > 0)) {
    echo "True";
}

if (true && maybe_array()) {
    echo "True";
}
