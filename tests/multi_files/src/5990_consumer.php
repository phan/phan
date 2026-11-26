<?php

namespace NS5990;

use NS5990\DTO\Provider5990;

/**
 * The file of Provider5990 is parsed after this one.
 *
 * @phpstan-import-type ItemShape from Provider5990
 * @phpstan-import-type Owner from Provider5990
 * @phpstan-import-type Payload from Provider5990
 * @phpstan-import-type Token from Provider5990
 * @phpstan-import-type Guarded from \NS5990\DTO\Guarded5990
 * @phpstan-import-type X from \NS5990\DTO\MutualA5990
 */
class Consumer5990 {
    /**
     * @param ItemShape $item
     * @param Owner $owner
     * @param Guarded $guarded
     * @param Payload $payload
     * @param Token $token
     * @param X $x
     */
    public function f(array $item, $owner, array $guarded, array $payload, $token, $x): void {
        '@phan-debug-var $item, $owner, $guarded, $payload, $token, $x';
    }
}
