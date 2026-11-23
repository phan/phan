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

// A hook that is not redeclared is inherited from the parent class
interface InheritI { public int $p { get; set; } public int $q { get; } }
class InheritA { public int $p { get => 1; set { } } public int $q = 0; }
class InheritB extends InheritA implements InheritI { public int $p { get => 2; } }
abstract class AbstractGetSet { abstract public int $p { get; set; } }
abstract class ImplementsGet extends AbstractGetSet { public int $p { get => 1; } }
class MissingSet extends ImplementsGet { public int $p { get => 2; } }
class BackedImplementsBoth extends AbstractGetSet { public int $p { get => $this->p; } }

// A final hook is found above a class that redeclares only the other hook
class FinalGet { public int $p { final get => 1; } }
class OnlySet extends FinalGet { public int $p { set { } } }
class OverridesFinalGet extends OnlySet { public int $p { get => 2; } }

// The properties of a trait are checked for the class that uses it
trait HookTrait {
    public int $p { #[\Override] get => 1; }
}
trait FinalTrait { public int $fp = 1; public int $hp { get => 2; } }
class TraitParent { public int $p = 0; }
class UsesTraitValid extends TraitParent { use HookTrait; }
class UsesTraitInvalid { use HookTrait; }
abstract class UsesFinalTrait extends Base { use FinalTrait; }

// A property that is private(set) is implicitly final
class PrivateSet { public private(set) int $x = 0; }
class OverridesPrivateSet extends PrivateSet { public int $x = 1; }

// #[\Override] needs the hook itself in an abstract property
abstract class AbstractGet { abstract public int $p { get; } }
class OverrideSet extends AbstractGet { public int $p { #[\Override] get => 1; #[\Override] set { } } }

// The issues can be suppressed on the property
abstract class Suppressed extends Base {
    /** @suppress PhanPropertyHookFinalOverride */
    public int $hp { get => 2; }
    /** @suppress PhanCommentOverrideOnNonOverrideMethod */
    public int $unknown { #[\Override] get => 2; }
    /** @suppress PhanPropertyHookIncompatibleParamType */
    public int $s { set(string $v) { $this->s = 1; } }
    /** @suppress PhanAccessOverridesFinalProperty */
    public int $fp = 3;
}

// The parameter of a set hook must accept the type of the property without coercion
class SetParamVariance {
    public mixed $m { set(string $v) { $this->m = $v; } }
    public int $i { set(float $v) { $this->i = (int)$v; } }
    public ?int $n { set(int $v) { $this->n = $v; } }
    public int $ok1 { set(int|string $v) { $this->ok1 = (int)$v; } }
    public float $ok2 { set(int|float $v) { $this->ok2 = $v; } }
    public ?SetParamVariance $ok3 { set(mixed $v) { $this->ok3 = null; } }
    public array $ok4 { set(iterable $v) { $this->ok4 = []; } }
    public string $ok5 { set(string|\Stringable $v) { $this->ok5 = (string)$v; } }
}

// The types of a set hook may refer to classes that are declared later, and null is part of the union
class LaterTypes {
    public LaterChild|int $p { set(LaterBase|int $v) { $this->p = 1; } }
    public ?LaterChild $n { set(object|null $v) { $this->n = null; } }
    public LaterBase $bad { set(LaterChild $v) { $this->bad = $v; } }
    /** @suppress PhanInvalidNode */
    public int $suppressed { get; }
}
class LaterBase {}
class LaterChild extends LaterBase {}

// A class with both (at)property and a declaration of the property
/** @property int $q */
class WithPHPDocProperty implements InheritI { public int $p = 0; public int $q = 0; }

// The defaults of hooked properties are still compared
trait DefaultTrait { public int|string $d = 'string' { get => $this->d; } }
class DefaultParent { public int|string $d = 42; }
class DefaultChild extends DefaultParent { use DefaultTrait; }
