<?php
call_user_func(function () {
    $a = null;  // Should warn about unused $a
    do {
        $a = rand(0, 1);
    } while ($a > 0);
});
// should not warn about unused variables
call_user_func(function () {
    try {
        $a = intdiv(2,1);
    } catch (Exception $e) {
        $a = $e->getCode();
    }
    var_export($a);
});
// should not warn about unused variables
call_user_func(function (array $data) {
    $a = null;
    if (rand(0, 1) > 0) {
        foreach ($data as $x) {
            $a = $x;
        }
    } else {
        $a = 2;
    }
    return $a;
}, []);

// should not warn about unused variables
call_user_func(function (int $n) {
    $a = null;
    if (rand(0, 1) > 0) {
        for ($i = 0; $i < $n; $i++) {
            $a = $n;
        }
    } else {
        $a = 2;
    }
    return $a;
}, -1);

// should not warn about unused variables (This is unrealistic, but all definitions can be used)
call_user_func(function (int $n) {
    $a = null;
    if (rand(0, 1) > 0) {
        while (rand(0, 1) > 0) {
            $a = $n;
        }
    } else {
        $a = 2;
    }
    return $a;
}, -1);
