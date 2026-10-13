<?php
// Regression test: when a variable has an unknown type on one branch and a known type on another,
// the merged type must keep the unknown (as `mixed`, like the ternary operator does) instead of
// becoming the known type alone: `$x = null; if ($c) { $x = unknown(); }` is not `null`.
class Results5949 {
    /** @param string[][][] $json */
    public function __construct($json) { var_export($json); }
}
class Client5949 {
    /** The type of this property is unknown: it has no type and is never assigned. */
    private $untyped;

    public static function passThrough($p) { return $p; }

    public function viaIf(bool $c): void {
        $response = null;
        if ($c) {
            $response = $this->untyped;
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
    public function viaIfElse(bool $c, int $i): void {
        if ($c) {
            $response = $i;
        } else {
            $response = $this->untyped;
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
    public function viaTry(): void {
        $response = null;
        try {
            $response = $this->untyped;
        } catch (Exception $e) {
            echo "failed";
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
    public function viaTryAssignedInCatch(): void {
        $response = $this->untyped;
        try {
            echo "x";
        } catch (Exception $e) {
            $response = null;
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
    public function viaForeach(array $a): void {
        $response = null;
        foreach ($a as $_) {
            $response = $this->untyped;
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
    public function viaSwitch(int $i, $q): void {
        $response = null;
        switch ($i) {
            case 1:
                $response = self::passThrough($q);
                break;
            case 2:
                $response = 'two';
                break;
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
    public function viaDynamicCall(bool $c, object $o, string $m): void {
        $response = null;
        if ($c) {
            $response = $o->{$m}();
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
    /** The ternary already kept the unknown type before this change. */
    public function viaTernary(bool $c): void {
        $response = $c ? $this->untyped : null;
        '@phan-debug-var $response';
        new Results5949($response);
    }
    /** Both branches known: unchanged. */
    public function bothKnown(bool $c): void {
        $response = null;
        if ($c) {
            $response = 'known';
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
    /** Both branches unknown: stays unknown, no `mixed` is added. */
    public function bothUnknown(bool $c, $q): void {
        $response = $this->untyped;
        if ($c) {
            $response = self::passThrough($q);
        }
        '@phan-debug-var $response';
        new Results5949($response);
    }
}
