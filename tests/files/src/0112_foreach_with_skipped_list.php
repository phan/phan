<?php
// should warn about empty foreach. Should not crash due to empty list assignment.
foreach ([] as list(, $a));
