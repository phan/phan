<?php

abstract class VerificationBase419 {
    public function __construct($method) {
    }

    const EMAIL = 'email';

    abstract protected function _getMethod();

    protected function _shouldVerify($uid){
    }
}
class VerificationEmail419 extends VerificationBase419 {
    /**
     * @var string
     */
    protected $_emailConstant = self::EMAIL;

    /**
     * @var string
     */
    protected $_method;

    /**
     * @return int
     */
    protected function _getMethod() {
        new VerificationEmail419('method', 'extra');
        return $this->_method;
    }
}
