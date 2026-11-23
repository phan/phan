<?php
/**
 * @phan-file-suppress PhanUnreferencedPublicProperty, PhanReadOnlyPublicProperty, PhanWriteOnlyPublicProperty
 * @phan-file-suppress PhanPluginUnknownPropertyType, PhanPluginUnknownClosureReturnType
 */
namespace NS5968;

interface I {
    public int $ix { get; }
    public int $irw { get; set; }
}

abstract class Base implements I {
    abstract public int $ax { get; set; }
    final public int $fp = 1;
    public int $hp {
        final get => 1;
    }
    public int $plain = 1;
    public int $virtual {
        get => 2;
    }
    public int $backed {
        get => $this->backed;
    }

}

// Valid: everything is implemented
class Valid extends Base {
    public int $ix = 1;
    public int $irw {
        get => $this->irw;
    }
    public int $ax {
        get => 3;
        set {
        }
    }
    // Overriding the set hook when only the get hook is final
    public int $hp {
        set {
        }
    }
    public int $plain {
        #[\Override]
        get => 4;
    }
    public int $backed {
        #[\Override]
        set {
            $this->backed = $value;
        }
    }
}

// Invalid
class Invalid extends Base {
    // I::$ix::get is not implemented at all
    // I::$irw::set is not implemented: the property is virtual
    public int $irw {
        get => 5;
    }
    // Base::$ax::set is not implemented
    public int $ax {
        get => 6;
    }
    public int $fp = 2;
    public int $hp {
        get => 7;
    }
    public int $virtual {
        #[\Override]
        set {
        }
    }
    public int $new {
        #[\Override]
        get => 8;
    }
}

class Backing {
    // Backed: the default is allowed
    public int $a = 0 {
        get => $this->a;
    }
    public int $b = 0 {
        set => $value;
    }
    public int $c = 0 {
        get {
            return $this?->c;
        }
    }
    public function __construct(
        public int $d = 0 {
            set {
                $this->d = $value * 2;
            }
        },
    ) {
    }
    // Virtual
    public int $e = 0 {
        get {
            return 1;
        }
        set {
        }
    }
    public int $f = 0 {
        get {
            $fn = fn() => $this->f;
            return $fn();
        }
    }
    public int $g {
        get;
    }
}

class SetParam {
    public string $ok1 {
        set(string|array $v) {
            $this->ok1 = (string)json_encode($v);
        }
    }
    public int $ok2 {
        set(?int $v) {
            $this->ok2 = (int)$v;
        }
    }
    public $untyped {
        set(string $v) {
            $this->untyped = $v;
        }
    }
    public string|array $wide {
        set(string $v) {
            $this->wide = $v;
        }
    }
    public string $untyped_param {
        set($v) {
            $this->untyped_param = $v;
        }
    }
}

$valid = new Valid();
$invalid = new Invalid();
$backing = new Backing();
$set = new SetParam();
$set->ok1 = 'a';
$set->ok2 = 1;
$set->untyped = 'a';
$set->wide = 'a';
$set->untyped_param = 'a';
var_export([$valid->ix, $valid->irw, $valid->ax, $valid->hp, $valid->plain, $valid->backed, $valid->fp, $valid->virtual]);
var_export([$invalid->irw, $invalid->ax, $invalid->fp, $invalid->hp, $invalid->virtual, $invalid->new]);
var_export([$backing->a, $backing->b, $backing->c, $backing->d, $backing->e, $backing->f, $backing->g, $set]);
