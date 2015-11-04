<?php
namespace Foo;

trait A {
}

trait B {
}

class C {
    use \Foo\A;
    use B;
}

class D {
    use A;
}

