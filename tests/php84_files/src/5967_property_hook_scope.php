<?php
namespace NS5967;

class P {
    public int $prop = 1;
    public int $other = 2;
}

class C extends P {
    public int $prop {
        get {
            // __FUNCTION__ and __METHOD__ are defined in a hook
            $f = __FUNCTION__;
            $m = __METHOD__;
            '@phan-debug-var $f, $m';
            // The hook of the parent's property
            $v = parent::$prop::get();
            '@phan-debug-var $v';
            return $v + 1;
        }
        set {
            $r = parent::$prop::set($value * 2);
            '@phan-debug-var $r';
            parent::$prop::set(undefined_function_5967());
        }
    }

    // PHP only allows this in the hook of the same property
    public int $other {
        get => parent::$prop::get();
    }

    public int $missing {
        get => parent::$missing::get();
    }

    public function method(): int {
        return parent::$prop::get();
    }
}

class Promoted {
    public function __construct(
        int $other,
        // The hooks are not part of the body of the constructor
        public int $q = 0 {
            get {
                echo __METHOD__;
                return $this->q + $other;
            }
            set {
                $this->q = $value * 2;
            }
        },
        public string $foo = '' {
            get {
                return "foo";
            }
            set(string $value) {
                $this->foo = strtoupper($value);
            }
        },
    ) {
        echo $other;
    }
}

$c = new C();
$c->prop = 2;
$p = new Promoted(1);
$p->q = 3;
$p->foo = 'x';
var_dump($c->prop, $c->other, $c->missing, $c->method(), $p->q, $p->foo);

class NoParent {
    public int $p {
        get => parent::$p::get();
    }
}

class Base2 {
    private int $priv = 1;
    public int $hooked = 1 {
        set {
            $this->hooked = $value;
        }
    }
    public int $backed = 1;
}

class Child2 extends Base2 {
    public int $priv {
        get => parent::$priv::get();
    }
    public int $hooked {
        set {
            // A set hook returns nothing
            $r = parent::$hooked::set($value);
            '@phan-debug-var $r';
        }
    }
    public int $backed {
        set {
            // Without a set hook in the parent, this is the value that was assigned
            $r = parent::$backed::set($value);
            '@phan-debug-var $r';
            parent::$backed::set($undefined);
        }
    }
}

/** @template T */
class Generic {
    /** @var T */
    public $v;
}

/** @extends Generic<int> */
class IntGeneric extends Generic {
    public $v {
        get {
            $x = parent::$v::get();
            '@phan-debug-var $x';
            return $x;
        }
    }
}

function declares_class_5967(): void {
    // The hooks of a class declared in a function are not part of that function
    class LocalClass5967 {
        public int $x {
            get {
                echo __FUNCTION__;
                return 1;
            }
        }
    }
    $o = new LocalClass5967();
    echo $o->x;
}
declares_class_5967();
$child2 = new Child2();
$child2->hooked = 1;
$child2->backed = 2;
var_dump((new NoParent())->p, $child2->priv, (new IntGeneric())->v);
