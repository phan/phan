<?php
trait A {
    use A { A as B; }
}