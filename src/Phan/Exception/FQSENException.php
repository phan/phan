<?php declare(strict_types=1);

namespace Phan\Exception;

use Exception;

/**
 * Thrown to indicate that an empty/invalid FQSEN was used where a valid FQSEN was expected.
 * @see InvalidFQSENException
 * @see EmptyFQSENException
 */
class FQSENException extends Exception
{
    /** @var string the empty/invalid, unparseable FQSEN */
    private $fqsen;

    /**
     * @param string $message
     * The error message
     * @param string $fqsen
     * the empty/invalid, unparseable FQSEN
     */
    public function __construct(
        string $message,
        string $fqsen
    ) {
        parent::__construct($message . " for FQSEN '$fqsen'");
        $this->fqsen = $fqsen;
    }

    /**
     * @return string the empty, unparseable FQSEN input that caused this exception
     */
    public function getFQSEN() : string
    {
        return $this->fqsen;
    }
}
