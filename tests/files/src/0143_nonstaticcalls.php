<?php
namespace NS1
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
        {
        }
    }
}

namespace NS2
{
    class B extends \NS1\A
    {
        public function __construct()
        {
            self::callNonStaticMethod();
            static::callNonStaticMethod();
            parent::callNonStaticMethod();
            \NS1\A::callNonStaticMethod();
        }
    }

    class C extends B
    {
        public function __construct()
        {
            $this->callStatics();
        }

        public function callStatics()
        {
            self::callNonStaticMethod();
            static::callNonStaticMethod();
            parent::callNonStaticMethod();
            \NS1\A::callNonStaticMethod();
            \NS1\A::callNonStaticMethod();
            \NS2\B::callNonStaticMethod();
        }
    }
}
namespace
{
    error_reporting(E_ALL | E_STRICT);

    new NS1\A();
    new NS2\B();
    $c = new NS2\C();
	$c->callStatics();
}
