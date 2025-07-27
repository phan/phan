<?php

function inconsistentExit(string $val): never {
    if ($val === "yes") {
        exit();
    }
}

inconsistentExit("yes");
