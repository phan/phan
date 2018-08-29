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

    private function __construct(string $message)
    {
        $this->message = $message;
    }

    public static function fromString(string $message) : Suggestion
    {
        return new self($message);
    }

    public function getMessage() : string
    {
        return $this->message;
    }
}
