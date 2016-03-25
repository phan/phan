<?php
namespace Something
{

    class A
    {

        public function __construct()
        {
            self::callNonStaticMethod();
            static::callNonStaticMethod();
            A::callNonStaticMethod();
        }

        public function callNonStaticMethod()
        {}
    }
}
namespace AnotherNamespace
{

    class B extends \Something\A
    {

        public function __construct()
        {
            self::callNonStaticMethod();
            static::callNonStaticMethod();
            parent::callNonStaticMethod();
            \Something\A::callNonStaticMethod();
        }
    }

    class C extends B
    {

        public function __construct()
        {
            self::callNonStaticMethod();
            static::callNonStaticMethod();
            parent::callNonStaticMethod();
            \Something\A::callNonStaticMethod();
            \Something\A::callNonStaticMethod();
            \AnotherNamespace\B::callNonStaticMethod();
        }
    }
}
namespace
{

    new Something\A();
    new AnotherNamespace\B();
    new AnotherNamespace\C();
}