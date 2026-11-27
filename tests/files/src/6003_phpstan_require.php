<?php
namespace NS6003\Contracts;
/** @template T */
interface Policy { public static function requirements(): array; public function name(): string; }
abstract class Base { public function id(): int { return 1; } }

namespace NS6003;
use NS6003\Contracts\Policy;
use NS6003\Contracts\Base;
/**
 * @template T of mixed
 *
 * @phpstan-require-implements Policy<T>
 */
trait Compare {
    public static function all(): array { return static::requirements(); }
    public function label(): string { return $this->name() . $this->missing(); }
}
/** @phpstan-require-extends Base */
trait Ident {
    public function ident(): int { return $this->id(); }
}
/** @PHPStan-Require-Implements \NS6003\Contracts\Policy<int> */
trait Cased { public function n(): string { return $this->name(); } }
/** @phpstan-require-implements int */
trait Bad {}
final class NotBase { use Ident; }
final class IsBase extends Base { use Ident; }
