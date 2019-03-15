<?php declare(strict_types=1);

namespace NS626;

use InvalidArgumentException;
use RuntimeException;
use TypeError;

class C {
    /**
     * @throws InvalidArgumentException
     */
    public function main() {
        if (rand() % 2 > 0) {
            throw new RuntimeException("odd");
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
