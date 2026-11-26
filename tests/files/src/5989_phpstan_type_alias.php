<?php

namespace NS5989;

use NS5989\Other\Mime as M;

/**
 * @phpstan-type Opts array{a: string, b?: int}
 * @psalm-type Id = int|string
 * @phpstan-type Multi array{
 *     channel: string,
 *     opts?: Opts,
 *     ids: list<Id>
 * }
 * @phpstan-type Handler callable( int, array<string, mixed> ): bool
 * @phpstan-type int string
 * @phpstan-type
 * @param UnknownAlias $x this line is reported at its own line
 */
class Declares {
    /**
     * @param Opts $o
     * @param Id $id
     * @param Handler $cb
     * @return Multi
     */
    public function f(array $o, $id, callable $cb): array {
        '@phan-debug-var $o, $id, $cb';
        return ['channel' => 'x', 'ids' => [$id]];
    }
}

/**
 * The aliases of another class can be imported, also when that class is declared later.
 *
 * @phpstan-import-type FileShape from File
 * @phpstan-import-type Listing from \NS5989\File as FileListing
 * @psalm-import-type Third from Deep
 * @phpstan-import-type Missing from File
 * @phpstan-import-type Gone from \NS5989\NoSuchClass
 * @phpstan-type Part array{text?: string, file?: FileShape}
 */
class Imports {
    /**
     * @param FileShape $f
     * @param FileListing $l
     * @param Third $t
     * @param Missing $m
     * @return Part
     */
    public function f(array $f, array $l, $t, $m): array {
        '@phan-debug-var $f, $l, $t, $m';
        return ['file' => $f];
    }
}

/**
 * @phpstan-import-type Third from Deep
 * @phpstan-type FileShape array{
 *     name: string,
 *     mime?: M,
 *     third: Third
 * }
 * @phpstan-type Listing array<string, FileShape>
 */
class File {
}

/** @psalm-type Third = int|false */
final class Deep {
}

namespace NS5989\Other;

class Mime {
}
