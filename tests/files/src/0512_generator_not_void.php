<?php
class X512 {
    use Base512;
    /**
     * @return Generator
     */
    public static function example() {
        return self::myGenerator();
    }
}

trait Base512 {
    private static function myGenerator() {
        yield 2 => 3;
    }
}
X512::example();
