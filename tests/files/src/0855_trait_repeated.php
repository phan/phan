<?php
/** @method static string magicMethod() */
trait HasMethod {
    public static function actualMethod();
}
class UsesTrait {
    // Both 'use' aliases should warn.
    // Note that php allows 'use TraitName' to be repeated.
    use HasMethod { magicMethod as alias; }
    use HasMethod { missingMethod as alias2; }
}
UsesTrait::alias();
UsesTrait::alias2();
UsesTrait::actualMethod('extra');

