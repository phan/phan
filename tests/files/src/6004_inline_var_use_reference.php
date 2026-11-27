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
