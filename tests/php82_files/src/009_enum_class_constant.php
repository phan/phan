<?php

enum EnumCase09 {
    case Single;
}

class ValidDocs09 {
    /** @var EnumCase09 */
    public const VALID = EnumCase09::Single;

    /** @var array<int|string, array<int, string|EnumCase09>> */
    public const ALSO_VALID = [[EnumCase09::Single]];
}

class InvalidDocs09 {
    /** @var EnumCase09|stdClass */
    public const NOT_VALID = EnumCase09::Single;

    /** @var stdClass|EnumCase09 */
    public const ALSO_NOT_VALID = EnumCase09::Single;
}

var_dump(
    ValidDocs09::VALID->name,
    ValidDocs09::ALSO_VALID[0][0]->name,
    InvalidDocs09::NOT_VALID->name,
    InvalidDocs09::ALSO_NOT_VALID->name
);
