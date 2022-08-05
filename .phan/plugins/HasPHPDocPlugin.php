<?php

declare(strict_types=1);

namespace HasPHPDocPlugin;

use AssertionError;
use ast;
use ast\Node;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Element\ClassElement;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Func;
use Phan\Language\Element\MarkupDescription;
use Phan\Library\StringUtil;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeClassCapability;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

use function array_shift;
use function count;
use function gettype;
use function is_string;
use function json_encode;
use function ltrim;
use function preg_match;
use function strpos;
use function ucfirst;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

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
 *   Once all properties have been parsed, this method will
 *   be called on every property in the code base.
 * - analyzeMethod
 *   Once all methods have been parsed, this method will
 *   be called on every method in the code base.
 * - analyzeFunction
 *   Once all functions have been parsed, this method will
 *   be called on every function/closure in the code base.
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 * @internal
 */
final class HasPHPDocPlugin extends PluginV3 implements
    AnalyzeClassCapability,
    AnalyzeFunctionCapability,
    PostAnalyzeNodeCapability
{
    /** @var ?string a regex to use to exclude methods from phpdoc checks. */
    public static $method_filter;

    public function __construct()
    {
        $plugin_config = Config::getValue('plugin_config');
        self::$method_filter = $plugin_config['has_phpdoc_method_ignore_regex'] ?? null;
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     * @override
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ): void {
        if ($class->isAnonymous()) {
            // Probably not useful in many cases to document a short anonymous class.
            return;
        }
        $doc_comment = $class->getDocComment();
        if (!StringUtil::isNonZeroLengthString($doc_comment)) {
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
        if (!StringUtil::isNonZeroLengthString($description)) {
            if (strpos($doc_comment, '@deprecated') !== false) {
                return;
            }
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
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ): void {
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
        $doc_comment = $function->getDocComment();
        if (!StringUtil::isNonZeroLengthString($doc_comment)) {
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
        if (!StringUtil::isNonZeroLengthString($description)) {
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

    /**
     * Encode the doc comment in a one-line form that can be used in Phan's issue message.
     * @internal
     */
    public static function getDocCommentRepresentation(string $doc_comment): string
    {
        return (string)json_encode(MarkupDescription::getDocCommentWithoutWhitespace($doc_comment), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return (bool)(Config::getValue('plugin_config')['has_phpdoc_check_duplicates'] ?? false)
            ? DuplicatePHPDocCheckerPlugin::class
            : BasePHPDocCheckerPlugin::class;
    }
}

/** Infer property and class doc comments and warn */
class BasePHPDocCheckerPlugin extends PluginAwarePostAnalysisVisitor
{
    /** @return array{0:list<ClassElementEntry>,1:list<ClassElementEntry>} */
    public function visitClass(Node $node): array
    {
        $class = $this->context->getClassInScope($this->code_base);
        $property_descriptions = [];
        $method_descriptions = [];
        foreach ($node->children['stmts']->children ?? [] as $element) {
            if (!($element instanceof Node)) {
                throw new AssertionError("All properties of ast\AST_CLASS's statement list must be nodes, saw " . gettype($element));
            }
            switch ($element->kind) {
                case ast\AST_METHOD:
                    $entry = $this->checkMethodDescription($class, $element);
                    if ($entry) {
                        $method_descriptions[] = $entry;
                    }
                    break;
                case ast\AST_PROP_GROUP:
                    $entry = $this->checkPropGroupDescription($class, $element);
                    if ($entry) {
                        $property_descriptions[] = $entry;
                    }
                    break;
            }
        }
        return [$property_descriptions, $method_descriptions];
    }

    /**
     * @param Node $node a node of kind ast\AST_METHOD
     */
    private function checkMethodDescription(Clazz $class, Node $node): ?ClassElementEntry
    {
        $method_name = (string)$node->children['name'];
        $method = $class->getMethodByName($this->code_base, $method_name);
        if ($method->isMagic()) {
            // Ignore construct
            return null;
        }
        if ($method->isOverride()) {
            return null;
        }
        $method_filter = HasPHPDocPlugin::$method_filter;
        if (is_string($method_filter)) {
            $fqsen_string = ltrim((string)$method->getFQSEN(), '\\');
            if (preg_match($method_filter, $fqsen_string) > 0) {
                return null;
            }
        }

        $doc_comment = $method->getDocComment();
        if (!StringUtil::isNonZeroLengthString($doc_comment)) {
            $visibility_upper = ucfirst($method->getVisibilityName());
            self::emitPluginIssue(
                $this->code_base,
                $method->getContext(),
                "PhanPluginNoCommentOn{$visibility_upper}Method",
                "$visibility_upper method {METHOD} has no doc comment",
                [$method->getFQSEN()]
            );
            return null;
        }
        $description = MarkupDescription::extractDescriptionFromDocComment($method);
        if (!StringUtil::isNonZeroLengthString($description)) {
            $visibility_upper = ucfirst($method->getVisibilityName());
            self::emitPluginIssue(
                $this->code_base,
                $method->getContext(),
                "PhanPluginDescriptionlessCommentOn{$visibility_upper}Method",
                "$visibility_upper method {METHOD} has no readable description: {STRING_LITERAL}",
                [$method->getFQSEN(), HasPHPDocPlugin::getDocCommentRepresentation($doc_comment)]
            );
            return null;
        }
        return new ClassElementEntry($method, \trim(\preg_replace('/\s+/', ' ', $description)));
    }

    /**
     * @param Node $node a node of type ast\AST_PROP_GROUP
     */
    private function checkPropGroupDescription(Clazz $class, Node $node): ?ClassElementEntry
    {
        $property_name = $node->children['props']->children[0]->children['name'] ?? null;
        if (!is_string($property_name)) {
            return null;
        }
        $property = $class->getPropertyByName($this->code_base, $property_name);
        $doc_comment = $property->getDocComment();
        if (!StringUtil::isNonZeroLengthString($doc_comment)) {
            $visibility_upper = ucfirst($property->getVisibilityName());
            self::emitPluginIssue(
                $this->code_base,
                $property->getContext(),
                "PhanPluginNoCommentOn{$visibility_upper}Property",
                "$visibility_upper property {PROPERTY} has no doc comment",
                [$property->getRepresentationForIssue()]
            );
            return null;
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $description = MarkupDescription::extractDocComment($doc_comment, Comment::ON_PROPERTY, null, true);
        if (!StringUtil::isNonZeroLengthString($description)) {
            $visibility_upper = ucfirst($property->getVisibilityName());
            self::emitPluginIssue(
                $this->code_base,
                $property->getContext(),
                "PhanPluginDescriptionlessCommentOn{$visibility_upper}Property",
                "$visibility_upper property {PROPERTY} has no readable description: {STRING_LITERAL}",
                [$property->getRepresentationForIssue(), HasPHPDocPlugin::getDocCommentRepresentation($doc_comment)]
            );
            return null;
        }
        return new ClassElementEntry($property, \trim(\preg_replace('/\s+/', ' ', $description)));
    }
}

/**
 * Describes a property group or a method node and the associated description
 * @phan-immutable
 * @internal
 */
final class ClassElementEntry
{
    /** @var ClassElement the element (or element group) */
    public $element;
    /** @var string the phpdoc description */
    public $description;

    public function __construct(ClassElement $element, string $description)
    {
        $this->element = $element;
        $this->description = $description;
    }
}

/**
 * Check if phpdoc of property groups and methods are duplicated
 * @internal
 */
final class DuplicatePHPDocCheckerPlugin extends BasePHPDocCheckerPlugin
{
    /** No-op */
    public function visitClass(Node $node): array
    {
        [$property_descriptions, $method_descriptions] = parent::visitClass($node);
        foreach (self::findGroups($property_descriptions) as $entries) {
            $first_entry = array_shift($entries);
            if (!$first_entry instanceof ClassElementEntry) {
                throw new AssertionError('Expected $entries of $property_descriptions to be a group of 1 or more entries');
            }
            $first_property = $first_entry->element;
            foreach ($entries as $entry) {
                $property = $entry->element;
                self::emitPluginIssue(
                    $this->code_base,
                    $property->getContext(),
                    "PhanPluginDuplicatePropertyDescription",
                    "Property {PROPERTY} has the same description as the property \${PROPERTY} on line {LINE}: {COMMENT}",
                    [$property->getRepresentationForIssue(), $first_property->getName(), $first_property->getContext()->getLineNumberStart(), $first_entry->description]
                );
            }
        }
        foreach (self::findGroups($method_descriptions) as $entries) {
            $first_entry = array_shift($entries);
            if (!$first_entry instanceof ClassElementEntry) {
                throw new AssertionError('Expected $entries of $property_descriptions to be a group of 1 or more entries');
            }
            $first_method = $first_entry->element;
            foreach ($entries as $entry) {
                $method = $entry->element;
                self::emitPluginIssue(
                    $this->code_base,
                    $method->getContext(),
                    "PhanPluginDuplicateMethodDescription",
                    "Method {METHOD} has the same description as the method {METHOD} on line {LINE}: {COMMENT}",
                    [$method->getRepresentationForIssue(), $first_method->getName() . '()', $first_method->getContext()->getLineNumberStart(), $first_entry->description]
                );
            }
        }
        return [$property_descriptions, $method_descriptions];
    }

    /**
     * @param list<ClassElementEntry> $values
     * @return array<string, list<ClassElementEntry>>
     */
    private static function findGroups(array $values): array
    {
        $result = [];
        foreach ($values as $v) {
            if ($v->element->isDeprecated()) {
                continue;
            }
            $result[$v->description][] = $v;
        }
        foreach ($result as $description => $keys) {
            if (count($keys) <= 1) {
                unset($result[$description]);
            }
        }
        return $result;
    }
}
// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new HasPHPDocPlugin();
