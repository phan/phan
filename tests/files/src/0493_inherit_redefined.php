<?php

class Base493 {public function foo() {}}
class Base493 {public function bar() {}}

interface I493 {public function abstract1();}
interface I493 {public function abstract2();}

trait T493 {}
trait T493 {}

class X493 extends Base493 implements I493 {
    use T493;
}
