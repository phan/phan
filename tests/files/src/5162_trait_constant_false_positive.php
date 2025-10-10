<?php
// Test for issue #5162 - false positive PhanIncompatibleCompositionConstant

trait T1 {
    private const PRIV = 'private';
    protected const PROT = 'protected';
    public const PUB = 'public';
}

// This class should NOT trigger PhanIncompatibleCompositionConstant
// It's just using trait constants, not redefining them
class C1 {
    use T1;
}

// This class SHOULD trigger PhanIncompatibleCompositionConstant
// It's redefining a trait constant with incompatible visibility
class C2 {
    use T1;

    public const PRIV = 'public_redefinition';  // Error: incompatible with private
}

// This class should be OK - redefining with compatible visibility and value
class C3 {
    use T1;

    public const PUB = 'public';  // OK: same visibility and value
}

// Test with multiple traits
trait T2 {
    private const CONST_A = 'a';
}

trait T3 {
    private const CONST_B = 'b';
}

// Should be OK - each constant comes from a different trait
class C4 {
    use T2, T3;
}

// Should error - both traits define the same constant
trait T4 {
    private const CONFLICT = 'value1';
}

trait T5 {
    private const CONFLICT = 'value2';
}

class C5 {
    use T4, T5;  // Error: incompatible constants from two traits
}
