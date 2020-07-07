<?php

// Regression test for https://github.com/phan/phan/issues/3796
namespace TraitCrash;
/**
 * @method diff(\DateTimeInterface $date) XXX redefining the method as a magic method causes different issue name
 */
trait BaseTrait {
    public function diff($date = null) {
        return 1;
    }
}

trait Other {
    use BaseTrait;
}
