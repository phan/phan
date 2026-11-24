<?php
trait SemiReservedTrait {
    function f() {}
    function list() {}
}
class SemiReservedUser {
    use SemiReservedTrait {
        f as list;
        list as protected;
        SemiReservedTrait::f as public array;
        f as private;
        f as public static;
        f as protected namespace;
    }
}
class SemiReservedUser2 {
    use SemiReservedTrait {
        SemiReservedTrait::list as print;
        list
            as
            public
            function;
    }
}
