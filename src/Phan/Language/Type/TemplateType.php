<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

final class TemplateType extends Type
{
    /** @var string */
    private $template_type_identifier;

    /**
     * @param string $template_type_identifier
     * An identifier for the template type
     */
    public function __construct(
        string $template_type_identifier
    ) {
        $this->template_type_identifier = $template_type_identifier;
    }

    /**
     * @return string
     * The name associated with this type
     */
    public function getName() : string
    {
        return $this->template_type_identifier;
    }

    /**
     * @return string
     * A string representation of this type in FQSEN form.
     * @override
     */
    public function asFQSENString() : string
    {
        return $this->template_type_identifier;
    }

    /**
     * @return bool
     * True if this namespace is defined
     */
    public function hasNamespace() : bool
    {
        return false;
    }

    /**
     * @return string
     * The namespace associated with this type
     */
    public function getNamespace() : string
    {
        return '';
    }

    public function isObject() : bool
    {
        // Return true because we don't know, it may or may not be an object.
        // Not sure if this will be called.
        return true;
    }
}
