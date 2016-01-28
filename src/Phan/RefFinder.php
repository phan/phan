<?php declare(strict_types=1);
namespace Phan;

use \Phan\CodeBase;
use \Phan\Config;

class RefFinder {
    public static function find(string $fqsen): array {
        $database = new Database();
        $callers = Model\CalledBy::findManyByFQSEN($database, $fqsen);

        $reference_locations = [];
        foreach ($callers as $caller) {
            $reference_locations[] = $caller->referenceLocation();
        }

        return $reference_locations;
    }
}
