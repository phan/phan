<?php

namespace NS5990\DTO;

use NS5990\Other\Tag5990 as Tag;

'@phan-type Id = int';

/**
 * Example in the text: @phpstan-type Token int
 *
 * @phpstan-type ItemShape array{
 *     name: string,
 *     tag?: Tag,
 *     owner: self
 * }
 * @phpstan-type Owner self
 * @phpstan-type Payload array{id: Id}
 * @phpstan-type Token string
 */
class Provider5990 {
}

/** @phpstan-type X float */
class Source5990 {
}

/**
 * An alias that this class imported is known to MutualB5990, which this class imports from further down.
 *
 * @phpstan-import-type X from Source5990
 * @phpstan-import-type BType from MutualB5990
 */
class MutualA5990 {
}

/**
 * @phpstan-type BType string
 * @phpstan-import-type X from MutualA5990
 */
class MutualB5990 {
}
