<?php

namespace {
    var_export(stdClass());
    var_export(ast\Node());
    class SomeClass778 {}
    class SomeInaccessibleClass778  { protected function __construct() {}}
    trait SomeTrait778 {}
}
namespace N778 {
    var_export(stdClass());
    var_export(ast\Node());  // oh well, not able to suggest this
    var_export(\stdClass());
    var_export(\ast\Node());
    var_export(ArrayAccess());
    var_export(ArrayObject());
    var_export(SomeClass778());
    var_export(SomeInaccessibleClass778());
    var_export(SomeTrait778());
}
