<?php

function accepts_cast_argument(string $x) {
    var_export($x);
}

call_user_func(
    /** @param mixed $x */
    function ($x) {
        accepts_cast_argument(
            (array
        ) $x;
        accepts_cast_argument(
            (binary
        ) $x;
        accepts_cast_argument(
            (bool
        ) $x;
        accepts_cast_argument(
            (boolean
        ) $x;
        accepts_cast_argument(
            (double
        ) $x;
        accepts_cast_argument(
            (int
        ) $x;
        accepts_cast_argument(
            (integer
        ) $x;
        accepts_cast_argument(
            (float
        ) $x;
        accepts_cast_argument(
            (object
        ) $x;
        accepts_cast_argument(
            (real
        );
        accepts_cast_argument(
            (string
        ) $x;
        accepts_cast_argument(
            (unset
        ) $x;
    }
);
