<?php
trait A13 {
    use A13 { A13 insteadof B; C as D; }
}