<?php
/** Encode a signed long */
function encodeSignedLong( int $id ) : string {
    $high   = ( $id & 0xffffffff00000000 ) >> 32;
    $low    = $id & 0x00000000ffffffff;
    return pack( 'NN', $high, $low );
}
/** Encode a signed long (float literals out of range) */
function encodeSignedLongInvalid( int $id ) : int {
    $high   = ( $id & 0xfffffffff00000000 ) >> 32;
    $low    = $id & 0x100000000ffffffff;
    return ~0xff000f000f000f000 ^ $high ^ $low;
}
echo bin2hex(encodeSignedLong(0x7ffffff));
function misc899( int $id ) : int {
    // these numbers can be precisely represented as floats without losing the least significant bit
    return ( ~0xf0ffffff000ff000 ) ^ ( $id | 0xffffffff00000000 );
}
echo misc899(0x899);
