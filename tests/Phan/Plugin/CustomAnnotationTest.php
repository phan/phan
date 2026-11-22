<?php

declare(strict_types=1);

namespace Phan\Tests\Plugin;

use AssertionError;
use Phan\Analysis;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Element\Comment\CustomAnnotation;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Type;
use Phan\Library\FileCache;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;
use Phan\Tests\TestBase;

/**
 * Tests of CustomAnnotationCapability that tests/custom_annotation_test does not cover: the text of the forms of an
 * annotation, the checks of the declared names, and the annotations after a file is parsed again in daemon mode.
 *
 * @phan-file-suppress PhanThrowTypeAbsentForCall, PhanAccessMethodInternal, PhanAccessClassConstantInternal
 */
final class CustomAnnotationTest extends TestBase
{
    /** The $GLOBALS key of the names that the test plugin declares (getCustomAnnotations()) */
    private const NAMES_KEY = 'phan_custom_annotation_test_names';

    /** The $GLOBALS key where the test plugin records its calls of handleCustomAnnotation() */
    private const CALLS_KEY = 'phan_custom_annotation_test_calls';

    /**
     * A plugin that declares the names in $GLOBALS[NAMES_KEY] and records each call of handleCustomAnnotation()
     * as "FQSEN of the element: annotation (line)".
     */
    private const RECORDING_PLUGIN = <<<'PHP'
<?php
use Phan\CodeBase;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment\CustomAnnotation;
use Phan\PluginV3;
use Phan\PluginV3\CustomAnnotationCapability;

return new class extends PluginV3 implements CustomAnnotationCapability {
    public function getCustomAnnotations(): array
    {
        return $GLOBALS['phan_custom_annotation_test_names'];
    }

    public function handleCustomAnnotation(CodeBase $code_base, AddressableElementInterface $element, CustomAnnotation $annotation): void
    {
        $GLOBALS['phan_custom_annotation_test_calls'][] = $element->getFQSEN() . ': ' . $annotation . ' (' . $annotation->getLineno() . ')';
    }
};
PHP;

    /** @var list<string> */
    private array $temp_files = [];

    /** @var ?string the directory of the files of the test, created on first use */
    private ?string $temp_dir = null;

    /** @var int the modification time of the next file written */
    private int $mtime = 0;

    public function setUp(): void
    {
        parent::setUp();
        Config::reset();
        ConfigPluginSet::reset();
        Phan::setIssueCollector(new BufferingCollector());
        $GLOBALS[self::NAMES_KEY] = ['phan-x' => 'The test annotation'];
        $GLOBALS[self::CALLS_KEY] = [];
        $this->mtime = \time() - 1000;
    }

    public function tearDown(): void
    {
        foreach ($this->temp_files as $path) {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
        $this->temp_files = [];
        if ($this->temp_dir !== null) {
            \rmdir($this->temp_dir);
            $this->temp_dir = null;
        }
        unset($GLOBALS[self::NAMES_KEY], $GLOBALS[self::CALLS_KEY]);
        Config::reset();
        ConfigPluginSet::reset();
        Type::clearAllMemoizations();
        FullyQualifiedGlobalStructuralElement::clearPreferredNameMap();
        FileCache::clear();
        parent::tearDown();
    }

    private function getTempDir(): string
    {
        if ($this->temp_dir === null) {
            $dir = \sys_get_temp_dir() . '/phan_custom_annotation_' . \getmypid();
            \mkdir($dir);
            $this->temp_dir = $dir;
        }
        return $this->temp_dir;
    }

    /**
     * Writes $contents to the file $name of the temporary directory, with a new modification time.
     */
    private function writeFile(string $name, string $contents): string
    {
        $path = $this->getTempDir() . '/' . $name;
        $is_new = !\is_file($path);
        \file_put_contents($path, $contents);
        // The undo tracker compares the modification times.
        $this->mtime += 10;
        \touch($path, $this->mtime);
        FileCache::clear();
        if ($is_new) {
            $this->temp_files[] = $path;
        }
        return $path;
    }

    /**
     * Loads the recording plugin, which declares the names in $GLOBALS[NAMES_KEY].
     */
    private function loadPlugin(): void
    {
        Config::setValue('plugins', [$this->writeFile('plugin_' . \count($this->temp_files) . '.php', self::RECORDING_PLUGIN)]);
        ConfigPluginSet::reset();
    }

    /**
     * @return list<string> the calls of handleCustomAnnotation() since the last call of this method
     */
    private function takeCalls(): array
    {
        $calls = $GLOBALS[self::CALLS_KEY];
        $this->assertIsArray($calls);
        $GLOBALS[self::CALLS_KEY] = [];
        return \array_values(\array_map('strval', $calls));
    }

    /**
     * @return list<string> the annotations of $element as strings, with their lines
     */
    private static function describe(CodeBase $code_base, AddressableElementInterface $element): array
    {
        return \array_map(static function (CustomAnnotation $annotation): string {
            return $annotation . ' (' . $annotation->getLineno() . ')';
        }, $element->getCustomAnnotations($code_base));
    }

    /**
     * Every annotation that Builder handles is an annotation of Phan, which plugins cannot declare.
     */
    public function testAnnotationsOfPhanAreReserved(): void
    {
        $source = \file_get_contents(\dirname(__DIR__, 3) . '/src/Phan/Language/Element/Comment/Builder.php');
        $this->assertIsString($source);
        \preg_match_all("/case '(phan-[a-z0-9_-]+)':/", $source, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $name) {
            $this->assertTrue(Builder::isPhanAnnotationName($name), "$name is handled by Builder but not reserved");
        }
        foreach (Builder::SUPPORTED_ANNOTATIONS as $annotation => $_) {
            $this->assertTrue(Builder::isPhanAnnotationName(\substr($annotation, 1)), "$annotation is not reserved");
        }
        foreach (['phan-debug-var', 'phan-var-force', 'phan-output-reference', 'phan-ignore-reference', 'phan-mandatory-param'] as $name) {
            $this->assertTrue(Builder::isPhanAnnotationName($name), "$name is not reserved");
        }
        $this->assertFalse(Builder::isPhanAnnotationName('phan-my-assert'));
        $this->assertFalse(Builder::isPhanAnnotationName('phan-param-x'));
    }

    /**
     * @param array<mixed,mixed> $names
     * @dataProvider invalidDeclarationProvider
     */
    public function testInvalidDeclaration(array $names, string $expected_message): void
    {
        $GLOBALS[self::NAMES_KEY] = $names;
        try {
            $this->loadPlugin();
            $this->fail('Expected loading the plugin to fail');
        } catch (AssertionError $e) {
            $this->assertStringContainsString($expected_message, $e->getMessage());
        }
    }

    /**
     * @return list<array{0:array<mixed,mixed>,1:string}>
     */
    public static function invalidDeclarationProvider(): array
    {
        $pattern = 'the name of an annotation must match /^phan-[a-z0-9_-]+$/';
        $reserved = 'which is an annotation of Phan itself';
        return [
            [['phan-Upper' => ''], '"phan-Upper" in getCustomAnnotations(): ' . $pattern],
            [['@phan-x' => ''], '"@phan-x" in getCustomAnnotations(): ' . $pattern],
            [['my-assert' => ''], '"my-assert" in getCustomAnnotations(): ' . $pattern],
            [['phan-' => ''], '"phan-" in getCustomAnnotations(): ' . $pattern],
            [['phan-x y' => ''], '"phan-x y" in getCustomAnnotations(): ' . $pattern],
            [['phan-x' => '', 'psalm-x' => ''], '"psalm-x" in getCustomAnnotations(): ' . $pattern],
            [[0 => 'phan-x'], '0 in getCustomAnnotations(): ' . $pattern],
            [['phan-param' => ''], '"phan-param" in getCustomAnnotations(), ' . $reserved],
            [['phan-suppress-next-line' => ''], '"phan-suppress-next-line" in getCustomAnnotations(), ' . $reserved],
            [['phan-immutable' => ''], '"phan-immutable" in getCustomAnnotations(), ' . $reserved],
            [['phan-debug-var' => ''], '"phan-debug-var" in getCustomAnnotations(), ' . $reserved],
            [['phan-x' => null], "must map the annotation 'phan-x' to a string"],
        ];
    }

    /**
     * @return list<string> the names and texts of the custom annotations of the comment $comment_text, as "name|text"
     */
    private static function annotationsOf(string $comment_text): array
    {
        $comment = Comment::fromStringInContext($comment_text, new CodeBase([], [], [], [], []), new Context(), 1, Comment::ON_FUNCTION);
        return \array_map(static function (CustomAnnotation $annotation): string {
            return $annotation->getName() . '|' . $annotation->getText();
        }, $comment->getCustomAnnotations());
    }

    public function testTextOfTheForms(): void
    {
        $this->loadPlugin();
        $this->assertSame(['phan-x|'], self::annotationsOf('/** @phan-x */'));
        $this->assertSame(['phan-x|a  b'], self::annotationsOf('/** @phan-x a  b**/'));
        $this->assertSame(['phan-x|Upper case'], self::annotationsOf("/**\n * @PHAN-X Upper case\n */"));
        // The inline form ends before the brace that closes the tag.
        $this->assertSame(['phan-x|inline {nested}'], self::annotationsOf("/**\n * @see {@phan-x inline {nested}} rest\n */"));
        $this->assertSame(['phan-x|unclosed {brace'], self::annotationsOf("/**\n * @see {@phan-x unclosed {brace\n */"));
        // A line that does not start with a tag is not read, as for the annotations of Phan.
        $this->assertSame([], self::annotationsOf("/**\n * {@phan-x at the start of the line}\n * Text @phan-x in the text\n */"));
        // Only the first tag of a line is read, as for the annotations of Phan.
        $this->assertSame(['phan-x|one @phan-x two'], self::annotationsOf("/**\n * @phan-x one @phan-x two\n */"));
        // The text does not continue on the next line.
        $this->assertSame(['phan-x|first', 'phan-x|second'], self::annotationsOf("/**\n * @phan-x first\n *   continued\n * @phan-x second\n */"));
        // A name ends before a character that cannot be part of it, as for the annotations of Phan.
        $this->assertSame(['phan-x|.y'], self::annotationsOf("/**\n * @phan-x.y\n * @phan-x-y\n */"));
        $this->assertSame([], $this->takeCalls(), 'The plugin is not called when a comment is built');
    }

    public function testMisspelledAnnotationSuggestsDeclaredName(): void
    {
        $this->loadPlugin();
        $collector = new BufferingCollector();
        Phan::setIssueCollector($collector);
        self::annotationsOf("/**\n * @phan-xy\n * @phan-zzzzzzzz\n */");
        $messages = \array_map(static function (IssueInstance $instance): string {
            return $instance->getIssue()->getType() . ' ' . $instance->getMessageAndMaybeSuggestion();
        }, $collector->getCollectedIssues());
        $this->assertCount(2, $messages);
        $this->assertStringStartsWith(Issue::MisspelledAnnotation . ' Saw misspelled annotation @phan-xy.', $messages[0]);
        $this->assertStringEndsWith('(Did you mean @phan-x)', $messages[0]);
        $this->assertStringNotContainsString('Did you mean', $messages[1]);
    }

    /**
     * In daemon mode, a file that changed is parsed again: the plugin is called for its new annotations,
     * and the elements return the new annotations.
     */
    public function testParsedAgainInDaemonMode(): void
    {
        $this->loadPlugin();
        $a = $this->writeFile('a.php', <<<'PHP'
<?php
/** @phan-x on A */
class CustomAnnotationA
{
    /** @phan-x on the property */
    public $prop;

    public function __construct(
        /** @phan-x on the promoted property */
        public int $promoted = 0
    ) {
    }

    /** @phan-x on the method */
    public function method(): void
    {
    }

    /** @phan-x on the constant */
    const C = 1;
}

/** @phan-x on the function */
function custom_annotation_f(): void
{
}

/** @phan-x on the global constant */
const CUSTOM_ANNOTATION_G = 1;
PHP);
        $b = $this->writeFile('b.php', <<<'PHP'
<?php
/** @phan-x on B */
class CustomAnnotationB extends CustomAnnotationA
{
}
PHP);

        $code_base = new CodeBase([], [], [], [], []);
        $code_base->enableUndoTracking();
        $tracker = $code_base->getUndoTracker();
        $this->assertNotNull($tracker);
        $files = [$a, $b];
        foreach ($files as $file) {
            Analysis::parseFile($code_base, $file);
        }
        $this->assertSame([
            '\CUSTOM_ANNOTATION_G: @phan-x on the global constant (28)',
            '\CustomAnnotationA: @phan-x on A (2)',
            '\CustomAnnotationA::C: @phan-x on the constant (19)',
            '\CustomAnnotationA::method: @phan-x on the method (14)',
            '\CustomAnnotationA::promoted: @phan-x on the promoted property (9)',
            '\CustomAnnotationA::prop: @phan-x on the property (5)',
            '\CustomAnnotationB: @phan-x on B (2)',
            '\custom_annotation_f: @phan-x on the function (23)',
        ], self::sorted($this->takeCalls()));

        $class_a = FullyQualifiedClassName::fromFullyQualifiedString('\CustomAnnotationA');
        $prop = FullyQualifiedPropertyName::make($class_a, 'prop');
        $promoted = FullyQualifiedPropertyName::make($class_a, 'promoted');
        $method = FullyQualifiedMethodName::make($class_a, 'method');
        $constant = FullyQualifiedClassConstantName::make($class_a, 'C');
        $function = FullyQualifiedFunctionName::fromFullyQualifiedString('\custom_annotation_f');
        $global_constant = FullyQualifiedGlobalConstantName::fromFullyQualifiedString('\CUSTOM_ANNOTATION_G');
        /** @return list<list<string>> */
        $describe_all = static function () use ($code_base, $class_a, $prop, $promoted, $method, $constant, $function, $global_constant): array {
            return [
                self::describe($code_base, $code_base->getClassByFQSEN($class_a)),
                self::describe($code_base, $code_base->getPropertyByFQSEN($prop)),
                self::describe($code_base, $code_base->getPropertyByFQSEN($promoted)),
                self::describe($code_base, $code_base->getMethodByFQSEN($method)),
                self::describe($code_base, $code_base->getClassConstantByFQSEN($constant)),
                self::describe($code_base, $code_base->getFunctionByFQSEN($function)),
                self::describe($code_base, $code_base->getGlobalConstantByFQSEN($global_constant)),
                self::describe($code_base, $code_base->getClassByFQSEN(FullyQualifiedClassName::fromFullyQualifiedString('\CustomAnnotationB'))),
            ];
        };
        $this->assertSame([
            ['@phan-x on A (2)'],
            ['@phan-x on the property (5)'],
            ['@phan-x on the promoted property (9)'],
            ['@phan-x on the method (14)'],
            ['@phan-x on the constant (19)'],
            ['@phan-x on the function (23)'],
            ['@phan-x on the global constant (28)'],
            ['@phan-x on B (2)'],
        ], $describe_all());

        // a.php changes: the annotations move one line down, two of them change, and the property has none.
        $this->writeFile('a.php', \str_replace(
            ["<?php\n", '/** @phan-x on the property */', 'on the method', 'on the function'],
            ["<?php\n\n", '/** Not annotated */', 'on the changed method', 'on the changed function'],
            (string)\file_get_contents($a)
        ));
        $changed = $tracker->updateFileList($code_base, $files, []);
        $this->assertSame([$a], $changed);
        foreach ($changed as $file) {
            Analysis::parseFile($code_base, $file);
        }
        // The plugin is only called for the file that was parsed again.
        $this->assertSame([
            '\CUSTOM_ANNOTATION_G: @phan-x on the global constant (29)',
            '\CustomAnnotationA: @phan-x on A (3)',
            '\CustomAnnotationA::C: @phan-x on the constant (20)',
            '\CustomAnnotationA::method: @phan-x on the changed method (15)',
            '\CustomAnnotationA::promoted: @phan-x on the promoted property (10)',
            '\custom_annotation_f: @phan-x on the changed function (24)',
        ], self::sorted($this->takeCalls()));
        $this->assertSame([
            ['@phan-x on A (3)'],
            [],
            ['@phan-x on the promoted property (10)'],
            ['@phan-x on the changed method (15)'],
            ['@phan-x on the constant (20)'],
            ['@phan-x on the changed function (24)'],
            ['@phan-x on the global constant (29)'],
            ['@phan-x on B (2)'],
        ], $describe_all());
    }

    /**
     * @param list<string> $calls
     * @return list<string> the calls in a stable order (the order of the calls for the members of a class follows the AST)
     */
    private static function sorted(array $calls): array
    {
        \sort($calls);
        return $calls;
    }
}
