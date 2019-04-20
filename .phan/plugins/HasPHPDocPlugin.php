<?php declare(strict_types=1);

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\MarkupDescription;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeClassCapability;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
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
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
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
        if ($class->isAnonymous()) {
            // Probably not useful in many cases to document a short anonymous class.
            return;
        }
        $doc_comment = $class->getDocComment();
        if (!$doc_comment) {
            self::emitIssue(
                $code_base,
                $class->getContext(),
                'PhanPluginNoCommentOnClass',
                'Class {CLASS} has no doc comment',
                [$class->getFQSEN()]
            );
            return;
        }
        $description = MarkupDescription::extractDescriptionFromDocComment($class);
        if (!$description) {
            self::emitIssue(
                $code_base,
                $class->getContext(),
                'PhanPluginDescriptionlessCommentOnClass',
                'Class {CLASS} has no readable description: {STRING_LITERAL}',
                [$class->getFQSEN(), self::getDocCommentRepresentation($doc_comment)]
            );
            return;
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the property exists
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
            return;
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
            self::emitIssue(
                $code_base,
                $property->getContext(),
                "PhanPluginNoCommentOn${visibility_upper}Property",
                "$visibility_upper property {PROPERTY} has no doc comment",
                [$property->getRepresentationForIssue()]
            );
            return;
        }
        $description = MarkupDescription::extractDescriptionFromDocComment($property);
        if (!$description) {
            $visibility_upper = ucfirst($property->getVisibilityName());
            self::emitIssue(
                $code_base,
                $property->getContext(),
                "PhanPluginDescriptionlessCommentOn${visibility_upper}Property",
                "$visibility_upper property {PROPERTY} has no readable description: {STRING_LITERAL}",
                [$property->getRepresentationForIssue(), self::getDocCommentRepresentation($doc_comment)]
            );
            return;
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        if ($method->isFromPHPDoc()) {
            // Phan does not track descriptions of (at)method.
            return;
        }
        if ($method->getIsMagic()) {
            // Don't require a description for `__construct()`, `__sleep()`, etc.
            return;
        }
        if ($method->getFQSEN() !== $method->getRealDefiningFQSEN()) {
            // Only warn once for the original definition of this method.
            // Don't warn about subclasses inheriting this method.
            return;
        }
        if ($method->getIsOverride()) {
            // Note: This deliberately avoids requiring a summary for methods that are just overrides of other methods
            // This reduces the number of false positives
            return;
        }
        $method_filter = Config::getValue('plugin_config')['has_phpdoc_method_ignore_regex'] ?? null;
        if (is_string($method_filter)) {
            $fqsen_string = ltrim((string)$method->getFQSEN(), '\\');
            if (preg_match($method_filter, $fqsen_string) > 0) {
                return;
            }
        }

        $doc_comment = $method->getDocComment();
        if (!$doc_comment) {
            $visibility_upper = ucfirst($method->getVisibilityName());
            self::emitIssue(
                $code_base,
                $method->getContext(),
                "PhanPluginNoCommentOn${visibility_upper}Method",
                "$visibility_upper method {METHOD} has no doc comment",
                [$method->getFQSEN()]
            );
            return;
        }
        $description = MarkupDescription::extractDescriptionFromDocComment($method);
        if (!$description) {
            $visibility_upper = ucfirst($method->getVisibilityName());
            self::emitIssue(
                $code_base,
                $method->getContext(),
                "PhanPluginDescriptionlessCommentOn${visibility_upper}Method",
                "$visibility_upper method {METHOD} has no readable description: {STRING_LITERAL}",
                [$method->getFQSEN(), self::getDocCommentRepresentation($doc_comment)]
            );
            return;
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        $doc_comment = $function->getDocComment();
        if ($function->isPHPInternal()) {
            // This isn't user-defined, there's no reason to warn or way to change it.
            return;
        }
        if ($function->isNSInternal($code_base)) {
            // (at)internal are internal to the library, and there's less of a need to document them
            return;
        }
        if ($function->isClosure()) {
            // Probably not useful in many cases to document a short closure passed to array_map, etc.
            return;
        }
        if (!$doc_comment) {
            self::emitIssue(
                $code_base,
                $function->getContext(),
                "PhanPluginNoCommentOnFunction",
                "Function {FUNCTION} has no doc comment",
                [$function->getFQSEN()]
            );
            return;
        }
        $description = MarkupDescription::extractDescriptionFromDocComment($function);
        if (!$description) {
            self::emitIssue(
                $code_base,
                $function->getContext(),
                "PhanPluginDescriptionlessCommentOnFunction",
                "Function {FUNCTION} has no readable description: {STRING_LITERAL}",
                [$function->getFQSEN(), self::getDocCommentRepresentation($doc_comment)]
            );
            return;
        }
    }

    private static function getDocCommentRepresentation(string $doc_comment) : string
    {
        return (string)json_encode(MarkupDescription::getDocCommentWithoutWhitespace($doc_comment), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new HasPHPDocPlugin();
