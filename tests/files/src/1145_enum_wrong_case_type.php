<?php
namespace NS19;

enum Suit: string {
    case Hearts;  // missing value
}

enum Nat1 {
    case ZERO = 0;  // unexpectedly has a value
}

enum Nat2: int {
    case ZERO = 'zero';  // unexpected type of value
}
var_dump(Suit::Hearts, Nat1::ZERO, Nat2::ZERO);
