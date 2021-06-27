<?php

class MyStringable {
    public function __toString() {
        return 'key';
    }
}

// NOTE: We use these invalid arrays in calls to force Phan to compute their union types,
// to validate that they don't cause Phan to crash.

echo strlen([new MyStringable() => 1]); // this implicit cast to string is not allowed
echo strlen([4.5 => 1]);
echo strlen([(rand(0,1) === 1) => 1]);
echo strlen([4.0 => 1]);  // this is a valid key literal
echo strlen([false => 0]);
echo strlen([true => 1]);
echo strlen([null => 'abc']);
echo strlen([new stdClass() => 'abc']);
$o = [];
echo strlen([$o => 1]);
echo strlen([STDIN => 'from STDIN']);
