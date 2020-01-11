<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * A `MarkupContent` literal represents a string value which content is interpreted base on its
 * kind flag. Currently the protocol supports `plaintext` and `markdown` as markup kinds.
 *
 * If the kind is `markdown` then the value can contain fenced code blocks like in GitHub issues.
 * See https://help.github.com/articles/creating-and-highlighting-code-blocks/#syntax-highlighting
 *
 * Here is an example how such a string can be constructed using JavaScript / TypeScript:
 * ```ts
 * let markdown: MarkdownContent = {
 *  kind: MarkupKind.Markdown,
 *  value: [
 *      '# Header',
 *      'Some text',
 *      '```typescript',
 *      'someCode();',
 *      '```'
 *  ].join('\n')
 * };
 * ```
 *
 * *Please Note* that clients might sanitize the return markdown. A client could decide to
 * remove HTML from the markdown to avoid script execution.
 * @phan-file-suppress PhanUnreferencedPublicClassConstant, PhanWriteOnlyPublicProperty
 * @phan-immutable
 */
class MarkupContent
{
    // MarkupKind values
    public const PLAINTEXT = 'plaintext';
    public const MARKDOWN = 'markdown';

    /**
     * @var string the type of the Markup
     */
    public $kind;

    /**
     * @var string the content itself
     */
    public $value;

    public function __construct(string $kind, string $value)
    {
        $this->kind = $kind;
        $this->value = $value;
    }

    /**
     * Generates a MarkupContent from an unserialized data array.
     * @param array{kind:string,value:string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['kind'],
            $data['value']
        );
    }
}
