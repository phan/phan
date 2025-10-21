<?php
trait A {
    use A { A\B insteadof B; }
}