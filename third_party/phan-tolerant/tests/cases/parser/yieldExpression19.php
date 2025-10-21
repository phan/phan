<?php

// This is parsed as (yield) && ($x).
function example($x) {
    yield &&$x;
}
