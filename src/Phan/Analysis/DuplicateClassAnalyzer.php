<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Clazz;

/**
 * Analyzer that checks for duplicate classes/traits/interfaces.
 */
class DuplicateClassAnalyzer
{
    /**
     * Check to see if the given Clazz is a duplicate
     */
    public static function analyzeDuplicateClass(
        CodeBase $code_base,
        Clazz $clazz
    ): void {
        // Determine if it's a duplicate by looking to see if
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
        $original_class = $code_base->getClassByFQSEN($original_fqsen);

        // Check to see if the original definition was from
        // an internal class
        if ($original_class->isPHPInternal()) {
            if (!$clazz->checkHasSuppressIssueAndIncrementCount(Issue::RedefineClassInternal)) {
                Issue::maybeEmit(
                    $code_base,
                    $clazz->getContext(),
                    Issue::RedefineClassInternal,
                    $clazz->getFileRef()->getLineNumberStart(),
                    (string)$clazz,
                    $clazz->getFileRef()->getFile(),
                    $clazz->getFileRef()->getLineNumberStart(),
                    (string)$original_class
                );
            }
        // Otherwise, print the coordinates of the original
        // definition
        } else {
            if (!$clazz->checkHasSuppressIssueAndIncrementCount(Issue::RedefineClass)) {
                Issue::maybeEmit(
                    $code_base,
                    $clazz->getContext(),
                    Issue::RedefineClass,
                    $clazz->getFileRef()->getLineNumberStart(),
                    (string)$clazz,
                    $clazz->getFileRef()->getFile(),
                    $clazz->getFileRef()->getLineNumberStart(),
                    (string)$original_class,
                    $original_class->getFileRef()->getFile(),
                    $original_class->getFileRef()->getLineNumberStart()
                );
            }
            // If there are 3 classes with the same namespace and name,
            // warn *once* about the first (user-defined) class being a duplicate.
            // NOTE: This won't work very well in language server mode.
            if ($clazz->getFQSEN()->getAlternateId() === 1) {
                if (!$original_class->checkHasSuppressIssueAndIncrementCount(Issue::RedefineClass)) {
                    Issue::maybeEmit(
                        $code_base,
                        $original_class->getContext(),
                        Issue::RedefineClass,
                        $original_class->getFileRef()->getLineNumberStart(),
                        (string)$original_class,
                        $original_class->getFileRef()->getFile(),
                        $original_class->getFileRef()->getLineNumberStart(),
                        (string)$clazz,
                        $clazz->getFileRef()->getFile(),
                        $clazz->getFileRef()->getLineNumberStart()
                    );
                }
            }
        }

        return;
    }
}
