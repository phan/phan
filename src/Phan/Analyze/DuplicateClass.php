<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Issue;
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

        if (!$code_base->hasClassWithFQSEN($original_fqsen)) {
            // If there's a missing class we'll catch that
            // elsewhere
            return;
        }

        // Get the original class
        $original_class = $code_base->getClassByFQSEN(
            $original_fqsen
        );

        // Check to see if the original definition was from
        // an internal class
        if ($original_class->isInternal()) {
            Issue::emit(
                Issue::RedefineClassInternal,
                $clazz->getContext()->getFile(),
                $clazz->getContext()->getLineNumberStart(),
                (string)$clazz,
                $clazz->getContext()->getFile(),
                $clazz->getContext()->getLineNumberStart(),
                (string)$original_class
            );

        // Otherwise, print the coordinates of the original
        // definition
        } else {
            Issue::emit(
                Issue::RedefineClass,
                $clazz->getContext()->getFile(),
                $clazz->getContext()->getLineNumberStart(),
                (string)$clazz,
                $clazz->getContext()->getFile(),
                $clazz->getContext()->getLineNumberStart(),
                (string)$original_class,
                $original_class->getContext()->getFile(),
                $original_class->getContext()->getLineNumberStart()
            );
        }

        return;
    }

}
