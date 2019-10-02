<?php
$_SESSION['field'] = 'blah';
$argc[0] = 'blah';
call_user_func(function () {
    $_SESSION['other'] = null;
    $argc[0] = 'blah';
});
