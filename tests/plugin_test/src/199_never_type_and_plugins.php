<?php

function up(string $message): never {
    exit($message);
}
throw up('goodbye');
