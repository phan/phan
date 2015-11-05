<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;
use \Phan\Log;

trait DuplicateClass {

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeDuplicateClass(
        CodeBase $code_base,
        Clazz $clazz
    ) {
        // Determine if its a duplicate by looking to see if
        // the FQSEN is suffixed with an alternate ID.

        if (!$clazz->getFQSEN()->isAlternate()) {
            return;
        }

        $original_fqsen = $clazz->getFQSEN()->getCanonicalFQSEN();

        // Get the original class
        $original_class = $code_base->getClassByFQSEN(
            $original_fqsen
        );

        // Check to see if the original definition was from
        // an internal class
        if ($original_class->isInternal()) {
            Log::err(Log::EREDEF,
                "{$fqsen_string} defined at "
                . "{$clazz->getContext()->getFile()}:{$clazz->getContext()->getLineNumberStart()} "
                . "was previously defined as {$original_class->getFQSEN()} internally",
                    $clazz->getContext()->getFile(),
                    $clazz->getContext()->getLineNumberStart()
                );

        // Otherwise, print the coordinates of the original
        // definition
        } else {
            Log::err(Log::EREDEF,
                "{$clazz->getFQSEN()} defined at "
                . "{$clazz->getContext()->getFile()}:{$clazz->getContext()->getLineNumberStart()} "
                . "was previously defined as {$original_class->getFQSEN()} at "
                . "{$original_class->getContext()->getFile()}:{$original_class->getContext()->getLineNumberStart()} ",
                    $clazz->getContext()->getFile(),
                    $clazz->getContext()->getLineNumberStart()
                );
        }

        return;
    }

}
