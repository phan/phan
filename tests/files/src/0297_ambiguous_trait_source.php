<?php

class Foo297 {
    use X297, Y297 {
        // Could be from X297 or Y297, and Phan may not have finished parsing,
        // so just give up on analyzing and warn for now.
        foo as bar;
    }
}

trait X297{}
trait Y297{}
