<?php

namespace ReservedClassNames5975;

use Foo5975\Bar as object;
use Foo5975\Baz as self;
use Foo5975\string;
use Foo5975\{Qux as void, Valid};
use Foo5975\Str as Text;
use function Foo5975\int;
use const Foo5975\MIXED;

class int {}
trait bool {}
interface float {}
enum Mixed {}
class Never {}
class iterable {}

class ExtendsSelf extends self {}
class ExtendsParent extends parent {}
class ImplementsStatic implements static {}
interface ExtendsSelfInterface extends self {}
enum EnumImplementsParent implements parent {}

class Integer {}
interface Stringable2 {}
class ValidClass extends Integer implements Stringable2
{
    public Valid|Text|null $valid = null;
    public function f(self $a, \Foo5975\Bar $b, ?Text $c, \self $d, Other\int $e): static
    {
        var_dump($a, $b, $c, $d, $e, int(), MIXED);
        return $this;
    }
    public Other\void $invalid;
}
