<?php
// TODO - handle this case better (skip semicolon - don't break out of parseList)
trait A {
    use A { };
}