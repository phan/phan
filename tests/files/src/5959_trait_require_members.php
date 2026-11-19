<?php

namespace NS5959;

abstract class Base
{
    public string $baseProp = '';
    protected static int $instances = 0;
    private int $secret = 0;

    public function baseMethod(string $p): void {}
    protected static function protectedStatic(): void {}
    private function hidden(): void {}
    abstract public function mustImplement(): void;
}

interface Factory
{
    public static function make(): static;
}

/**
 * @require-extends Base
 */
trait RequiresBase
{
    public function useBase(): int
    {
        $this->baseMethod($this->baseProp);
        self::$instances++;
        static::$instances++;
        self::protectedStatic();
        static::protectedStatic();
        $this->hidden();  // private to Base, so not accessible from a subclass
        return $this->secret;
    }
}

/**
 * @require-implements Factory
 */
trait RequiresFactory
{
    public static function build(): static
    {
        return static::make();
    }

    public function rebuild(): static
    {
        return self::make();
    }
}

/**
 * @require-implements \BackedEnum
 */
trait EnumHelpers
{
    public static function fromOrFirst(string $value): static
    {
        return self::tryFrom($value) ?? self::cases()[0];
    }

    public function label(): string
    {
        return $this->name . '=' . $this->value;
    }
}

enum Color: string
{
    use EnumHelpers;

    case Red = 'red';
}

final class ValidChild extends Base
{
    use RequiresBase;

    public function mustImplement(): void {}
}

final class ValidFactory implements Factory
{
    use RequiresFactory;

    public static function make(): static
    {
        return new static();
    }
}

// The members of the required class are not copied to classes using the trait without extending it
class InvalidChild
{
    use RequiresBase;
}

class InvalidFactory
{
    use RequiresFactory;
}

function test(ValidChild $valid, InvalidChild $invalid): void
{
    $valid->baseMethod($valid->baseProp);
    $invalid->baseMethod($invalid->baseProp);
    ValidFactory::build()->rebuild();
    InvalidFactory::make();
    echo Color::fromOrFirst('red')->label();
}

class Counter
{
    public int $count = 0;
}

/**
 * @require-extends Counter
 */
trait UsesCounter
{
    public function next(): int
    {
        return ++$this->count;
    }
}

// Reported against Counter::$count only, not also against the trait, which does not declare $count
final class BadCounter extends Counter
{
    use UsesCounter;

    public string $count = '';
}

// Not reported, for the same reason
final class NotACounter
{
    use UsesCounter;

    public string $count = '';
}

class Model
{
    public static function make(): static
    {
        return new static();
    }

    public function touch(): static
    {
        return $this;
    }
}

function takesModel(Model $model): void {}

/**
 * @require-extends Model
 */
trait ModelHelpers
{
    // $this, self and static are instances of the required class
    public static function build(): Model
    {
        return static::make();
    }

    public function touched(): Model
    {
        takesModel($this);
        takesModel($this->touch());
        return $this;
    }
}

/**
 * @require-extends Model
 */
trait ModelFactory
{
    public static function __callStatic(string $name, array $args): Model
    {
        throw new \RuntimeException($name);
    }

    public static function build(): Model
    {
        return self::make();
    }
}

final class Post extends Model
{
    use ModelHelpers;
}

final class Page extends Model
{
    use ModelFactory;
}
