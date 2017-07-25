<?php
class ClassMissingStaticProps {
    public function __get($name) { return 42; }  // __get should have no impact on analyzing static properties
    public function __set($name, $value) { return; }  // __set should have no impact on analyzing static properties.
}
function checkMissingStaticProps() {
    ClassMissingStaticProps::$prop = 2;  // Causes Error to be thrown if executed.
    var_export(ClassMissingStaticProps::$prop);
    // Instances of the built in stdClass are permitted to have any instance property.
    // But stdClass has no static properties.
    stdClass::$firstProp = true;
    $b = stdClass::$secondProp;
    var_export(stdClass::$thirdProp);
}
