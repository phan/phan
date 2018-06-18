<?php

// Phan warns about it, but don't use 'void' as a class name.
function returns_void() : void {
    echo "This behaves differently in php 7.0";
}
returns_void();
