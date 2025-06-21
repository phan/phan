<?php

// Regression test for https://github.com/phan/phan/issues/4972

namespace NS983;

function sameParamObj( $obj ): void {
    if ( $obj->stop ?? false ) {
        var_export( $obj );
        return;
    }
    $obj->stop = true;
    sameParamObj( $obj );
}

function sameParamArray( $arr ): void {
    if ( $arr['stop'] ?? false ) {
        var_export( $arr );
        return;
    }
    $arr['stop'] = true;
    sameParamArray( $arr );
}
