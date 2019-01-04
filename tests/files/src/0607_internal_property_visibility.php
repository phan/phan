<?php

class MyException extends Exception {
    protected $message = 'default';
    private $code = 7;  // not visible enough
}
