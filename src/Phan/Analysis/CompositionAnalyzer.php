<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\UnionType;

use function count;

/**
 * This analyzer checks if the signatures of inherited properties match
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

        // No chance of failed composition if we don't inherit from
        // lots of stuff.
        if (count($inherited_class_list) < 2) {
            return;
        }

        // Since we're not necessarily getting this list of classes
        // via getClass, we need to ensure that hydration has occurred.
        $class->hydrate($code_base);

        // For each property, find out every inherited class that defines it
        // and check to see if the types line up.
        foreach ($class->getPropertyMap($code_base) as $property) {
            try {
                $property_union_type = $property->getUnionType();
            } catch (IssueException $_) {
                $property_union_type = UnionType::empty();
            }

            // Check for that property on each inherited
            // class/trait/interface
            foreach ($inherited_class_list as $inherited_class) {
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

                // Figure out if this property type can cast to the
                // inherited definition's type.
                try {
                    $inherited_property_union_type = $inherited_property->getUnionType();
                } catch (IssueException $_) {
                    $inherited_property_union_type = UnionType::empty();
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
                    $class->getFileRef()->getFile(),
                    $class->getFileRef()->getLineNumberStart()
                );
            }
        }
    }
}
