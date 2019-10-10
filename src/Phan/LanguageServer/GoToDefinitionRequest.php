<?php declare(strict_types=1);

namespace Phan\LanguageServer;

use Exception;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\MarkupDescription;
use Phan\Language\Element\TypedElementInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FileRef;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type\StaticOrSelfType;
use Phan\Language\Type\TemplateType;
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
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod, PhanPluginNoCommentOnPublicMethod
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
     */
    public function recordDefinitionElement(
        CodeBase $code_base,
        AddressableElementInterface $element,
        bool $resolve_type_definition_if_needed
    ) : void {
        if ($this->isTypeDefinitionRequest() && $resolve_type_definition_if_needed) {
            if (!($element instanceof Clazz)) {
                $this->recordTypeOfElement($code_base, $element->getContext(), $element);
                return;
            }
        }
        $this->recordFinalDefinitionElement($code_base, $element);
    }

    private function recordFinalDefinitionElement(
        CodeBase $code_base,
        AddressableElementInterface $element
    ) : void {
        if ($this->request_type === self::REQUEST_HOVER) {
            if ($this->hover_response === null) {
                $this->setHoverMarkdown(MarkupDescription::buildForElement($element, $code_base));
                // TODO: Support documenting more than one definition.
            }
            return;
        }
        $this->recordDefinitionContext($element->getContext());
    }

    private function setHoverMarkdown(string $markdown) : void
    {
        $this->hover_response = new Hover(
            new MarkupContent(
                MarkupContent::MARKDOWN,
                $markdown
            )
        );
    }

    /**
     * Precondition: $this->isHoverRequest()
     */
    private function recordHoverTextForElementType(
        CodeBase $code_base,
        Context $context,
        TypedElementInterface $element
    ) : void {
        $union_type = $element->getUnionType();
        $type_set = $union_type->getTypeSet();
        $description = null;
        if ($element instanceof Variable) {
            $description = $this->getDescriptionOfVariable($code_base, $context, $element);
        }
        if (count($type_set) === 0) {
            if ($description) {
                $this->setHoverMarkdown($description);
            }
            // Don't bother generating hover text if there are no known types or descriptions, maybe a subsequent call will have types
            return;
        }
        $maybe_set_markdown_to_union_type = function () use ($union_type, $description) : void {
            if ($this->hover_response === null) {
                $markdown = \sprintf('`%s`', (string)$union_type);
                if ($description) {
                    $markdown = \sprintf("%s %s", $markdown, $description);
                }
                $this->setHoverMarkdown($markdown);
            }
        };
        if (count($type_set) >= 2) {
            $maybe_set_markdown_to_union_type();
            return;
        }

        // If there is exactly one known type, then if it is a class/interface type, show details about the class/interface for that type
        foreach ($type_set as $type) {
            if ($type->isNullable()) {
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
     * Based on https://secure.php.net/manual/en/reserved.variables.php
     */
    const GLOBAL_DESCRIPTIONS = [
        'argc' => 'The number of arguments passed to the script',
        'argv' => 'Array of arguments passed to the script. The first argument `$argv[0]` is always the name that was used to run the script.',
        '_COOKIE' => 'An associative array of variables passed to the current script via HTTP Cookies.',
        '_ENV' => 'An associative array of variables passed to the current script via the environment method.',
        '_FILES' => 'An associative array of items uploaded to the current script via the HTTP POST method.',
        '_GET' => 'An associative array of variables passed to the current script via the URL parameters (aka. query string).',
        '_GLOBALS' => 'References all variables available in global scope',
        '_POST' => 'An associative array of variables passed to the current script via the HTTP POST method when using *application/x-www-form-urlencoded* or *multipart/form-data* as the HTTP Content-Type in the request.',
        '_REQUEST' => 'An associative array that by default contains the contents of $_GET, $_POST and $_COOKIE.',
        '_SERVER' => 'An array containing information such as headers, paths, and script locations. The entries in this array are created by the web server.',
        '_SESSION' => 'An associative array containing session variables available to the current script.',
    ];

    public function getDescriptionOfVariable(
        CodeBase $code_base,
        Context $context,
        Variable $variable
    ) : ?string {
        $variable_name = $variable->getName();
        $description = self::GLOBAL_DESCRIPTIONS[$variable_name] ?? null;
        if ($description) {
            return $description;
        }
        if (!$context->isInFunctionLikeScope()) {
            return null;
        }
        $function = $context->getFunctionLikeInScope($code_base);
        // TODO(optional): Use inheritance to find descriptions for the corresponding parameters of ancestor classes/interfaces
        // TODO(optional): Could support (at)var
        $param_tags = MarkupDescription::extractParamTagsFromDocComment($function, false);
        $variable_description = $param_tags[$variable_name] ?? null;
        if (!$variable_description) {
            return null;
        }
        // Remove the first part of '`@param int $x` description'
        $variable_description = \preg_replace('@^`[^`]*`\s*@', '', $variable_description);
        if (!$variable_description) {
            return null;
        }
        return $variable_description;
    }

    /**
     * @param CodeBase $code_base used for resolving type location in "Go To Type Definition"
     * @param Context $context used for resolving 'self'/'static', etc.
     */
    public function recordDefinitionOfVariableType(
        CodeBase $code_base,
        Context $context,
        Variable $variable
    ) : void {
        $this->recordTypeOfElement($code_base, $context, $variable);
    }

    private function recordTypeOfElement(
        CodeBase $code_base,
        Context $context,
        TypedElementInterface $element
    ) : void {
        if ($this->isHoverRequest()) {
            $this->recordHoverTextForElementType($code_base, $context, $element);
            return;
        }
        $union_type = $element->getUnionType();

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
    ) : void {
        $record_definition = function (AddressableElementInterface $element) use ($code_base) : void {
            if (!$element->isPHPInternal()) {
                if ($this->isHoverRequest()) {
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
    public function recordDefinitionContext(FileRef $context) : void
    {
        if ($context->isPHPInternal()) {
            // We don't have complete stubs to show the user for internal functions such as is_string(), etc.
            return;
        }
        $this->recordDefinitionLocation(Location::fromContext($context));
    }


    public function recordDefinitionLocation(Location $location) : void
    {
        $this->locations[$location->uri . ':' . \json_encode($location->range)] = $location;
    }

    /**
     * @param Location|array<string,mixed>|list<Location|array> $locations
     */
    public function recordDefinitionLocationList($locations) : void
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
     * @return list<Location>
     */
    public function getDefinitionLocations() : array
    {
        return \array_values($this->locations);
    }

    public function getHoverResponse() : ?Hover
    {
        return $this->hover_response;
    }

    /**
     * Sets the only response for this hover request (with markdown to render)
     *
     * @param ?Hover|?array $hover
     */
    public function setHoverResponse($hover) : void
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
    public function finalize() : void
    {
        if ($this->fulfilled) {
            return;
        }
        $this->fulfilled = true;
        if ($this->request_type === self::REQUEST_HOVER) {
            $result = $this->hover_response;
        } else {
            $result = $this->locations ? \array_values($this->locations) : null;
        }
        $this->promise->fulfill($result);
    }

    /**
     * Is this a "go to type definition" request?
     */
    public function isTypeDefinitionRequest() : bool
    {
        return $this->request_type === self::REQUEST_TYPE_DEFINITION;
    }

    /**
     * Is this a hover request?
     */
    public function isHoverRequest() : bool
    {
        return $this->request_type === self::REQUEST_HOVER;
    }

    public function __destruct()
    {
        if ($this->fulfilled) {
            return;
        }
        $this->fulfilled = true;
        $this->promise->reject(new Exception('Failed to send a valid textDocument/completion result'));
    }
}
