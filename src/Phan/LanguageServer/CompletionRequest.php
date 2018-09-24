<?php declare(strict_types=1);
namespace Phan\LanguageServer;

use Exception;
use Phan\CodeBase;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Property;
use Phan\LanguageServer\Protocol\CompletionContext;
use Phan\LanguageServer\Protocol\CompletionItem;
use Phan\LanguageServer\Protocol\Position;

/**
 * Represents the Language Server Protocol's "Completion" request for an element
 * (property, method, class constant, etc.)
 *
 * @see https://microsoft.github.io/language-server-protocol/specification#textDocument_completion
 *
 * @see \Phan\LanguageServer\CompletionResolver for how this maps the found node to the type in the context.
 * @see \Phan\Plugin\Internal\NodeSelectionPlugin for how the node is found
 * @see \Phan\AST\TolerantASTConverter\TolerantASTConverterWithNodeMapping for how isSelected is set
 *
 * @phan-file-suppress PhanUnusedPublicMethodParameter
 */
final class CompletionRequest extends NodeInfoRequest
{
    /** @var CompletionContext|null */
    private $completion_context;

    /**
     * @var array<string,CompletionItem> the list of completion items.
     */
    private $completions = [];

    public function __construct(
        string $uri,
        Position $position,
        CompletionContext $completion_context = null
    ) {
        parent::__construct($uri, $position);
        $this->completion_context = $completion_context;
    }

    /**
     * @param ?Location|?array<int,Location> $locations
     * @return void
     */
    public function recordCompletionList($completions)
    {
        if ($completions instanceof CompletionItem || isset($completions['uri'])) {
            $completions = [$completions];
        }
        foreach ($completions ?? [] as $completion) {
            if (is_array($completion)) {
                $completion = CompletionItem::fromArray($completion);
            }
            $this->recordCompletionItem($completion);
        }
    }

    /**
     * Records the definition of an element that can be used for a code completion
     *
     * @param CodeBase $code_base used for resolving type location in "Go To Type Definition"
     * @return void
     */
    public function recordCompletionElement(
        CodeBase $code_base,
        AddressableElementInterface $element
    ) {
        $item = $this->createCompletionItem($code_base, $element);
        $this->recordCompletionItem($item);
    }

    private function recordCompletionItem(CompletionItem $item)
    {
        $this->completions[$item->insertText] = $item;
    }

    private function createCompletionItem(CodeBase $unused_code_base, AddressableElementInterface $element) : CompletionItem
    {
        $item = new CompletionItem();
        $item->label = $this->labelForElement($element);
        $item->kind = $this->kindForElement($element);
        $item->detail = (string)$element->getUnionType();  // TODO: Better summary
        // TODO: Add documentation
        // TODO: Migrate to the non-deprecated version
        if ($element instanceof Property && $element->isStatic()) {
            $item->insertText = '$' . $element->getName();
        }
        return $item;
    }

    private function labelForElement(AddressableElementInterface $element) : string
    {
        return "TODO:" . $element->getFQSEN();
    }

    /**
     * @return null placeholder
     */
    private function kindForElement(AddressableElementInterface $unused_element)
    {
        // TODO: Implement
        return null;
    }

    /**
     * @return array<int,CompletionItem>
     */
    public function getCompletions() : array
    {
        return array_values($this->completions);
    }

    public function finalize()
    {
        $promise = $this->promise;
        if ($promise) {
            $result = $this->completions ? array_values($this->completions) : null;
            $promise->fulfill($result);
            $this->promise = null;
        }
    }

    public function __destruct()
    {
        $promise = $this->promise;
        if ($promise) {
            $promise->reject(new Exception('Failed to send a valid textDocument/definition result'));
            $this->promise = null;
        }
    }
}
