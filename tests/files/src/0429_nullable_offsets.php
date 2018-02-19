<?php

/**
 * @param ?string[] $data
 * @param array<int,string> $data_int_key
 * @param array<int,string> $nullable_data_int_key
 * @param ?int $index
 */
function example429($data, $data_int_key, $nullable_data_int_key, $index) {
    echo strlen($data['opt']);
    echo intdiv($data['opt'], 2);
    echo strlen($data_int_key['opt']);
    echo strlen($data_int_key[2]);
    echo strlen($nullable_data_int_key['opt']);
    echo strlen($nullable_data_int_key[2]);
    echo strlen($nullable_data_int_key[$index]);
    echo intdiv($nullable_data_int_key[$index], 2);
}
