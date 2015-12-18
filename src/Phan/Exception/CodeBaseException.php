<?php declare(strict_types=1);
namespace Phan\Exception;

use \Phan\Debug;
use \Phan\Language\FQSEN;

class CodeBaseException extends \Exception {

    /**
     * @var FQSEN
     */
    private $missing_fqsen;

    /**
     * @param FQSEN $missing_fqsen
     * The FQSEN that cannot be found in the code base
     *
     * @param string $message
     * The error message
     */
    public function __construct(
        FQSEN $missing_fqsen,
        string $message = null
    ) {
        parent::__construct($message);
        $this->missing_fqsen = $missing_fqsen;
    }

    /**
     * @return FQSEN
     * The missing FQSEN
     */
    public function getFQSEN() : FQSEN {
        return $this->missing_fqsen;
    }

}
