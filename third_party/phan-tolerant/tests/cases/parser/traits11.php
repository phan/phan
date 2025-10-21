<?php
// TODO consider being more tolerant of this scenario
trait A {
    use A { A as B, B as C; }
}
