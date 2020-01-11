<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * The kind of a completion entry.
 * @phan-file-suppress PhanUnreferencedPublicClassConstant all constants are added for completeness
 */
abstract class CompletionItemKind
{
    public const TEXT = 1;
    public const METHOD = 2;
    public const FUNCTION = 3;
    public const CONSTRUCTOR = 4;
    public const FIELD = 5;
    public const VARIABLE = 6;
    public const CLASS_ = 7;
    public const INTERFACE = 8;
    public const MODULE = 9;
    public const PROPERTY = 10;
    public const UNIT = 11;
    public const VALUE = 12;
    public const ENUM = 13;
    public const KEYWORD = 14;
    public const SNIPPET = 15;
    public const COLOR = 16;
    public const FILE = 17;
    public const REFERENCE = 18;
}
