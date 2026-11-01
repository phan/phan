<?php
// A nullsafe access on a receiver that is known to be null is short-circuited by PHP: the member is never accessed.
// With dead code detection, such an access must not mark every member with that name as referenced
// (the fallback for calls whose class can't be determined), so the unreferenced members below are still reported.
class Receiver323 {
    public int $prop323 = 1;
    public static int $static_prop323 = 2;
    public function method323(): int {
        return 3;
    }
    public static function static_method323(): int {
        return 4;
    }
    public function next323(): Receiver323 {
        return $this;
    }
}
function test_nullsafe_null_receiver323(): void {
    $n = null;
    var_export($n?->method323());
    var_export($n?->prop323);
    var_export($n?->next323()->method323());
    var_export($n?->next323()::static_method323());
    var_export($n?->next323()::$static_prop323);
}
test_nullsafe_null_receiver323();
