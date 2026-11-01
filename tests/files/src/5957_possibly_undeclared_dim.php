<?php
// Regression test for https://github.com/phan/phan/issues/5621:
// reading `$var[...]` of a possibly undeclared variable should warn like reading `$var` or `$var->prop`.

function byref5957(array &$a): void { $a[] = 1; }
function sink5957(int ...$values): void { var_dump($values); }
function refs5957(array &...$refs): void { var_dump($refs); }

function test5957(bool $b, array $src): void {
    if ($b) {
        $arr = [1, [2]]; $str = 'abc'; $obj = new stdClass(); $obj->p = 1;
        $w = []; $inc = [1]; $cat = ['a']; $r = [1]; $q = [[1], [2]]; $m = [[1]]; $n = [1]; $u = [1]; $c = [1]; $e = [1, 2]; $d = [1]; $pa = [1]; $pb = [1];
    }
    echo $arr[0];
    echo $arr;
    echo $obj->p;
    echo $str[0];
    $x = $arr[1][0];
    $y = [$arr[0], count($arr[1])];
    foreach ($arr[1] as $v) { echo $v; }
    [$z] = $m[0];
    $inc[0]++;
    $cat[0] .= 'b';
    var_dump($x, $y, $z, strlen($str[1]));
    var_dump(strlen(string: $str[2]));
    sink5957(item: $arr[0]);
    sink5957($pb[0], count($pb = [1]));

    // Not warned about: PHP does not read the variable (or does so silently)
    $w[0] = 1;
    $w[1][2] = 1;
    $ref = &$r[0];
    byref5957($q[0]);
    byref5957(a: $q[1]);
    refs5957(item: $q[0]);
    sink5957(count($pa = [1]), $pa[0]);
    preg_match('/a/', 'a', $n[0]);
    unset($u[0]);
    $c[0] ??= 1;
    var_dump($ref, isset($e[0]), empty($e[1]), $e[0] ?? null, $d[0] ?? $src);
}

if (rand(0, 1)) {
    $global_arr = [1];
}
echo $global_arr[0];
