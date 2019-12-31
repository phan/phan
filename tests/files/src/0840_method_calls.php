<?php

class Test840 {
    /** @return string */
    public static function returns_string() : string {
        return 'prefix';
    }

    public function main() : ?array {
        $result = null;
        foreach (['e1', 'e2'] as $value) {
            if (isset($result)) {
                // Should infer that $result must be a string and warn.
                echo spl_object_hash($result);
                $result .= $value;
                continue;
            }
            $result = self::returns_string();
        }
        return $result;
    }

    public function main2() : ?array {
        $result = null;
        foreach (['e1', 'e2'] as $value) {
            if (isset($result)) {
                // Should infer that $result must be a string and warn.
                echo spl_object_hash($result);
                $result .= $value;
                continue;
            }
            $result = $this->returns_string();
        }
        return $result;
    }
}
