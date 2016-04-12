<?php
class A {
}
$cl1 = function() {
};

$bcl1 = Closure::bind($cl1, null, 'A');
