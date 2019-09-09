<?php declare(strict_types=1);

function accepts_string(string $s) : void {
    echo "Got $s\n";
}

call_user_func(function () {
    $values = sscanf('10 values', '%d');
    foreach ($values as $i => $value) {
        echo strlen($i);
        echo strlen($value);
    }
    foreach (['x' => 'y'] as $i => $value) {
        // array<string, string> is possibly allowed to have keys cast to 'int' when the keys get inserted.
        echo intdiv($i, 2);
        echo strlen($value);
    }
    foreach (['y', 'z'] as $i => $value) {
        accepts_string($i);
        accepts_string($value);
    }
});
function create_array(string $key) : array {
    $result = [$key => ['value']];
    foreach ($result as $i => $value) {
        // array<string, string> is possibly allowed to have keys cast to 'int' when the keys get inserted.
        echo intdiv($i, 2);
        echo strlen($value);
        '@phan-debug-var $value';
    }
    return $result;
}
