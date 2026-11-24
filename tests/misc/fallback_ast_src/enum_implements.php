<?php
interface HasLabel { public function label(): string; }
enum Suit: string implements HasLabel, \JsonSerializable {
    case Hearts = "h";
    public function label(): string { return $this->value; }
    public function jsonSerialize(): mixed { return $this->value; }
}
enum Unit implements HasLabel {
    case A;
    public function label(): string { return "a"; }
}
