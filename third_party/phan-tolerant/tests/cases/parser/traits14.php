<?php
trait A {
    use A { A insteadof B\C; }
}