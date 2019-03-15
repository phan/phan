<?php
call_user_func(function () {
    $file = "test.gz";
    $is_compressed = (substr($file, -3) === '.gz');
    if ($is_compressed) {
            $file_handle = gzopen($file, "r");
    } else {
            $file_handle = fopen($file, "r");
    }
    while (($line = ($is_compressed ? gzgets($file_handle, 1024 * 32)
                                    : fgets($file_handle))) !== false) {
        echo count($line);
    }
});
