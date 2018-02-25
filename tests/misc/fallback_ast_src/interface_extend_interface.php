<?php
namespace{
$x = new stdClass();
}

namespace Foo {

interface MultiInterface extends I1, namespace\I2, \I3 {
}
}
