<?php
class V003Base
{
    /**
     * @param int[] $values
     */
    public function setUp(array $values): void {}
    public function hello(): string { return 'hi'; }
    public function bye(): string { return 'bye'; }
    public function stay(): string { return 'stay'; }
}
