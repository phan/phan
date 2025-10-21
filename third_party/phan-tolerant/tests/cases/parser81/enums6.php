<?php
// union type syntax is not allowed by php when parsing enum type.
enum Foo: int|string {
}
