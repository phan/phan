<?php

// Test cases for UncoveredEnumCasesInMatchPlugin

enum Suit {
    case Hearts;
    case Spades;
    case Diamonds;
    case Clubs;
}

enum Game {
    case Poker;
    case Solitaire;
    case Blackjack;
}

enum Color: string {
    case Red = 'red';
    case Blue = 'blue';
    case Green = 'green';
}

// Test 1: Missing enum cases - should warn
function testMatchEnum1(Suit $suit): string
{
    return match ($suit) {
        Suit::Spades => 'The swords of a soldier',
        Suit::Hearts => 'The shape of my heart',
    };
}

// Test 2: All cases covered - should NOT warn
function testMatchEnum2(Suit $suit): string
{
    return match ($suit) {
        Suit::Spades => 'The swords of a soldier',
        Suit::Hearts => 'The shape of my heart',
        Suit::Diamonds => 'Money for this art',
        Suit::Clubs => 'Weapons of war',
    };
}

// Test 3: Has default - should NOT warn
function testMatchEnum3(Suit $suit): string
{
    return match ($suit) {
        Suit::Spades => 'The swords of a soldier',
        Suit::Hearts => 'The shape of my heart',
        default => 'Generic card suit',
    };
}

// Test 4: Union type with multiple enums, missing cases - should warn
function testMatchEnum4(Suit|Game $var): string
{
    return match ($var) {
        Suit::Spades => 'The swords of a soldier',
        Suit::Hearts => 'The shape of my heart',
        Suit::Diamonds => 'Money for this art',
        Suit::Clubs => 'Weapons of war',
    };
}

// Test 5: Union type with multiple enums, all covered - should NOT warn
function testMatchEnum5(Suit|Game $var): string
{
    return match ($var) {
        Suit::Spades => 'The swords of a soldier',
        Suit::Hearts => 'The shape of my heart',
        Suit::Diamonds => 'Money for this art',
        Suit::Clubs => 'Weapons of war',
        Game::Solitaire => 'Single player card game',
        Game::Poker => 'Card game played against other players',
        Game::Blackjack => 'Card game played against the house',
    };
}

// Test 6: Union with non-enum types - should warn about non-finite types needing default
// (string|int|array are non-finite and need a default arm)
function testMatchEnum6(Suit|string|int|array $suit): string
{
    return match ($suit) {
        Suit::Spades => 'The swords of a soldier',
        Suit::Hearts => 'The shape of my heart',
        Suit::Diamonds => 'Money for this art',
    };
}

// Test 7: Mixed enum cases and other conditions - should NOT warn
function testMatchEnum7(Suit $suit): string
{
    return match ($suit) {
        Suit::Spades => 'The swords of a soldier',
        Suit::Hearts => 'The shape of my heart',
        default => 'Other',
    };
}

// Test 8: Backed enum missing cases - should warn
function testMatchEnum8(Color $color): string
{
    return match ($color) {
        Color::Red => 'Stop',
        Color::Blue => 'Go',
    };
}

// Test 9: All backed enum cases covered - should NOT warn
function testMatchEnum9(Color $color): string
{
    return match ($color) {
        Color::Red => 'Stop',
        Color::Blue => 'Go',
        Color::Green => 'Caution',
    };
}

// Test 10: Multiple conditions in single arm, missing cases - should warn
function testMatchEnum10(Suit $suit): string
{
    return match ($suit) {
        Suit::Spades, Suit::Clubs => 'Black',
        Suit::Hearts => 'Red',
    };
}

// Test 11: Multiple conditions in single arm, all covered - should NOT warn
function testMatchEnum11(Suit $suit): string
{
    return match ($suit) {
        Suit::Spades, Suit::Clubs => 'Black',
        Suit::Hearts, Suit::Diamonds => 'Red',
    };
}

// Test 12: Empty match (edge case) - should NOT warn
function testMatchEnum12(Suit $suit): never
{
    match ($suit) {
    };
}

// Test 13: Match with only default - should NOT warn
function testMatchEnum13(Suit $suit): string
{
    return match ($suit) {
        default => 'Any suit',
    };
}

// Test 14: Non-enum match - should NOT warn
function testMatchEnum14(int $x): string
{
    return match ($x) {
        1 => 'one',
        2 => 'two',
    };
}

// Test 15: Mixed types with int - should warn about int needing default
function testMatchEnum15(Suit|int $var): string
{
    return match ($var) {
        Suit::Hearts => 'heart',
        1 => 'one',
        2 => 'two',
    };
}

// ============================================
// Bool exhaustiveness tests
// ============================================

// Test 16: Bool missing false - should warn
function testBool1(bool $b): string
{
    return match ($b) {
        true => 'yes',
    };
}

// Test 17: Bool missing true - should warn
function testBool2(bool $b): string
{
    return match ($b) {
        false => 'no',
    };
}

// Test 18: Bool all cases covered - should NOT warn
function testBool3(bool $b): string
{
    return match ($b) {
        true => 'yes',
        false => 'no',
    };
}

// Test 19: Bool with default - should NOT warn
function testBool4(bool $b): string
{
    return match ($b) {
        true => 'yes',
        default => 'other',
    };
}

// Test 20: Nullable bool - only checks true/false, not null - should warn
function testBool5(?bool $b): string
{
    return match ($b) {
        true => 'yes',
    };
}

// Test 21: Variable arm condition - should NOT warn (false positive prevention)
function testBoolVarArm(bool $a, bool $b): string
{
    return match ($a) {
        $b => 'equal',
    };
}

// ============================================
// Non-finite type tests (need default)
// ============================================

// Test 22: String without default - should warn
function testString1(string $s): string
{
    return match ($s) {
        'a' => 'A',
        'b' => 'B',
    };
}

// Test 23: String with default - should NOT warn
function testString2(string $s): string
{
    return match ($s) {
        'a' => 'A',
        default => 'other',
    };
}

// Test 24: Int without default - should warn
function testInt1(int $i): string
{
    return match ($i) {
        1 => 'one',
        2 => 'two',
    };
}

// Test 25: Int with default - should NOT warn
function testInt2(int $i): string
{
    return match ($i) {
        1 => 'one',
        default => 'other',
    };
}

// Test 26: Float without default - should warn
function testFloat1(float $f): string
{
    return match ($f) {
        1.0 => 'one',
        2.0 => 'two',
    };
}

// Test 27: Mixed type - should warn (needs default)
function testMixed1(mixed $m): string
{
    return match ($m) {
        'a' => 'A',
        1 => 'one',
    };
}
