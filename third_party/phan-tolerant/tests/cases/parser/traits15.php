<?php
trait A {
    use A { C::c insteadof D; }
}