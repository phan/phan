<?php declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Element\Clazz;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\TemplateType;

/**
 * An analyzer that checks a class's properties for issues.
 */
class PropertyTypesAnalyzer
{

    /**
     * Check to see if the given class's properties have issues.
     */
    public static function analyzePropertyTypes(CodeBase $code_base, Clazz $clazz) : void
    {
        foreach ($clazz->getPropertyMap($code_base) as $property) {
            // This phase is done before the analysis phase, so there aren't any dynamic properties to filter out.

            // Get the union type of this property. This may throw (e.g. it can refers to missing elements).
            try {
                $union_type = $property->getUnionType();
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $property->getContext(),
                    $exception->getIssueInstance()
                );
                continue;
            }

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
                                $property->getContext(),
                                Issue::TemplateTypeStaticProperty,
                                $property->getFileRef()->getLineNumberStart(),
                                $property->asPropertyFQSENString()
                            );
                        }
                        continue;
                    }

                    // Make sure the class exists
                    $type_fqsen = FullyQualifiedClassName::fromType($type);

                    if (!$code_base->hasClassWithFQSEN($type_fqsen)
                        && !($type instanceof TemplateType)
                        && (
                            !$property->hasDefiningFQSEN()
                            || $property->getDefiningFQSEN() === $property->getFQSEN()
                        )
                    ) {
                        Issue::maybeEmitWithParameters(
                            $code_base,
                            $property->getContext(),
                            Issue::UndeclaredTypeProperty,
                            $property->getFileRef()->getLineNumberStart(),
                            [$property->asPropertyFQSENString(), (string)$outer_type],
                            IssueFixSuggester::suggestSimilarClass($code_base, $property->getContext(), $type_fqsen, null, 'Did you mean', IssueFixSuggester::CLASS_SUGGEST_CLASSES_AND_TYPES)
                        );
                    }
                }
            }
        }
    }
}
