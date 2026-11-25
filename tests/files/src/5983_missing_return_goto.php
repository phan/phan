<?php

namespace NS5983;

// Every path throws or exits: no missing return
function neverWithGoto(bool $x): never {
    if ($x) {
        goto done;
    }
    exit(1);
    done:
    throw new \RuntimeException();
}

function intWithGoto(bool $x): int {
    if ($x) {
        goto done;
    }
    exit(1);
    done:
    throw new \RuntimeException();
}

class C {
    public function intWithGoto(bool $x): int {
        if ($x) {
            goto done;
        }
        exit(1);
        done:
        throw new \RuntimeException();
    }
}

$c = function (bool $x): int {
    if ($x) {
        goto done;
    }
    exit(1);
    done:
    throw new \RuntimeException();
};

// The code after the label reaches the end of the function
function fallsOffAfterLabel(bool $x): int {
    if ($x) {
        goto done;
    }
    exit(1);
    done:
    echo "done\n";
}

function neverFallsOffAfterLabel(bool $x): never {
    if ($x) {
        goto done;
    }
    exit(1);
    done:
}
