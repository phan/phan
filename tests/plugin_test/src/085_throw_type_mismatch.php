<?php declare(strict_types=1);

namespace NS626;

use InvalidArgumentException;
use RuntimeException;
use TypeError;

final class FinalClass85 {}

class C {
    /**
     * @throws InvalidArgumentException
     */
    public function main() {
        if (rand() % 2 > 0) {
            throw new RuntimeException("odd");
        } elseif (rand() % 3 > 0) {
            throw null;
        } elseif (rand() % 4 > 0) {
            throw new FinalClass85();
        }
        $this->throw();
    }

    /**
     * @throws TypeError
     */
    public function throw() {
        throw new TypeError("error");
    }
}
(new C())->main();
