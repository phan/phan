<?php

namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Configuration;
use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;
use \Phan\Log;

trait ParentConstructorCalled {

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
            Configuration::instance()->parent_constructor_required)
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
            Log::err(
                Log::ETYPE,
                "{$clazz->getFQSEN()} extends {$parent_clazz->getFQSEN()} but doesn't call parent::__construct()",
                $clazz->getContext()->getFile(),
                $clazz->getContext()->getLineNumberStart()
            );
        }
    }
}
