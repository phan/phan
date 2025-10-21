<?php
trait A {
    use A { C insteadof D::d; }
}