<?php

/**
 * @throws OutOfBoundsException
 */
function throwOutOfBoundsException() {
    throw new OutOfBoundsException();
}

class ExampleThrow43 {
    /**
     * @throws InvalidArgumentException
     */
    public static function throwInvalidArgumentException() {
        throw new InvalidArgumentException();
    }

    public static function example() {
        if (rand() % 2 > 0) {
            throwOutOfBoundsException();
        }
        self::throwInvalidArgumentException();
    }

    public static function example2() {
        try {
            if (rand() % 2 > 0) {
                throwOutOfBoundsException();
            }
            self::throwInvalidArgumentException();
        } catch (OutOfBoundsException $e) {
            echo "Caught " . $e->getMessage();
        }
    }
}
ExampleThrow43::example();
ExampleThrow43::example2();
