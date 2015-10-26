<?php
declare(strict_types=1);
namespace Phan;

class Options {

    private $instance = null;

    private function __construct() {
    }

    /**
     *
     */
    public function instance() {
        return $this->instance ?: (
            $this->instance = new Options()
        );
    }

    /**
     * @return bool
     */
    public function isEnabledBCChecks():bool {
        return true;
    }
}
