<?php

$element_list = array();
// Regression test for #3569
function el829(string $table_name)
{
    global $element_list;
    $data = array(
        'prefix' => '',
        'type' => array(),
    );

    foreach ($element_list[$table_name]['elements'] as $el_name => $data_array) {
        $data['type'][$el_name] = isset($data_array['type']) ? $data_array['type'] : '';
    }
    if (isset($element_list[$table_name]['read_data'])) {
        // should not throw PhanTypePossiblyInvalidDimOffset
        $data['type'][$data['prefix'].$element_list[$table_name]['read_data']['name']] = 'string';
        // should not throw PhanTypePossiblyInvalidDimOffset
        $data['type'][$data['prefix']] = 'string';

        $data['type'][$element_list[$table_name]['read_data']['name']] = 'string';
        $bar = '';

        $data['type'][$bar] = 'string';
        // should warn about misuse of ??
        $data['prefix'] = !isset($data['prefix']) ?? '';
        $data['type'][$data['prefix']] = 'string';
    }
}
