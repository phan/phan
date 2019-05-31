<?php

abstract class someabstract {
    private static $instance;
    public function __construct() {
        self::$instance = $this;
        // False positive PhanTypeMismatchProperty Assigning \someabstract|static to property but \someabstract::$instance is static
        self::$instance = $this;
    }
}
