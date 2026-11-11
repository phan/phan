<?php

/**
 * @require-extends Talker
 */
trait A {
    // Talker has a talk2() method, so this trait has a fake method by that name.
    // Ensure that it's not treated as a real method when the trait is adapted.
    public function talk() {
        echo 'A';
    }
}

class Talker {
    use A {
        A::talk2 as talk;
    }

    function talk2() {
        $this->talk();
    }
}
