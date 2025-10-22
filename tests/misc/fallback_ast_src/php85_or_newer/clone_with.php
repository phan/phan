<?php

class ClonableExample {
    public int $prop = 0;
}

$object = new ClonableExample();

clone($object, ['prop' => 42]);
