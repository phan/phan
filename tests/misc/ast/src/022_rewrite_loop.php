<?php

while ($x && $y) {
    var_export($x);
    var_export($y);
}

for ($i = 0; true, $i < 2 && is_string($x); $i++) {
    var_export([$i, $x]);
}

while (!!$x) {
}

for (;$i=0,!!$x;) {
}
