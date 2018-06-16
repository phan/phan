<?php

try {
    throw new \Exception();
} catch (\Exception | \Throwable $e) {
    throw $e;
}