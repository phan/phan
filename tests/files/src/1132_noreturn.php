<?php

namespace Point\Of;

abstract class Joke {
    abstract public function joke(): string;
}

// Note that minimum_target_php_version must be '7.4' or newer
// in order for a method to be overridden with a subtype
class AntiJoke extends Joke {
    public function joke(): never {  // this is valid in php 8.1
        exit(1);
    }
}

class Up extends AntiJoke {
    public function joke(): never {
        throw new \Exception('Up: going to give you "never"');
    }
}

class BadJoke extends AntiJoke {
    public function joke(): string {  // it is an error to replace a return type of never in an override
        return 'run around';
    }
}

function up(): never {
    if (rand(0, 1)) {
        return 'dog';
    }
    return;
}
