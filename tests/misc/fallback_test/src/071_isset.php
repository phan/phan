<?php
// https://github.com/phan/phan/issues/3767
function test71() {
    return isset ? 2 : 3;
}
