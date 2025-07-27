<?php

function doExit(): never {
    exit();
}

function indirectExit(): never {
    doExit();
}

indirectExit();
