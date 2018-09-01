<?php
call_user_func(function() {
    $dir = opendir('.');
    while ($dir && (false !== ($file = readdir($dir)))) {
        echo $file;
    }
});
