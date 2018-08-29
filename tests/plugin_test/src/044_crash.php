<?php
// Phan should not crash
class example {
    private function validFunction() : void {
    };
    private function $notAFunction = function() : void {
    };
}
