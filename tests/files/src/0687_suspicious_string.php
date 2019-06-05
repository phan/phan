<?php
namespace NS681;

class NotStringable {
    public function test() {
        echo "Foo$this\n";
        echo $this;
    }
}
class Stringable {
    public function test() {
        echo "$this\n";
        echo $this;
        unset($this['field']);
    }
    public function __toString() {
        return 'stringified';
    }
}
