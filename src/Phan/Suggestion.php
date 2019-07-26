<?php declare(strict_types=1);

namespace Phan;

/**
 * This may be extended later to support the language server protocol
 * (E.g. will contain a representation of 1 or more edit that would actually fix the UndeclaredVariable error)
 */
final class Suggestion
{
    /** @var string the text of the suggestion */
    private $message;

    /** @var mixed internal data not shown in error messages */
    private $internal_data;

    private function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     * Create a Suggestion suggesting $message
     */
    public static function fromString(string $message) : Suggestion
    {
        return new self($message);
    }

    /**
     * Contains the text of the suggestion to fix the issue
     */
    public function getMessage() : string
    {
        return $this->message;
    }

    /**
     * Contains the text of the suggestion to fix the issue
     */
    public function __toString() : string
    {
        return $this->message;
    }

    /**
     * Sets additional data.
     * This can be used by plugins implementing --automatic-fix, for example.
     * (Create a Suggestion with the empty string if the suggestion should not be visible in error messages)
     *
     * @param mixed $data
     * @suppress PhanUnreferencedPublicMethod
     */
    public function setInternalData($data) : void
    {
        $this->internal_data = $data;
    }

    /**
     * Gets additional data.
     * This can be used by plugins implementing --automatic-fix, for example.
     * @return mixed
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getInternalData()
    {
        return $this->internal_data;
    }
}
