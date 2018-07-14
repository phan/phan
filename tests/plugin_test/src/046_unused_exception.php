<?php
call_user_func(function() {
    $e = null;
    try {
        if (rand() % 2 > 0) {
            throw new RuntimeException('x');
        } else {
            throw new InvalidArgumentException('y');
        }
    } catch (RuntimeException $e) {
        echo $e;
    } catch (InvalidArgumentException $e2) {
    }
});
