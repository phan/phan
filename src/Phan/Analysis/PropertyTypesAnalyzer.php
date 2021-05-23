<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Element\Clazz;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

/**
 * An analyzer that checks a class's properties for issues.
 */
class PropertyTypesAnalyzer
{

    /**
     * Check to see if the given class's properties have issues.
     */
    public static function analyzePropertyTypes(CodeBase $code_base, Clazz $clazz): void
    {
        foreach ($clazz->getPropertyMap($code_base) as $property) {
            $property_context = $property->getContext();
            // This phase is done before the analysis phase, so there aren't any dynamic properties to filter out.

            // Get the union type of this property. This may throw (e.g. it can refers to missing elements).
            try {
                $union_type = $property->getUnionType();
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $property_context,
                    $exception->getIssueInstance()
                );
                continue;
            }
            // @phan-suppress-next-line PhanPluginUseReturnValueKnown this is invoked to emit issues
            $union_type->checkImpossibleCombination($code_base, $property_context);

            // Look at each type in the parameter's Union Type
            foreach ($union_type->withFlattenedArrayShapeOrLiteralTypeInstances()->getTypeSet() as $outer_type) {
                foreach ($outer_type->getReferencedClasses() as $type) {
                    // If it's a reference to self, its OK
                    if ($type->isSelfType()) {
                        continue;
                    }

                    if ($type instanceof TemplateType) {
                        if ($property->isStatic()) {
                            Issue::maybeEmit(
                                $code_base,
                                $property_context,
                                Issue::TemplateTypeStaticProperty,
                                $property_context->getLineNumberStart(),
                                $property->asPropertyFQSENString()
                            );
                        }
                        continue;
                    }
                    if (!($property->hasDefiningFQSEN() && $property->getDefiningFQSEN() === $property->getFQSEN())) {
                        continue;
                    }
                    if ($type instanceof TemplateType) {
                        continue;
                    }

                    // Make sure the class exists
                    $type_fqsen = FullyQualifiedClassName::fromType($type);

                    if ($code_base->hasClassWithFQSEN($type_fqsen)) {
                        if ($code_base->hasClassWithFQSEN($type_fqsen->withAlternateId(1))) {
                            UnionType::emitRedefinedClassReferenceWarning(
                                $code_base,
                                $property_context,
                                $type_fqsen
                            );
                        }
                    } else {
                        Issue::maybeEmitWithParameters(
                            $code_base,
                            $property_context,
                            Issue::UndeclaredTypeProperty,
                            $property_context->getLineNumberStart(),
                            [$property->asPropertyFQSENString(), (string)$outer_type],
                            IssueFixSuggester::suggestSimilarClass($code_base, $property_context, $type_fqsen, null, 'Did you mean', IssueFixSuggester::CLASS_SUGGEST_CLASSES_AND_TYPES)
                        );
                    }
                }
            }
        }
    }
}
