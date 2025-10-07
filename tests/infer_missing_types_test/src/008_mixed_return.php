<?php
// Regression test for https://github.com/phan/phan/issues/4853
/**
 * @return mixed
 */
function returns_nullable_issue4853() {
    return null;
}

echo returns_nullable_issue4853();
