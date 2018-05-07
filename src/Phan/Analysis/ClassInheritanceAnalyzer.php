<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\FQSEN\FullyQualifiedClassName;

use Closure;

class ClassInheritanceAnalyzer
{
    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return void
     */
    public static function analyzeClassInheritance(
        CodeBase $code_base,
        Clazz $clazz
    ) {

        // Don't worry about internal classes
        if ($clazz->isPHPInternal()) {
            return;
        }

        if ($clazz->hasParentType()) {
            $class_exists = self::fqsenExistsForClass(
                $clazz->getParentClassFQSEN(),
                $code_base,
                $clazz,
                Issue::UndeclaredExtendedClass
            );

            if ($class_exists) {
                self::testClassAccess(
                    $clazz,
                    $clazz->getParentClass($code_base),
                    $code_base
                );
            }
        }

        foreach ($clazz->getInterfaceFQSENList() as $fqsen) {
            $class_exists = self::fqsenExistsForClass(
                $fqsen,
                $code_base,
                $clazz,
                Issue::UndeclaredInterface
            );

            if ($class_exists) {
                self::testClassAccess(
                    $clazz,
                    $code_base->getClassByFQSEN($fqsen),
                    $code_base
                );
            }
        }

        foreach ($clazz->getTraitFQSENList() as $fqsen) {
            $class_exists = self::fqsenExistsForClass(
                $fqsen,
                $code_base,
                $clazz,
                Issue::UndeclaredTrait
            );
            if ($class_exists) {
                self::testClassAccess(
                    $clazz,
                    $code_base->getClassByFQSEN($fqsen),
                    $code_base
                );
            }
        }
    }

    /**
     * @return bool
     * True if the FQSEN exists. If not, a log line is emitted
     */
    private static function fqsenExistsForClass(
        FullyQualifiedClassName $fqsen,
        CodeBase $code_base,
        Clazz $clazz,
        string $issue_type
    ) : bool {
        if (!$code_base->hasClassWithFQSEN($fqsen)) {
            $generate_filter = function($class_closure) use ($code_base) : Closure {
                return function(FullyQualifiedClassName $alternate_fqsen) use ($code_base, $class_closure) : bool {
                    if (!$code_base->hasClassWithFQSEN($alternate_fqsen)) {
                        return false;
                    }
                    return $class_closure($code_base->getClassByFQSEN($alternate_fqsen));
                };
            };
            $filter = null;
            switch($issue_type) {
            case Issue::UndeclaredExtendedClass:
                $filter = $generate_filter(function(Clazz $class) : bool {
                    return !$class->isInterface() && !$class->isTrait();
                });
                break;
            case Issue::UndeclaredTrait:
                $filter = $generate_filter(function(Clazz $class) : bool {
                    return $class->isTrait();
                });
                break;
            case Issue::UndeclaredInterface:
                $filter = $generate_filter(function(Clazz $class) : bool {
                    return $class->isInterface();
                });
                break;
            }
            $suggestion = Issue::suggestSimilarClass($code_base, $clazz->getContext(), $fqsen, $filter);

            Issue::maybeEmitWithParameters(
                $code_base,
                $clazz->getContext(),
                $issue_type,
                $clazz->getFileRef()->getLineNumberStart(),
                [(string)$fqsen],
                $suggestion
            );

            return false;
        }

        return true;
    }

    /**
     * @param Clazz $source_class
     * The class accessing the $target_class
     *
     * @param Clazz $target_class
     * The class being accessed from the $source_class
     *
     * @param CodeBase $code_base
     * The code base in which both classes exist
     */
    private static function testClassAccess(
        Clazz $source_class,
        Clazz $target_class,
        CodeBase $code_base
    ) {
        if ($target_class->isNSInternal($code_base)
            && !$target_class->isNSInternalAccessFromContext(
                $code_base,
                $source_class->getContext()
            )
        ) {
            Issue::maybeEmit(
                $code_base,
                $source_class->getContext(),
                Issue::AccessClassInternal,
                $source_class->getFileRef()->getLineNumberStart(),
                (string)$target_class,
                $target_class->getFileRef()->getFile(),
                (string)$target_class->getFileRef()->getLineNumberStart()
            );
        }

        /*
        if ($target_class->isDeprecated()) {
            Issue::maybeEmit(
                $code_base,
                $source_class->getContext(),
                Issue::DeprecatedClass,
                $source_class->getFileRef()->getLineNumberStart(),
                (string)$target_class->getFQSEN(),
                $target_class->getFileRef()->getFile(),
                (string)$target_class->getFileRef()->getLineNumberStart()
            );
        }
        */
    }
}
