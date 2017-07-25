<?php

namespace {
    class Original278 {}
    class ExtendsAliased278 extends Aliased278 {}
    class ExtendsTwiceAliased278 extends TwiceAliased278 {}

    function testOriginal278(Original278 $x) {}
    function testAliased278(Aliased278 $x) {}

    class_alias('Original278', 'Aliased278');
    class_alias('Aliased278', 'TwiceAliased278');

    testOriginal278(new Original278());
    testOriginal278(new Aliased278());
    testOriginal278(new TwiceAliased278());
    testOriginal278(new ExtendsAliased278());
    testOriginal278(new ExtendsTwiceAliased278());
    testAliased278(new Original278());
    testAliased278(new Aliased278());
    testAliased278(new TwiceAliased278());
    testAliased278(new ExtendsAliased278());
    testAliased278(new ExtendsTwiceAliased278());
}

namespace Foo278 {

    class NamespacedOriginal {}
    class Dupe {}
    trait OriginalTrait {}

    class_alias('\Foo278\NamespacedOriginal', 'AliasedFromNamespace278');
    class_alias(\Original278::class, '\Foo278\AliasedFromRootNamespace');
    class_alias('DoesNotExist', '\Foo278\AliasedFromNonExisting');
    // Dupe was defined above this line
    class_alias('\Foo278\NamespacedOriginal', '\Foo278\Dupe');
    // Dupe2 was defined below  this line
    class_alias('\Foo278\NamespacedOriginal', '\Foo278\Dupe2');
    class_alias('\Foo278\OriginalTrait', '\Foo278\AliasedTrait');
    $w = new \Aliased278();
    $x = new \AliasedFromNamespace278();
    $y = new AliasedFromRootNamespace();
    // does not exist, was aliased from non-existent class
    $z = new AliasedFromNonExisting();
    // does not exist, was never attempted to be created
    $z = new DoesNotExist();

    class UsesAliasedTrait extends \Original278 {
        use AliasedTrait;
    }
    class Dupe2 {}
}
