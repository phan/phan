<?php

function maybeGenerate404Exception() : string {
    if (rand() % 2 > 0) {
        throw new RuntimeException("404 not available");
    } else {
        return "hello, world!";
    }
}

function serve404() {
    try {
        $response = maybeGenerate404Exception();
    } catch (Exception $e) {
        $msg = $response ?? $e->getMessage();
        echo $msg;
        echo intdiv($msg, 2);
    }
}
