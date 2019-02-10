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
use Phan\Language\Type\StaticOrSelfType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use Phan\LanguageServer\Protocol\Hover;
use Phan\LanguageServer\Protocol\Location;
use Phan\LanguageServer\Protocol\MarkupContent;
use Phan\LanguageServer\Protocol\Position;

use function count;
use function is_array;

/**
 * Represents the Language Server Protocol's "Go to Definition" or "Go to Type Definition" or "Hover" request for a usage of an Element
 * (class, property, function-like, constant, etc.)
 *
 * @see https://microsoft.github.io/language-server-protocol/specification#textDocument_definition
 * @see https://microsoft.github.io/language-server-protocol/specification#textDocument_typeDefinition
 * @see https://microsoft.github.io/language-server-protocol/specification#textDocument_hover
 *
 * @see \Phan\LanguageServer\DefinitionResolver for how this maps the found node to the type in the context.
 * @see \Phan\Plugin\Internal\NodeSelectionPlugin for how the node is found
 * @see \Phan\AST\TolerantASTConverter\TolerantASTConverterWithNodeMapping for how isSelected is set
 *
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
final class GoToDefinitionRequest extends NodeInfoRequest
{
    /** @var int self::REQUEST_* */
    private $request_type;

    /**
     * @var array<string,Location> the list of locations for a "Go to [Type] Definition" request
     */
    private $locations = [];

    /**
     * @var ?Hover the list of locations for a "Hover" request
     */
    private $hover_response = null;

    const REQUEST_DEFINITION = 0;
    const REQUEST_TYPE_DEFINITION = 1;
    const REQUEST_HOVER = 2;

    public function __construct(
        string $uri,
        Position $position,
        int $request_type
    ) {
        parent::__construct($uri, $position);
        $this->request_type = $request_type;
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
            if ($type instanceof StaticOrSelfType) {
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
            if ($type instanceof StaticOrSelfType) {
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

    /**
     * Record the location in which the Node or Token (that the client is requesting information about)
     * had the requested information defined (e.g. Definition, Type Definition, element that has information used to generate hover response, etc.)
     */
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
     * @param Location|array<string,mixed>|array<int,Location|array> $locations
     * @return void
     */
    public function recordDefinitionLocationList($locations)
    {
        if ($locations instanceof Location || isset($locations['uri'])) {
            $locations = [$locations];
        }
        foreach ($locations ?? [] as $location) {
            if (is_array($location)) {
                // @phan-suppress-next-line PhanPartialTypeMismatchArgument
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

    /**
     * @return ?Hover
     */
    public function getHoverResponse()
    {
        return $this->hover_response;
    }

    /**
     * Sets the only response for this hover request (with markdown to render)
     *
     * @param ?Hover|?array $hover
     */
    public function setHoverResponse($hover)
    {
        if (is_array($hover)) {
            $hover = Hover::fromArray($hover);
        }
        $this->hover_response = $hover;
    }

    /**
     * Clean up resources associated with this request.
     *
     * If a response for this request hasn't been sent yet, then send it (or null) back to the language server client
     */
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
     * Is this a "go to type definition" request?
     */
    public function getIsTypeDefinitionRequest() : bool
    {
        return $this->request_type === self::REQUEST_TYPE_DEFINITION;
    }

    /**
     * Is this a hover request?
     */
    public function getIsHoverRequest() : bool
    {
        return $this->request_type === self::REQUEST_HOVER;
    }

    public function __destruct()
    {
        $promise = $this->promise;
        if ($promise) {
            $promise->reject(new Exception('Failed to send a valid textDocument/completion result'));
            $this->promise = null;
        }
    }
}
