<?php
namespace NS802;

function recurs(...$ion) {
    implode(...$ion);
    min(...$ion);
    if (max(...$ion)) {
        recurs(...$ion);
    }
}
