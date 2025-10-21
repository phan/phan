<?php
function test(A&B&C $first, A&B &$second): A&B {
}

class E {
    public ArrayAccess&Countable $arrayLike;
    function invalid(): A& {
    }
}
