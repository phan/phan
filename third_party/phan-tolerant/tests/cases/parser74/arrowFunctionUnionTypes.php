<?php
fn(int|false &$param1): int => $x;
fn(stdClass| $x): int|false => $x;
fn(iterable|stdClass $valid): \stdClass|bool|array => [$valid];
fn(iterable|stdClass ...): \stdClass| => $x;
fn(): namespace\stdClass|ArrayObject|iterable => $x;
