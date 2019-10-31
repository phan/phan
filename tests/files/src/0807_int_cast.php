<?php
function test(int $i, float $f) {
    var_export($i === 2.0);
    var_export(2 === 2.0);
    var_export($i === $f);
    var_export($i === []);
    var_export($i !== 2.0);
    var_export(2 !== 2.0);
    var_export($i !== $f);
    var_export($i !== []);
    var_export($i == 2.0);
    var_export(2 == 2.0);
    var_export($i == $f);
}
