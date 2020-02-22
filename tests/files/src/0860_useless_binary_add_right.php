<?php
class Example860 {
    const W = [2] + [3];
    /**
     * @suppress PhanUselessBinaryAddRight
     */
    const X = [2] + [3];

    public static $x = [2] + [3];
    /**
     * @suppress PhanUselessBinaryAddRight
     */
    public static $y = [2] + [3];
}
// Note that php-ast does not provide doc comments for global constant, because php doesn't track that.
// E.g. ReflectionClassConstant exists, but ReflectionGlobalConstant does not
const W = [2] + [3];
function muda860($x = [2] + [3]) {
}
