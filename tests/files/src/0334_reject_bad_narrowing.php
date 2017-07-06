<?php

/**
 * @param DateTime $x (Boilerplate comment, should say ?DateTime or DateTime|null)
 * @param w $w (Boilerplate comment, should say ?DateTime or DateTime|null)
 */
function badNarrowing334(DateTime $x = null) {
}
badNarrowing334(null);
badNarrowing334();
badNarrowing334(new DateTime('@123456789'));
badNarrowing334(442);  // invalid

/**
 * @param null $x (typically a mistake to specify null as the type)
 */
function badNarrowingAsNull334(int $x = null) {
}

badNarrowingAsNull334(42);
badNarrowingAsNull334(null);
badNarrowingAsNull334("42");  // invalid

/**
 * @param int|null $x (Wrong)
 * @param ?int $y (Wrong)
 */
function badNarrowingAsNullComposite334(?string $x, string $y = null) {
}
badNarrowingAsNullComposite334('a', 'b');
badNarrowingAsNullComposite334(null, null);
badNarrowingAsNullComposite334(2, 3);  // invalid

/**
 * @param int|null $x (Wrong)
 * @param ?int $y (Wrong)
 */
function badNarrowingAsNullComposite334B(?string $x, string $y = null) {
}

interface X334 {
    /** @param ?DateTime $dt */
    public function setDT(DateTime $dt = null);
}
