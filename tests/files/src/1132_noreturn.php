<?php

namespace Point\Of;

abstract class Joke {
    abstract public function joke(): string;
}



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
