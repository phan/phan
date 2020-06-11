<?php
function test_condition_and_assign(array $extraInfo, object $device, bool $x) {
    if($x && !is_string($devState=$device->stateInfo())) {
        '@phan-debug-var $devState';  // Should infer this is defined but of unknown type
        if (array_key_exists('chmax',$devState)) {
           $someVar=$devState['chmax'];
        }
    }
}
