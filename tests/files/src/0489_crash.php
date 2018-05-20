<?php
// Regression test for #1699
function x() {
    if (rand() % 2) {
        ${42} = 24;
    }
    echo count(${42});
}
