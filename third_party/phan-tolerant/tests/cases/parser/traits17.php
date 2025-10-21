<?php
trait A {
    use A { C\B::d insteadof hi; }
}