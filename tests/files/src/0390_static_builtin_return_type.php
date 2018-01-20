<?php

class ExtendedDateTimeImmutable extends DateTimeImmutable {
    /** @return static */
    public function later() {
        return $this->add(new DateInterval('PT1H'));
    }
}

function test390() {
    $now = new ExtendedDateTimeImmutable();
    $later = $now->later();
    $earlier = $later->sub(new DateInterval('PT1D'));
    $earlier->missingMethod();
}

test390();
