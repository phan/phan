<?php

declare(strict_types=1);

function getObjectMaybe(): ?stdClass
{
    return \mt_rand(0, 2) ? new stdClass() : null;
}

function problematic(): stdClass
{
    return ($obj = getObjectMaybe()) instanceof stdClass
        ? $obj // <--------- should not emit an error
        : new stdClass();
}
