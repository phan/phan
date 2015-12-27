<?php

interface TestInterface
{
    public function testFunction(): TestInterface;
}

class TestClass
{
    public function otherFunction()
    {
        $something = new self;

        if ($this instanceof TestInterface) {
            $something = $this->testFunction();
        }

        return $something;
    }

}

class TestAncestor extends TestClass implements TestInterface
{
    public function testFunction(): TestInterface
    {
        return $this;
    }
}

$ancestor = new TestAncestor();
$ancestor->otherFunction();