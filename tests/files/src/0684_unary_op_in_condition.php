<?php declare(strict_types=1);

namespace NS684;

/**
 * @param string $s
 *
 * @return bool|int Returns -1 when $s is empty, false if it is bad word, or true if it is fine
 */
function checkString( string $s )
{
    if ( 'badword' === $s ) return false;
    return '' === $s ? -1 : true;
}

/**
 * Checks whether string is valid
 *
 * @param string $s String to check
 *
 * @return bool String is not empty and not a bad word
 */
function isValidString( $s ): bool
{
    $x = checkString( $s );

    // Assuming we can't statically analyse checkString
    // Here, $x can be -1, true or false
    \assert( -1 === $x || \is_bool( $x ) );

    if ( -1 === $x ) {
        // Handling the case that $x is -1
        return false;
    }
    echo count($x);

    // At this point, $x can only be true or false so it is boolean
    return $x;
}

/**
 * Checks whether string is valid
 *
 * @param string $s String to check
 *
 * @return bool String is not empty and not a bad word
 */
function isValidString2( $s ): bool
{
    $x = checkString( $s );

    // Assuming we can't statically analyse checkString
    // Here, $x can be -1, true or false
    \assert( 10 - 11 === $x || \is_bool( $x ) );

    if ( -1 === $x ) {
        // Handling the case that $x is -1
        return false;
    }
    echo count($x);

    // At this point, $x can only be true or false so it is boolean
    return $x;
}
