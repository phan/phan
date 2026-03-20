<?php

class ConvergenceMiddle {
    function get() {
        return (new ConvergenceProvider())->provide();
    }
}
