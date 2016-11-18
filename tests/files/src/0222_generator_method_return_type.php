<?php

class GeneratorChecks {
    function bad_generator() : Generator {
        return 1;  // Should print an error
    }

    function bad_generator2() : Iterator {
        return 1;  // Should print an error
    }

    function bad_generator3() : Traversable {
        return 1;  // Should print an error
    }

    /** @return int */
    function bad_generator4() {
        yield;
        return 1;  // Should print an error
    }

    function good_generator() : Generator {
        yield 1;
        return 1;
    }

    function good_generator2() : Generator {
        return self::good_generator();
    }

    function good_generator3() : Traversable {
        if (rand()) { return 1; }
        yield;
    }

    /** @return Traversable */
    function good_generator3b() {
        if (rand()) { return 1; }
        yield;
    }

    function good_generator3c() : Generator {
        if (rand()) { return 1; }
        yield from self::good_generator();
    }

    function good_generator4() : Generator {
        return (yield from self::good_generator());
    }

    /** @return Iterator */
    function good_generator5() {
        yield (new stdClass());
    }
}
