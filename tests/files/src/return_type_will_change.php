<?php

class IteratorWithAttribute extends ArrayIterator {
    #[\ReturnTypeWillChange]
    public function key() {
        return parent::key();
    }
}

class IteratorWithoutAttribute extends ArrayIterator {
    public function key() {
        return parent::key();
    }
}
