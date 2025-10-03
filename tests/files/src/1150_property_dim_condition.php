<?php
class C1150 {
    private int $p;
    public function f(): void {
        if (is_array($this->p['x'])) {
            echo 'array';
        }
    }
}
