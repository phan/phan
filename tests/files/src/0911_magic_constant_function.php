<?php

namespace NS911;

/**
 * @param string|string[] $data
 *
 * @return string|string[]
 */
function recursive_rawurlencode($data){
    if(is_array($data)){
        return array_map(__FUNCTION__, $data);
    } elseif (is_object($data)) {
        // should emit PhanUndeclaredFunctionInCallable
        return call_user_func(__FUNCTION__ . '_object', $data);
    }

    return rawurlencode($data);
}

recursive_rawurlencode([]);
