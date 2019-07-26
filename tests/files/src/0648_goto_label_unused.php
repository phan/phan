<?php
m1:
m2:
echo "At line\n";
if (rand() % 2 > 0) {
    goto m1;
}
call_user_func(function () {
    x1:echo "a";
    x2:echo "b";
    if (rand() % 2 > 0) {
        echo "c";
        if (rand() % 2 > 0) {
            goto x2;
        }
    }
});
function test() {
    m1:
    m2:
    echo "At line\n";
    m3:
    if (rand() % 2 > 0) {
        goto m2;
    }
}
class X {
    function test() {
        m1:
        m2:
        echo "At line\n";
        if (rand() % 2 > 0) {
            goto m2;
        }
    }
}
