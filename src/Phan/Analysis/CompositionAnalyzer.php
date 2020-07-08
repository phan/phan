<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\UnionType;

/**
 * This analyzer checks if the signatures of inherited properties match
 * and for type mismatches for php 7.4 typed properties.
 */
class CompositionAnalyzer
{

    /**
     * Check to see if the signatures of inherited properties match
     */
    public static function analyzeComposition(
        CodeBase $code_base,
        Clazz $class
    ): void {
        // Get the list of all inherited classes.
        $inherited_class_list =
            $class->getAncestorClassList($code_base);

        // No chance of failed composition if we don't inherit from anything.
        if (!$inherited_class_list) {
            return;
        }

        // Since we're not necessarily getting this list of classes
        // via getClass, we need to ensure that hydration has occurred.
        $class->hydrate($code_base);

        // For each property, find out every inherited class that defines it
        // and check to see if the types line up.
        // (This must be done after hydration, because some properties are loaded from traits)
        foreach ($class->getPropertyMap($code_base) as $property) {
            try {
                $property_union_type = $property->getDefaultType() ?? UnionType::empty();
            } catch (IssueException $_) {
                $property_union_type = UnionType::empty();
            }

            // Check for that property on each inherited
            // class/trait/interface
            foreach ($inherited_class_list as $inherited_class) {
                $inherited_class->hydrate($code_base);

                // Skip any classes/traits/interfaces not defining that
                // property
                if (!$inherited_class->hasPropertyWithName($code_base, $property->getName())) {
                    continue;
                }

                // We don't call `getProperty` because that will create
                // them in some circumstances.
                $inherited_property_map =
                    $inherited_class->getPropertyMap($code_base);

                if (!isset($inherited_property_map[$property->getName()])) {
                    continue;
                }

                // Get the inherited property
                $inherited_property =
                    $inherited_property_map[$property->getName()];

                if ($inherited_property->isDynamicOrFromPHPDoc()) {
                    continue;
                }
                if ($inherited_property->getRealDefiningFQSEN() === $property->getRealDefiningFQSEN()) {
                    continue;
                }

                // Figure out if this property type can cast to the
                // inherited definition's type.
                // Use the phpdoc comment or real type declaration instead of the inferred
                // types from the default to perform this check.
                try {
                    $inherited_property_union_type = $inherited_property->getDefaultType() ?? UnionType::empty();
                } catch (IssueException $_) {
                    $inherited_property_union_type = UnionType::empty();
                }
                if (!$property->isDynamicOrFromPHPDoc()) {
                    $real_property_type = $property->getRealUnionType()->asNormalizedTypes();
                    $real_inherited_property_type = $inherited_property->getRealUnionType()->asNormalizedTypes();
                    if (!$real_property_type->isEqualTo($real_inherited_property_type)) {
                        Issue::maybeEmit(
                            $code_base,
                            $property->getContext(),
                            Issue::IncompatibleRealPropertyType,
                            $property->getFileRef()->getLineNumberStart(),
                            $property->getFQSEN(),
                            $real_property_type,
                            $inherited_property->getFQSEN(),
                            $real_inherited_property_type,
                            $inherited_property->getFileRef()->getFile(),
                            $inherited_property->getFileRef()->getLineNumberStart()
                        );
                    }
                }

                if ($property->getFQSEN() === $property->getRealDefiningFQSEN()) {
                    // No need to warn about incompatible composition of trait with another ancestor if the property's default was overridden
                    continue;
                }
                $can_cast =
                    $property_union_type->canCastToExpandedUnionType(
                        $inherited_property_union_type,
                        $code_base
                    );

                if ($can_cast) {
                    continue;
                }

                // Don't emit an issue if the property suppresses the issue
                // NOTE: The current context is the class, not either of the properties.
                if ($property->checkHasSuppressIssueAndIncrementCount(Issue::IncompatibleCompositionProp)) {
                    continue;
                }

                Issue::maybeEmit(
                    $code_base,
                    $property->getContext(),
                    Issue::IncompatibleCompositionProp,
                    $property->getFileRef()->getLineNumberStart(),
                    (string)$class->getFQSEN(),
                    (string)$inherited_class->getFQSEN(),
                    $property->getName(),
                    (string)$class->getFQSEN(),
                    $property_union_type,
                    $inherited_property_union_type,
                    $class->getFileRef()->getFile(),
                    $class->getFileRef()->getLineNumberStart()
                );
            }
        }
    }
}
