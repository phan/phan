<?php

// The type of the comment of a statement refers to a `use` statement, also though php-ast has no node for that comment.
// Not: a fully qualified name, a word of the description, a `//` comment, a comment in another namespace of the file.

namespace NS6004\Model { class Period {} class Metric {} class Row {} class Unused {} class Word {} class Fq {} class Sub {} class Event {} class Plain {} }
namespace NS6004 {
use NS6004\Model\Period;
use NS6004\Model\Metric;
use NS6004\Model\Row as R;
use NS6004\Model\Unused;
use NS6004\Model\Word;
use NS6004\Model\Fq;
use NS6004\Model;
use NS6004\Model\Event;
use NS6004\Model\Plain;
function f(array $rows, $x) {
    /** @var Period $period */
    $period = $x;
    /** @var Metric[] $metrics */
    $metrics = $rows;
    foreach ($rows as $row) {
        /* @var array<int, R>|null $row */
        echo count($row ?? []);
    }
    /** @var \NS6004\Model\Fq $fq the Word that is described here */
    $fq = $x;
    /** @phpstan-var Model\Sub $sub */
    $sub = $x;
    /**
     * @param Event $event
     */
    do_action('x', $x);
    // @var Plain $p
    return [$period, $metrics, $fq, $sub];
}
function do_action(string $n, $e): void { echo $n, $e; }
}
namespace NS6004b {
use NS6004\Model\Period;
function g($x) { return $x; }
}

// The same for a type on several lines and after a method that is named namespace. Not a reference: a key of an array shape,
// a string literal, the name of a class constant, and a comment in another declaration of the same namespace.
namespace NS6004m { class A {} class B {} class C {} class D {} class E {} class F {} class G {} class H {} class K {} }
namespace NS6004x {
use NS6004m\A;
use NS6004m\B;
use NS6004m\C;
use NS6004m\D;
use NS6004m\E;
use NS6004m\F;
use NS6004m\G;
class Holder {
    public function namespace($x) { return $x; }
    public function f($x) {
        /** @var A $a after a method that is named namespace */
        $a = $x;
        /**
         * @var array{
         *   item: B,
         *   other?: int
         * } $b
         */
        $b = $x;
        /** @var array{C: string, D?: int} $c shape keys */
        $c = $x;
        /** @var 'E'|"F" $e string literals */
        $e = $x;
        /** @var int-mask-of<Holder::G> $g a class constant named like an import */
        $g = $x;
        return [$a, $b, $c, $e, $g];
    }
}
}
namespace NS6004y {
use NS6004m\H;
function unused($x) { return $x; }
}
namespace NS6004y {
use NS6004m\K;
function g($x) {
    /** @var H $h refers to NS6004y\H here, not to the import of the block above */
    $h = $x;
    /** @var K $k */
    $k = $x;
    return [$h, $k];
}
}

// A comment that is only `#`. Not a reference: a part of a type with hyphens, and a key of an array shape with a dot.
namespace NS6004q { class Positive {} class Foo {} class Real {} class Cond {} }
namespace NS6004r {
use NS6004q\Positive;
use NS6004q\Foo;
use NS6004q\Real;
use NS6004q\Cond;
#
function f($x) {
    /** @var positive-int $p */
    $p = $x;
    /** @var array{Foo.Bar: string, 0: int} $s */
    $s = $x;
    /** @var non-empty-list<Real> $r */
    $r = $x;
    return [$p, $s, $r];
}
/** @return ($x is null ? Cond : int) */
function g($x) { return $x; }
}
