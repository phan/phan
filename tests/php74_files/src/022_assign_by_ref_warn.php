<?php

namespace NS21;

use stdClass;

class HasTypedProperties {
    public stdClass $obj;
    public string $str;
    public static int $static_int;
    public function main() {
        $h = new HasTypedProperties();
        $h->obj = 2;
        self::modify_value(self::$static_int);
        self::modify_value($h->obj);
        self::modify_value($this->str);
        $h->instanceModifyValue($this->obj);
        $h->instanceModifyValue(HasTypedProperties::$static_int);
        $h->instanceModifyValue($h->str);
        $ref = &$h->obj;
        self::modify_value($ref);
        $ref2 = &self::$static_int;
        $ref2 = null;
    }
    public function instanceModifyValue(&$prop) {
        $prop = 'invalid instance value';
    }
    public static function modify_value(&$prop) {
        $prop = 'invalid value';
    }

    public function modifyWithReference(string $sentence) {
        preg_match('/\w+/', $sentence, $this->obj);
        preg_match('/\w+/', $sentence, self::$static_int);
        $h = new HasTypedProperties();
        self::setRef($h->obj);
        self::setRef(self::$static_int);
    }

    public static function setRef(&$val) {
        $val = self::makeArray('[]');
    }

    /**
     * @return array
     * @suppress PhanPartialTypeMismatchReturn
     */
    public static function makeArray(string $str) {
        return json_decode($str);
    }
}
