<?php

function expects_nullable_int266(int $x = null) {
}

/** @param string|null $x */
function coalescingTest266($x) {
    expects_nullable_int266($x ?? "default"); // infer string
    expects_nullable_int266($x ?? null);      // infer string|null, conservatively avoid false positive
    expects_nullable_int266(null ?? null);    // infer null, avoid false positive
    expects_nullable_int266(null ?? $x);      // infer string|null, conservatively
}
