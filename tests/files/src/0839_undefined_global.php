<?php
assert($x instanceof \ArrayObject);
// type is undefined
'@phan-debug-var $x';
assert ($this instanceof \ArrayObject);
echo strlen($this);
// imitate #3616. Note that config settings for this test don't allow undeclared properties.
if (isset($this->prop)) {
}
echo "prop2 is $this->prop2\n";
