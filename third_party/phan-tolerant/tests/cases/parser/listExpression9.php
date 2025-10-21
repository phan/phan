<?php
// TODO eventually throw error "Cannot use object of type A as array"
class A { }
list (list($d,)) = new A();