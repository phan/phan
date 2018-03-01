<?php

// Should not result in a false positive in this case:
function test434() : int {
    $x = ['key' => ['other' => 'value']];
    $x['key']['arrayKey'] = [2];
    return count($x['key']['arrayKey']) + strlen($x['key']['other']);
}
