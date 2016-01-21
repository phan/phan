<?php declare(strict_types=1);

namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Issue;
use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;

class ParentConstructorCalledAnalyzer {

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeParentConstructorCalled(
        CodeBase $code_base,
        Clazz $clazz
    ) {
        // Only look at classes configured to require a call
        // to its parent constructor
        if (!in_array($clazz->getName(),
            Config::get()->parent_constructor_required)
        ) {
            return;
        }

        // Don't worry about internal classes
        if ($clazz->isInternal()) {
            return;
        }

        // Don't worry if there's no parent class
        if (!$clazz->hasParentClassFQSEN()) {
            return;
        }

        if (!$code_base->hasClassWithFQSEN(
            $clazz->getParentClassFQSEN()
        )) {
            // This is an error, but its caught elsewhere. We'll
            // just roll through looking for other errors
            return;
        }

        $parent_clazz = $code_base->getClassByFQSEN(
            $clazz->getParentClassFQSEN()
        );

        if (!$parent_clazz->isAbstract()
            && !$clazz->getIsParentConstructorCalled()
        ) {
            Issue::emit(
                Issue::TypeParentConstructorCalled,
                $clazz->getContext()->getFile(),
                $clazz->getContext()->getLineNumberStart(),
                (string)$clazz->getFQSEN(),
                (string)$parent_clazz->getFQSEN()
            );
        }
    }
}
