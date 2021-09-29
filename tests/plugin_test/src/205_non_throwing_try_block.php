<?php
function examples205(int $flag, bool $x): bool {
    try {
        if ($x) {
            return true;
        }
    } catch (Exception $_) {
    }
    try {
        return $flag > 0;
    } catch (Exception $_) {
        echo "done\n";
    }
    for ($i = 0; $i < 10; $i++) {
        try {
            if ($i > 5) {
                echo "Found\n";
                break;
            }
        } catch (Exception $_) {
        }
    }
    return false;
}
examples205(1, false);
