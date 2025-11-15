<?php
class GP {
    /** @return array */
    public function foo() {
        return [];
    }
}
class P extends GP {
    public function foo(): string {
        return 'foo';
    }
}
class C extends P {
    public function foo(): string {
        return 'bar';
    }
}
