<?php

class Deck {
    const NUM_CARDS = 54  // missing semicolon
}
echo Deck::NUM_CARDS;
// files with syntax errors are currently not output (if PhanSyntaxError is emitted),
// so expected_src/004_error.php does not exist.
