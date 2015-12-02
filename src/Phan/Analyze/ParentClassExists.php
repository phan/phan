<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;
use \Phan\Log;

trait ParentClassExists {

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
                "Trying to inherit from unknown class %s"
            );
        }

        foreach ($clazz->getInterfaceFQSENList() as $fqsen) {
            self::fqsenExistsForClass(
                $fqsen,
                $code_base,
                $clazz,
                "Trying to implement unknown interface %s"
            );
        }

        foreach ($clazz->getTraitFQSENList() as $fqsen) {
            self::fqsenExistsForClass(
                $fqsen,
                $code_base,
                $clazz,
                "Trying to use unknown trait %s"
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
        string $message_template
    ) : bool {

        if (!$code_base->hasClassWithFQSEN($fqsen)) {
            Log::err(
                Log::EUNDEF,
                sprintf($message_template, $fqsen),
                $clazz->getContext()->getFile(),
                $clazz->getContext()->getLineNumberStart()
            );
            return false;
        }

        return true;
    }
}
