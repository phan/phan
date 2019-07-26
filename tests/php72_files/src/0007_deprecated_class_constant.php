<?php
class O7 {
    /** @deprecated */
    const VALUE = 7;

    const OTHER = self::VALUE + 8;
}
var_export(O7::VALUE);
