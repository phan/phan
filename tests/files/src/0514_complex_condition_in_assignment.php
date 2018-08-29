<?php

call_user_func(function() {
    if (($arg = ((rand(0, 10) > 0) ? 'test string' : false)) !== false) {
        echo key($arg);  // should warn
    }
});
