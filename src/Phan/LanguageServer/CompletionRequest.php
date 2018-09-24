<?php declare(strict_types=1);
namespace Phan\LanguageServer;

use Exception;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Prop;
use Phan\LanguageServer\Protocol\CompletionContext;
use Phan\LanguageServer\Protocol\CompletionItem;
use Phan\LanguageServer\Protocol\Position;
use Sabre\Event\Promise;

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
final class CompletionRequest
{
    /** @var string file URI */
    private $uri;
    /** @var string absolute path for $this->uri */
    private $path;
    /** @var Position */
    private $position;
    /** @var Promise|null */
    private $promise;
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
        $this->uri = $uri;
        $this->path = Utils::uriToPath($uri);
        $this->position = $position;
        $this->promise = new Promise();
        $this->completion_context = $completion_context;
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
        $item = $this->createCompletionItem($element);
        $this->completions[$item->insertText] = $item;
    }

    private function createCompletionItem(AddressableElementInterface $element) : CompletionItem
    {
        $item = new CompletionItem();
        $item->label = $this->labelForElement($element);
        $item->kind = $this->kindForElement($element);
        $item->detail = (string)$element->getUnionType();  // TODO: Better summary
        // TODO: Add documentation
        if ($element instanceof Prop && $element->isStatic()) {
        }
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

    /**
     * @suppress PhanUnreferencedPublicMethod TODO: Compare against the context->getPath() to be sure we're looking up the right node
     */
    public function getUrl() : string
    {
        return $this->uri;
    }

    public function getPath() : string
    {
        return $this->path;
    }

    public function getPosition() : Position
    {
        return $this->position;
    }

    /** @return ?Promise */
    public function getPromise()
    {
        return $this->promise;
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
