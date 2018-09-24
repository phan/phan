<?php declare(strict_types=1);
namespace Phan\LanguageServer;

use Exception;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\MarkupDescription;
use Phan\Language\Element\Variable;
use Phan\Language\FileRef;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use Phan\LanguageServer\Protocol\CompletionContext;
use Phan\LanguageServer\Protocol\Hover;
use Phan\LanguageServer\Protocol\Location;
use Phan\LanguageServer\Protocol\MarkupContent;
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
     * @param CodeBase $code_base used for resolving type location in "Go To Type Definition"
     * @return void
     */
    public function recordDefinitionElement(
        CodeBase $code_base,
        AddressableElementInterface $element,
        bool $resolve_type_definition_if_needed
    ) {
        if ($this->getIsTypeDefinitionRequest() && $resolve_type_definition_if_needed) {
            if (!($element instanceof Clazz)) {
                $this->recordTypeOfElement($code_base, $element->getContext(), $element->getUnionType());
                return;
            }
        }
        $this->recordFinalDefinitionElement($element);
    }

    private function recordFinalDefinitionElement(
        AddressableElementInterface $element
    ) {
        if ($this->request_type === self::REQUEST_HOVER) {
            if ($this->hover_response === null) {
                $this->setHoverMarkdown(MarkupDescription::buildForElement($element));
                // TODO: Support documenting more than one definition.
            }
            return;
        }
        $this->recordDefinitionContext($element->getContext());
    }

    private function setHoverMarkdown(string $markdown)
    {
        $this->hover_response = new Hover(
            new MarkupContent(
                MarkupContent::MARKDOWN,
                $markdown
            )
        );
    }

    /**
     * Precondition: $this->getIsHoverRequest()
     */
    private function recordHoverTextForElementType(
        CodeBase $code_base,
        Context $context,
        UnionType $union_type
    ) {
        $type_set = $union_type->getTypeSet();
        if (count($type_set) === 0) {
            // Don't bother generating hover text if there are no known types, maybe a subsequent call will have types
            return;
        }
        $maybe_set_markdown_to_union_type = function () use ($union_type) {
            if ($this->hover_response === null) {
                $this->setHoverMarkdown(sprintf('`%s`', (string)$union_type));
            }
        };
        if (count($type_set) >= 2) {
            $maybe_set_markdown_to_union_type();
            return;
        }

        // If there is exactly one known type, then if it is a class/interface type, show details about the class/interface for that type
        foreach ($type_set as $type) {
            if ($type->getIsNullable()) {
                continue;
            }
            if ($type instanceof TemplateType) {
                continue;
            }
            if ($type->isSelfType() || $type->isStaticType()) {
                if (!$context->isInClassScope()) {
                    // Phan already warns elsewhere
                    continue;
                }
                $type_fqsen = $context->getClassFQSEN();
            } else {
                // Get the FQSEN of the class or closure.
                $type_fqsen = $type->asFQSEN();
            }
            try {
                $this->recordDefinitionOfTypeFQSEN($code_base, $type_fqsen);
            } catch (CodeBaseException $_) {
                continue;
            }
        }

        if ($this->hover_response === null) {
            $maybe_set_markdown_to_union_type();
        }
    }

    /**
     * @param CodeBase $code_base used for resolving type location in "Go To Type Definition"
     * @param Context $context used for resolving 'self'/'static', etc.
     * @return void
     */
    public function recordDefinitionOfVariableType(
        CodeBase $code_base,
        Context $context,
        Variable $variable
    ) {
        $this->recordTypeOfElement($code_base, $context, $variable->getUnionType());
    }

    /**
     * @return void
     */
    private function recordTypeOfElement(
        CodeBase $code_base,
        Context $context,
        UnionType $union_type
    ) {
        if ($this->getIsHoverRequest()) {
            $this->recordHoverTextForElementType($code_base, $context, $union_type);
            return;
        }

        // Do something similar to the check for undeclared classes
        foreach ($union_type->getTypeSet() as $type) {
            if ($type instanceof TemplateType) {
                continue;
            }
            if ($type->isSelfType() || $type->isStaticType()) {
                if (!$context->isInClassScope()) {
                    // Phan already warns elsewhere
                    continue;
                }
                $type_fqsen = $context->getClassFQSEN();
            } else {
                // Get the FQSEN of the class or closure.
                $type_fqsen = $type->asFQSEN();
            }
            try {
                $this->recordDefinitionOfTypeFQSEN($code_base, $type_fqsen);
            } catch (CodeBaseException $_) {
                continue;
            }
        }
    }

    /**
     * @param FQSEN $type_fqsen the FQSEN of a type. FullyQualifiedClassName or FullyQualifiedFunctionName or FullyQualifiedMethodName (For closures/methods)
     * @throws CodeBaseException if codebase is somehow missing a definition
     */
    private function recordDefinitionOfTypeFQSEN(
        CodeBase $code_base,
        FQSEN $type_fqsen
    ) {
        $record_definition = function (AddressableElementInterface $element) use ($code_base) {
            if (!$element->isPHPInternal()) {
                if ($this->getIsHoverRequest()) {
                    $this->recordDefinitionElement($code_base, $element, false);
                } else {
                    $this->recordDefinitionContext($element->getContext());
                }
            }
        };
        if ($type_fqsen instanceof FullyQualifiedClassName) {
            if ($code_base->hasClassWithFQSEN($type_fqsen)) {
                $record_definition($code_base->getClassByFQSEN($type_fqsen));
            }
            return;
        }
        // Closures can be regular closures (FullyQualifiedFunctionName)
        // or Closures created from callables (Functions or methods)
        if ($type_fqsen instanceof FullyQualifiedFunctionName) {
            if ($code_base->hasFunctionWithFQSEN($type_fqsen)) {
                $record_definition($code_base->getFunctionByFQSEN($type_fqsen));
            }
            return;
        }
        if ($type_fqsen instanceof FullyQualifiedMethodName) {
            if ($code_base->hasMethodWithFQSEN($type_fqsen)) {
                $record_definition($code_base->getMethodByFQSEN($type_fqsen));
            }
            return;
        }
    }

    public function recordDefinitionContext(FileRef $context)
    {
        if ($context->isPHPInternal()) {
            // We don't have complete stubs to show the user for internal functions such as is_string(), etc.
            return;
        }
        $this->recordDefinitionLocation(Location::fromContext($context));
    }


    /**
     * @return void
     */
    public function recordDefinitionLocation(Location $location)
    {
        $this->locations[$location->uri . ':' . \json_encode($location->range)] = $location;
    }

    /**
     * @param ?Location|?array<int,Location> $locations
     * @return void
     */
    public function recordDefinitionLocationList($locations)
    {
        if ($locations instanceof Location || isset($locations['uri'])) {
            $locations = [$locations];
        }
        foreach ($locations ?? [] as $location) {
            if (is_array($location)) {
                $location = Location::fromArray($location);
            }
            $this->recordDefinitionLocation($location);
        }
    }

    /**
     * @return array<int,Location>
     */
    public function getDefinitionLocations() : array
    {
        return array_values($this->locations);
    }

    public function finalize()
    {
        $promise = $this->promise;
        if ($promise) {
            if ($this->request_type === self::REQUEST_HOVER) {
                $result = $this->hover_response;
            } else {
                $result = $this->locations ? array_values($this->locations) : null;
            }
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

    public function getIsTypeDefinitionRequest() : bool
    {
        return $this->request_type === self::REQUEST_TYPE_DEFINITION;
    }

    public function getIsHoverRequest() : bool
    {
        return $this->request_type === self::REQUEST_HOVER;
    }

    public function getIsCompletionRequest() : bool
    {
        return $this->request_type === self::REQUEST_COMPLETION;
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
