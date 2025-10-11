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

// Test 6: Union with non-enum types - should NOT warn
// (because not all arms are enum cases)
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

// Test 15: Mixed types but some arms are not enum cases - should NOT warn
function testMatchEnum15(Suit|int $var): string
{
    return match ($var) {
        Suit::Hearts => 'heart',
        1 => 'one',
        2 => 'two',
    };
}
