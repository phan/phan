<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\FQSEN;

class ParentClassExistsAnalyzer
{

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeParentClassExists(
        CodeBase $code_base,
        Clazz $clazz
    ) {

        // Don't worry about internal classes
        if ($clazz->isInternal()) {
            return;
        }

        if ($clazz->hasParentClassFQSEN()) {
            self::fqsenExistsForClass(
                $clazz->getParentClassFQSEN(),
                $code_base,
                $clazz,
                Issue::UndeclaredExtendedClass
            );
        }

        foreach ($clazz->getInterfaceFQSENList() as $fqsen) {
            self::fqsenExistsForClass(
                $fqsen,
                $code_base,
                $clazz,
                Issue::UndeclaredInterface
            );
        }

        foreach ($clazz->getTraitFQSENList() as $fqsen) {
            self::fqsenExistsForClass(
                $fqsen,
                $code_base,
                $clazz,
                Issue::UndeclaredTrait
            );
        }
    }

    /**
     * @return bool
     * True if the FQSEN exists. If not, a log line is emitted
     */
    private static function fqsenExistsForClass(
        FQSEN $fqsen,
        CodeBase $code_base,
        Clazz $clazz,
        string $issue_type
    ) : bool {

        if (!$code_base->hasClassWithFQSEN($fqsen)) {
            Issue::maybeEmit(
                $code_base,
                $clazz->getContext(),
                $issue_type,
                $clazz->getFileRef()->getLineNumberStart(),
                (string)$fqsen
            );

            return false;
        }

        return true;
    }
}
