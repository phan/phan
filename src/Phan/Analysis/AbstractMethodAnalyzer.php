<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Parameter;
use Phan\Language\FQSEN;
use Phan\Language\UnionType;

/**
 * This verifies that the inherited abstract methods are all implemented on non-abstract clases.
 * NOTE: This step must be run after adding methods from this class and each of its ancestors.
 */
class AbstractMethodAnalyzer
{

    /**
     * Check to see if signatures match
     *
     * @return void
     */
    public static function analyzeAbstractMethodsAreImplemented(
        CodeBase $code_base,
        Clazz $class
    ) {
        // Don't worry about internal classes
        if ($class->isPHPInternal()) {
            return;
        }
        // Don't worry about traits or abstract classes, those can have abstract methods
        if ($class->isAbstract() || $class->isTrait() || $class->isInterface()) {
            return;
        }
        foreach ($class->getMethodMap($code_base) as $method) {
            if ($method->isAbstract()) {
                if ($method->isPHPInternal()) {
                    Issue::maybeEmit(
                        $code_base,
                        $class->getContext(),
                        Issue::ClassContainsAbstractMethodInternal,
                        $class->getFileRef()->getLineNumberStart(),
                        (string)$class->getFQSEN(),
                        (string)$method->getDefiningFQSEN()
                    );
                } else {
                    Issue::maybeEmit(
                        $code_base,
                        $class->getContext(),
                        Issue::ClassContainsAbstractMethod,
                        $class->getFileRef()->getLineNumberStart(),
                        (string)$class->getFQSEN(),
                        (string)$method->getDefiningFQSEN(),
                        $method->getFileRef()->getFile(),
                        $method->getFileRef()->getLineNumberStart()
                    );
                }
            }
        }
    }
}
