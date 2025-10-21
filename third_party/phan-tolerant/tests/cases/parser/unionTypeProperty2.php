<?php
class Xyz {
    // Should not crash
    public int|string function test () {
    }
    public int|string| $prop = 'default';
}
