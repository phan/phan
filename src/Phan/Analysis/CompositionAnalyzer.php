<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Parameter;
use Phan\Language\FQSEN;
use Phan\Language\UnionType;

class CompositionAnalyzer
{

    /**
     * Check to see if signatures match
     *
     * @return void
     */
    public static function analyzeComposition(
        CodeBase $code_base,
        Clazz $class
    ) {
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
        foreach ($class->getPropertyList($code_base) as $property) {
            try {
                $property_union_type = $property->getUnionType();
            } catch (IssueException $exception) {
                $property_union_type = new UnionType;
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
                $can_cast =
                    $property_union_type->canCastToExpandedUnionType(
                        $inherited_property->getUnionType(),
                        $code_base
                    );

                if ($can_cast) {
                    continue;
                }

                // Don't emit an issue if the property suppresses the issue
                // NOTE: The current context is the class, not either of the properties.
                if ($property->hasSuppressIssue(Issue::IncompatibleCompositionProp)) {
                    $property->incrementSuppressIssueCount(Issue::IncompatibleCompositionProp);
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

        // TODO: This has too much overlap with PhanParamSignatureMismatch
        //       and we should figure out how to merge it.

        /*
        // Get the Class's FQSEN
        $fqsen = $class->getFQSEN();

        $method_map =
            $code_base->getMethodMapByFullyQualifiedClassName($fqsen);

        // For each method, find out every inherited class that defines it
        // and check to see if the types line up.
        foreach ($method_map as $i => $method) {

            $method_union_type = $method->getUnionType();

            // We don't need to analyze constructors for signature
            // compatibility
            if ($method->getName() == '__construct') {
                continue;
            }

            // Get the method parameter list

            // Check for that method on each inherited
            // class/trait/interface
            foreach ($inherited_class_list as $inherited_class) {

                // Skip anything that doesn't define this method
                if (!$inherited_class->hasMethodWithName($code_base, $method->getName())) {
                    continue;
                }

                $inherited_method =
                    $inherited_class->getMethodByName($code_base, $method->getName());

                if ($method == $inherited_method) {
                    continue;
                }

                // Figure out if this method return type can cast to the
                // inherited definition's return type.
                $is_compatible =
                    $method_union_type->canCastToExpandedUnionType(
                        $inherited_method->getUnionType(),
                        $code_base
                    );

                $inherited_method_parameter_map =
                    $inherited_method->getParameterList();

                // Figure out if all of the parameter types line up
                foreach ($method->getParameterList() as $i => $parameter) {
                    $is_compatible = (
                        $is_compatible
                        && isset($inherited_method_parameter_map[$i])
                        && $parameter->getUnionType()->canCastToExpandedUnionType(
                            ($inherited_method_parameter_map[$i])->getUnionType(),
                            $code_base
                        )
                    );
                }

                if ($is_compatible) {
                    continue;
                }

                // Don't emit an issue if the method suppresses the issue
                if ($method->hasSuppressIssue(Issue::IncompatibleCompositionMethod)) {
                    continue;
                }

                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::IncompatibleCompositionMethod,
                    $method->getFileRef()->getLineNumberStart(),
                    (string)$method,
                    (string)$inherited_method,
                    $inherited_method->getFileRef()->getFile(),
                    $inherited_method->getFileRef()->getLineNumberStart()
                );
            }
        }
        */
    }
}
