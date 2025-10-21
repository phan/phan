<?php
class X {
    // Starting in php 8.1, it is a compile error rather than a syntax error to have more than one visibility modifier
    public function __construct(
        public protected $var
    ) {}
}
