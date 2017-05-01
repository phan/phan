<?php
// Tests for issue #510
namespace A510\Inner {
    function lowercasefn() {}
    function mixedCaseFn() {}
    function GroupUseFn() {}
    const C = 42;
    const D = 24;
    const Foo = 24;
    const bar = 24;
}

namespace B510 {
    use A510 as B510_Alias;
    use A510\Inner;  // TODO: Aliases?
    use \A510\Inner\{function GroupUseFn, function MissingGroupUseFn};
    function f510() {
        B510_Alias\Inner\lowercaseFn();
        B510_Alias\Inner\missingThisFn();
        groupUseFn();  // fine
        MissingGroupUseFn();  // should report an error
        mixedCaseFn();  // should fail, this was not imported
        A510\Inner\mixedCaseFn();
        Inner\mixedCaseFn();
        Inner\missingFn();  // should error
    }
}
