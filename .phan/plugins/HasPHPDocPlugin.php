<?php declare(strict_types=1);

use Phan\CodeBase;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\MarkupDescription;
use Phan\Language\Element\Property;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeClassCapability;
use Phan\PluginV2\AnalyzePropertyCapability;

/**
 * This file checks if an element (class or property) has a PHPDoc comment,
 * and that Phan can extract a plaintext summary/description from that comment.
 *
 * (e.g. for generating a hover description in the language server)
 *
 * It hooks into these events:
 *
 * - analyzeClass
 *   Once all classes are parsed, this method will be called
 *   on every method in the code base
 *
 * - analyzeProperty
 *   Once all functions have been parsed, this method will
 *   be called on every property in the code base.
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV2
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class HasPHPDocPlugin extends PluginV2 implements
    AnalyzeClassCapability,
    AnalyzePropertyCapability
{
    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        $doc_comment = $class->getDocComment();
        if (!$doc_comment) {
            $this->emitIssue(
                $code_base,
                $class->getContext(),
                'PhanPluginNoCommentOnClass',
                'Class {CLASS} has no doc comment',
                [$class->getFQSEN()]
            );
            return;
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $description = MarkupDescription::extractDescriptionFromDocComment($class);
        if (!$description) {
            $this->emitIssue(
                $code_base,
                $class->getContext(),
                'PhanPluginDescriptionlessCommentOnClass',
                'Class {CLASS} has no readable description: {STRING_LITERAL}',
                [$class->getFQSEN(), json_encode($class->getDocComment(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
            );
            return;
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Property $property
     * A property being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeProperty(
        CodeBase $code_base,
        Property $property
    ) {
        if ($property->isDynamicProperty()) {
            // And dynamic properties don't have phpdoc.
        }
        if ($property->isFromPHPDoc()) {
            // Phan does not track descriptions of (at)property.
            return;
        }
        if ($property->getFQSEN() !== $property->getRealDefiningFQSEN()) {
            // Only warn once for the original definition of this property.
            // Don't warn about subclasses inheriting this property.
            return;
        }
        $doc_comment = $property->getDocComment();
        if (!$doc_comment) {
            $visibility_upper = ucfirst($property->getVisibilityName());
            $this->emitIssue(
                $code_base,
                $property->getContext(),
                "PhanPluginNoCommentOn${visibility_upper}Property",
                "$visibility_upper property {PROPERTY} has no doc comment",
                [$property->getFQSEN()]
            );
            return;
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $description = MarkupDescription::extractDescriptionFromDocComment($property);
        if (!$description) {
            $visibility_upper = ucfirst($property->getVisibilityName());
            $this->emitIssue(
                $code_base,
                $property->getContext(),
                "PhanPluginDescriptionlessCommentOn${visibility_upper}Property",
                "$visibility_upper property {PROPERTY} has no readable description: {STRING_LITERAL}",
                [$property->getFQSEN(), json_encode($property->getDocComment(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
            );
            return;
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new HasPHPDocPlugin();
