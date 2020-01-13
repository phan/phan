<?php
function test(array $attributes) {
    do {
        $att = [];
        foreach ($attributes as $key => $value) {
            $att[] = sprintf('%s: %s', $key, $value);
        }
        $att = ($att ? ', '.implode(', ', $att) : '');
        echo $att;  '@phan-debug-var $att';
    } while (rand() % 2 );
}
