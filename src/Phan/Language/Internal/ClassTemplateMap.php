<?php

declare(strict_types=1);

namespace Phan\Language\Internal;

/**
 * Template metadata for PHP internal classes.
 * @phan-file-suppress PhanPluginMixedKeyNoKey intentionally using numeric array format matching FunctionSignatureMap
 *
 * This map provides template parameter information for internal classes
 * that behave like generic containers but don't expose this information
 * through reflection.
 *
 * Format (similar to FunctionSignatureMap):
 * '<ClassName>' => [
 *   '@template' => ['TParam1' => 'ConstraintType', 'TParam2' => 'DefaultType'],
 *   '@implements' => ['Interface<TParam1,TParam2>', ...],
 *   'methodName' => ['returnType', 'param1'=>'Type1', 'param2='=>'OptionalType2'],
 * ]
 *
 * Only methods that use template parameters need to be listed.
 * Other methods will use their reflected signatures.
 *
 * @phan-file-suppress PhanUnreferencedPublicClassConstant
 */
class ClassTemplateMap
{
    public const TEMPLATE_MAP = [
        'SplObjectStorage' => [
            '@template' => ['TObject' => 'object', 'TData' => 'mixed'],
            '@implements' => ['ArrayAccess<TObject,TData>', 'Iterator<int,TObject>'],
            'attach' => ['void', 'object' => 'TObject', 'info=' => 'TData|null'],
            'offsetGet' => ['TData', 'object' => 'TObject'],
            'offsetSet' => ['void', 'object' => 'TObject', 'info=' => 'TData|null'],
            'offsetExists' => ['bool', 'object' => 'TObject'],
            'offsetUnset' => ['void', 'object' => 'TObject'],
            'current' => ['TObject'],
            'getInfo' => ['TData'],
            'setInfo' => ['void', 'data' => 'TData'],
            'detach' => ['void', 'object' => 'TObject'],
            'contains' => ['bool', 'object' => 'TObject'],
            'addAll' => ['int', 'storage' => 'SplObjectStorage<TObject,TData>'],
            'removeAll' => ['int', 'storage' => 'SplObjectStorage<TObject,TData>'],
            'removeAllExcept' => ['int', 'storage' => 'SplObjectStorage<TObject,TData>'],
        ],

        'WeakMap' => [
            '@template' => ['TKey' => 'object', 'TValue' => 'mixed'],
            '@implements' => ['ArrayAccess<TKey,TValue>', 'IteratorAggregate<TKey,TValue>', 'Countable'],
            'offsetGet' => ['TValue', 'object' => 'TKey'],
            'offsetSet' => ['void', 'object' => 'TKey', 'value' => 'TValue'],
            'offsetExists' => ['bool', 'object' => 'TKey'],
            'offsetUnset' => ['void', 'object' => 'TKey'],
            'getIterator' => ['Iterator<TKey,TValue>'],
        ],

        'ArrayObject' => [
            '@template' => ['TKey' => 'int|string', 'TValue' => 'mixed'],
            '@implements' => ['IteratorAggregate<TKey,TValue>', 'ArrayAccess<TKey,TValue>', 'Countable'],
            'offsetGet' => ['TValue|null', 'key' => 'TKey'],
            'offsetSet' => ['void', 'key' => 'TKey|null', 'value' => 'TValue'],
            'offsetExists' => ['bool', 'key' => 'TKey'],
            'offsetUnset' => ['void', 'key' => 'TKey'],
            'append' => ['void', 'value' => 'TValue'],
            'getIterator' => ['ArrayIterator<TKey,TValue>'],
        ],

        'ArrayIterator' => [
            '@template' => ['TKey' => 'int|string', 'TValue' => 'mixed'],
            '@implements' => ['SeekableIterator<TKey,TValue>', 'ArrayAccess<TKey,TValue>', 'Countable'],
            'offsetGet' => ['TValue', 'key' => 'TKey'],
            'offsetSet' => ['void', 'key' => 'TKey', 'value' => 'TValue'],
            'offsetExists' => ['bool', 'key' => 'TKey'],
            'offsetUnset' => ['void', 'key' => 'TKey'],
            'current' => ['TValue'],
            'key' => ['TKey'],
            'append' => ['void', 'value' => 'TValue'],
        ],

        'SplFixedArray' => [
            '@template' => ['TValue' => 'mixed'],
            '@implements' => ['IteratorAggregate<int,TValue>', 'ArrayAccess<int,TValue>', 'Countable'],
            'offsetGet' => ['TValue', 'index' => 'int'],
            'offsetSet' => ['void', 'index' => 'int', 'value' => 'TValue'],
            'offsetExists' => ['bool', 'index' => 'int'],
            'offsetUnset' => ['void', 'index' => 'int'],
            'current' => ['TValue'],
            'getIterator' => ['Iterator<int,TValue>'],
            'fromArray' => ['SplFixedArray<TValue>', 'array' => 'array<int,TValue>', 'preserve_keys=' => 'bool'],
        ],
    ];

    /**
     * Returns the template metadata for a given class name.
     *
     * @return ?array<string,mixed>
     */
    public static function getTemplateMapForClass(string $class_name): ?array
    {
        return self::TEMPLATE_MAP[$class_name] ?? null;
    }

    /**
     * Checks if a class has template metadata.
     */
    public static function hasTemplateMetadata(string $class_name): bool
    {
        return isset(self::TEMPLATE_MAP[$class_name]);
    }

    /**
     * Returns all class names that have template metadata.
     *
     * @return list<string>
     */
    public static function getClassNames(): array
    {
        return \array_keys(self::TEMPLATE_MAP);
    }
}
