<?php
interface T
{
     /** @return iterable<int> */
     public function f(): iterable;

     /** @return iterable<int> should warn */
     public function g(): Traversable;

     /** @return iterable<int> should warn */
     public function h(): int;
}
