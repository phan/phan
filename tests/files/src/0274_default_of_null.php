<?php

class DefaultNullCheck{
    public function foo() {
        return "In A\n";
    }
    public static function expects_nullable_1(string $tag, DefaultNullCheck $a = null) {
        $isDefined = $a != null;
        if ($isDefined) {
            echo $a->foo();
        }
    }

    public static function expects_nullable_2(string $tag, DefaultNullCheck $a = null) {
        $isDefined = $a != null;
        if ($isDefined) {
            echo $a->foo();
        }
    }
}
DefaultNullCheck::expects_nullable_1('tag', new DefaultNullCheck());  // This alone won't cause an error
DefaultNullCheck::expects_nullable_1('tag', null);  // won't cause an error when analyzed. This may change
DefaultNullCheck::expects_nullable_2('tag', new DefaultNullCheck());  // This alone won't cause an error
DefaultNullCheck::expects_nullable_2('tag');        // should be consistent with expect_nullable_1 now and in the future.
