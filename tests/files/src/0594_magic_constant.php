<?php
echo intdiv(__FUNCTION__, 2);
echo intdiv(__METHOD__, 2);
echo intdiv(__TRAIT__, 2);
echo intdiv(__CLASS__, 3);
call_user_func(function () {
    echo intdiv(__FUNCTION__, 2);
    echo intdiv(__METHOD__, 2);
    echo intdiv(__TRAIT__, 2);
    echo intdiv(__CLASS__, 3);
});
