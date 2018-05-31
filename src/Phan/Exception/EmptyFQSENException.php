<?php declare(strict_types=1);
namespace Phan\Exception;

class EmptyFQSENException extends \Exception
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
