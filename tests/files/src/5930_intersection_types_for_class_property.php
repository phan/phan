<?php

interface A {}
interface B {}

class ParentIntersectionClass {
    public A&B $parentVar;
}

class ChildIntersectionClass extends ParentIntersectionClass {
    public A&B $parentVar;
}
