<?php declare(strict_types=1);

/** @template Magic */
class Foo {

    /** @var Magic */
    protected $magic;

    /** @param Magic $magic */
    function __construct( $magic ) {
        $this->magic = $magic;
    }

    /**
     * @param Magic[] $in
     * @return Magic
     */
    function unbox( $in ) {
        return reset( $in );
    }

    /** @return Magic */
    function magic() {
        return $this->magic;
    }

    /** @return Magic[] */
    function magicRay() : array {
        return [ $this->magic ];
    }

}

class Bar {

    function baz() {
        echo "baz\n";
    }

}

$foo = new Foo( new Bar );
$foo->magic()->baz();
$foo->unbox( [ new Bar ] )->baz();
foreach ( $foo->magicRay() as $bar ) {
    $bar->baz();
    $bar->missing();
}
