<?php

/** @phan-file-suppress PhanUnreferencedPublicClassConstant */

// Equal constant values in two traits are compatible whatever line they are on.
trait T1 { public const C = [1, 2]; public const D = null; public const E = 1 + 1; public const F = 'x'; }
trait T2 {
    public const C = [1, 2];
    public const D = null;
    public const E = 1 + 1;
    public const F = 'x';
}
/** @phan-suppress-next-line PhanUnreferencedClass */
class B { use T1, T2; }

// Different values are still reported.
trait T3 { public const G = [1, 2]; }
trait T4 {
    public const G = [1, 3];
}
/** @phan-suppress-next-line PhanUnreferencedClass */
class B2 { use T3, T4; }
