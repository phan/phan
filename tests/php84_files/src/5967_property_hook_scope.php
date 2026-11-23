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
