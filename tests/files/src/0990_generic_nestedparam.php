<?php 
/** @template T */
class Container {
    /** @var T */
    public $t;

    /** @param T $t */
    function __construct($t) {
        $this->t = $t;
    }
}
/**
 * @extends Container<int>
 */
class IntContainer extends Container {
}
/**
 * @extends Container<string>
 */
class StringContainer extends Container {
}

/**
 * @template X
 * @extends Container<Container<X>>
 */
class ContainerContainer extends Container {
    // This constructor is redundant and shouldn't be necessary, but Phan requires it to parse template annotations right now
    /** @param Container<X> $t */
    function __construct($t) {
        parent::__construct($t);
    }
}
/**
 * @extends ContainerContainer<int>
 */
class IntContainerContainer extends ContainerContainer {
}

function assertInt(int $x): void {
	echo $x;
}

$ok1 = new IntContainerContainer(new Container(1));
assertInt($ok1->t->t);

$ok2 = new IntContainerContainer(new IntContainer(1));
assertInt($ok2->t->t);

$err1 = new IntContainerContainer(new Container('oops'));
assertInt($err1->t->t);

$err2 = new IntContainerContainer(new StringContainer('oops'));
assertInt($err2->t->t);

$ok3 = new ContainerContainer(new Container('ok'));
assertInt($ok3->t->t); // err
