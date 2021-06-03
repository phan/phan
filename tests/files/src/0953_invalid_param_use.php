<?php
class AnonymousFunctionUsingThis {
    public function testInvalidUse() {
        return ['func'=>static function() use ($this, $_GET) { return $this; }];
    }
    public function testInvalidParam() {
        return ['func'=>static function($this, $_GET) { return $this; }];
    }
}
