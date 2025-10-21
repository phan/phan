<?php
trait A {
    use A { A as public b; }
}