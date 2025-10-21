<?php
trait A {
    use A { A as protected public b; }
}