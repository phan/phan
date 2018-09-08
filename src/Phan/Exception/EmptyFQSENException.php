<?php declare(strict_types=1);
namespace Phan\Exception;

use Exception;

/**
 * Thrown to indicate that an empty FQSEN was used where a valid FQSEN was expected.
 */
class EmptyFQSENException extends Exception
{
    /** @var string */
    private $fqsen;

    /**
     * @param string $message
     * The error message
     */
    public function __construct(
        string $message,
        string $fqsen
    ) {
        parent::__construct($message);
        $this->fqsen = $fqsen;
    }

    public function getFQSEN() : string
    {
        return $this->fqsen;
    }
}
