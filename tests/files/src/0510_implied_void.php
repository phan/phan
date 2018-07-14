<?php

class X510 {
    // This is private and has no return statements, so Phan infers that it is guaranteed to be a void.
    private function returnsVoid() {
        echo "This does something";
    }

    public function other() {
        echo $this->returnsVoid();
    }
}
