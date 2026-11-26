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
use Phan\PluginV3\CustomAnnotationCapability;
use Phan\Tests\TestBase;

/**
 * Tests of CustomAnnotationCapability that tests/custom_annotation_test does not cover: the prefix of the names,
 * the text of the forms of an annotation, the checks of the declared names, and the annotations after a file is parsed
 * again in daemon mode.
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
        $GLOBALS[self::NAMES_KEY] = ['phan-x-a' => 'The test annotation'];
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
     * The files other than Builder.php that give a meaning to annotations starting with `phan-`
     * (in string literals used as statements, in suppression comments, and in doc comments read by a plugin).
     */
    private const OTHER_FILES_WITH_PHAN_ANNOTATIONS = [
        'src/Phan/BlockAnalysisVisitor.php',
        'src/Phan/Parse/ParseVisitor.php',
        'src/Phan/Plugin/Internal/BuiltinSuppressionPlugin.php',
        '.phan/plugins/SleepCheckerPlugin.php',
    ];

    /**
     * Phan gives no meaning of its own to the names with the prefix of the annotations of plugins.
     */
    public function testPhanDoesNotUseThePrefixOfPlugins(): void
    {
        $prefix = CustomAnnotationCapability::NAME_PREFIX;
        $this->assertSame('phan-x-', $prefix);
        $root = \dirname(__DIR__, 3);
        $builder_path = $root . '/src/Phan/Language/Element/Comment/Builder.php';
        $source = \file_get_contents($builder_path);
        $this->assertIsString($source);
        \preg_match_all("/case '(phan-[a-z0-9_-]+)':/", $source, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $name) {
            $this->assertStringStartsNotWith($prefix, $name, "Builder handles $name");
        }
        foreach (Builder::SUPPORTED_ANNOTATIONS as $annotation => $_) {
            $this->assertStringStartsNotWith('@' . $prefix, $annotation);
        }
        // No string literal of these files (which includes their regular expressions) contains the prefix.
        foreach (\array_merge([$builder_path], \array_map(static fn(string $path): string => "$root/$path", self::OTHER_FILES_WITH_PHAN_ANNOTATIONS)) as $path) {
            $contents = \file_get_contents($path);
            $this->assertIsString($contents, $path);
            $this->assertStringContainsString('phan-', $contents, $path);
            foreach (\token_get_all($contents) as $token) {
                if (\is_array($token) && \in_array($token[0], [\T_CONSTANT_ENCAPSED_STRING, \T_ENCAPSED_AND_WHITESPACE], true)) {
                    $this->assertStringNotContainsStringIgnoringCase($prefix, $token[1], "$path:$token[2]");
                }
            }
        }
        // An annotation with the prefix that no plugin declares is reported as misspelled.
        $collector = new BufferingCollector();
        Phan::setIssueCollector($collector);
        $this->assertSame([], self::annotationsOf("/**\n * @phan-x-param int \$x\n * @phan-x-suppress PhanUndeclaredClass\n */"));
        $messages = \array_map(static function (IssueInstance $instance): string {
            return $instance->getIssue()->getType() . ' ' . \explode(' The annotations', $instance->getMessage())[0];
        }, $collector->getCollectedIssues());
        \sort($messages);
        $this->assertSame([
            Issue::MisspelledAnnotation . ' Saw misspelled annotation @phan-x-param.',
            Issue::MisspelledAnnotation . ' Saw misspelled annotation @phan-x-suppress.',
        ], $messages);
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
        $rule = " in getCustomAnnotations(): the name of an annotation declared by a plugin must start with 'phan-x-', "
            . "followed by a lowercase letter or digit and then lowercase letters, digits, '_' or '-' (/^phan-x-[a-z0-9][a-z0-9_-]*$/, without '@')";
        return [
            // Without the prefix
            [['phan-my-assert' => ''], '"phan-my-assert"' . $rule],
            [['my-assert' => ''], '"my-assert"' . $rule],
            // An annotation of Phan is rejected by the same rule
            [['phan-param' => ''], '"phan-param"' . $rule],
            // The prefix alone, or without its last character
            [['phan-x-' => ''], '"phan-x-"' . $rule],
            [['phan-x' => ''], '"phan-x"' . $rule],
            // Not in lowercase
            [['phan-x-Upper' => ''], '"phan-x-Upper"' . $rule],
            [['PHAN-X-UPPER' => ''], '"PHAN-X-UPPER"' . $rule],
            // With '@'
            [['@phan-x-a' => ''], '"@phan-x-a"' . $rule],
            // Other characters
            [['phan-x-a b' => ''], '"phan-x-a b"' . $rule],
            [['phan-x-a.b' => ''], '"phan-x-a.b"' . $rule],
            [['phan-x--a' => ''], '"phan-x--a"' . $rule],
            [['phan-x-a' => '', 'phan-x-é' => ''], '"phan-x-é"' . $rule],
            // Not a string
            [[0 => 'phan-x-a'], '0' . $rule],
            [['phan-x-a' => null], "must map the annotation 'phan-x-a' to a string"],
        ];
    }

    public function testValidDeclaration(): void
    {
        $GLOBALS[self::NAMES_KEY] = ['phan-x-a' => '', 'phan-x-0' => '', 'phan-x-my_assert-2' => 'Description'];
        $this->loadPlugin();
        $this->assertSame(['phan-x-a', 'phan-x-0', 'phan-x-my_assert-2'], \array_keys(ConfigPluginSet::instance()->getCustomAnnotationPluginMap()));
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
        $this->assertSame(['phan-x-a|'], self::annotationsOf('/** @phan-x-a */'));
        $this->assertSame(['phan-x-a|a  b'], self::annotationsOf('/** @phan-x-a a  b**/'));
        $this->assertSame(['phan-x-a|Upper case'], self::annotationsOf("/**\n * @PHAN-X-A Upper case\n */"));
        // The inline form ends before the brace that closes the tag.
        $this->assertSame(['phan-x-a|inline {nested}'], self::annotationsOf("/**\n * @see {@phan-x-a inline {nested}} rest\n */"));
        $this->assertSame(['phan-x-a|unclosed {brace'], self::annotationsOf("/**\n * @see {@phan-x-a unclosed {brace\n */"));
        // A line that does not start with a tag is not read, as for the annotations of Phan.
        $this->assertSame([], self::annotationsOf("/**\n * {@phan-x-a at the start of the line}\n * Text @phan-x-a in the text\n */"));
        // Only the first tag of a line is read, as for the annotations of Phan.
        $this->assertSame(['phan-x-a|one @phan-x-a two'], self::annotationsOf("/**\n * @phan-x-a one @phan-x-a two\n */"));
        // The text does not continue on the next line.
        $this->assertSame(['phan-x-a|first', 'phan-x-a|second'], self::annotationsOf("/**\n * @phan-x-a first\n *   continued\n * @phan-x-a second\n */"));
        // A name ends before a character that cannot be part of it, as for the annotations of Phan:
        // a longer name that starts with the declared name is another annotation.
        $this->assertSame(['phan-x-a|.y'], self::annotationsOf("/**\n * @phan-x-a.y\n * @phan-x-a-y\n * @phan-x-ab\n */"));
        $this->assertSame([], $this->takeCalls(), 'The plugin is not called when a comment is built');
    }

    public function testMisspelledAnnotationSuggestsDeclaredName(): void
    {
        $GLOBALS[self::NAMES_KEY] = ['phan-x-a' => '', 'phan-x-test-mark' => ''];
        $this->loadPlugin();
        $collector = new BufferingCollector();
        Phan::setIssueCollector($collector);
        self::annotationsOf("/**\n * @phan-x-ab\n * @phan-zzzzzzzz\n * @phan-test-mark the old form without the prefix\n */");
        $messages = \array_map(static function (IssueInstance $instance): string {
            return $instance->getIssue()->getType() . ' ' . $instance->getMessageAndMaybeSuggestion();
        }, $collector->getCollectedIssues());
        \sort($messages);
        $this->assertCount(3, $messages);
        // A plugin annotation used without the prefix is not that annotation: it is misspelled, and the suggestion names it.
        $this->assertStringStartsWith(Issue::MisspelledAnnotation . ' Saw misspelled annotation @phan-test-mark.', $messages[0]);
        $this->assertStringEndsWith('(Did you mean @phan-x-test-mark)', $messages[0]);
        $this->assertStringStartsWith(Issue::MisspelledAnnotation . ' Saw misspelled annotation @phan-x-ab.', $messages[1]);
        $this->assertStringEndsWith('(Did you mean @phan-x-a)', $messages[1]);
        $this->assertStringStartsWith(Issue::MisspelledAnnotation . ' Saw misspelled annotation @phan-zzzzzzzz.', $messages[2]);
        $this->assertStringNotContainsString('Did you mean', $messages[2]);
    }

    /**
     * Each occurrence of a custom annotation gets its own line, also when the following lines of the comment are identical
     * (guessActualLineLocation() alone returns the line of the last identical line for them).
     */
    public function testLinesOfIdenticalLines(): void
    {
        $this->loadPlugin();
        $file = $this->writeFile('lines.php', <<<'PHP'
<?php
/**
 * @phan-x-a
 * @phan-x-a
 */
function custom_annotation_two() {}

/** @phan-x-a on one line */
function custom_annotation_one_line() {}

/**
 * @phan-x-a last
 * @phan-x-a last */
function custom_annotation_tag_on_last_line() {}

/**
 * @phan-x-a merged
 * @phan-x-a merged
 * @param array{
 *   a: int
 * } $x
 */
function custom_annotation_merged(array $x) {}
PHP);
        $code_base = new CodeBase([], [], [], [], []);
        Analysis::parseFile($code_base, $file);
        $this->assertSame([
            '\custom_annotation_two: @phan-x-a (3)',
            '\custom_annotation_two: @phan-x-a (4)',
            '\custom_annotation_one_line: @phan-x-a on one line (8)',
            '\custom_annotation_tag_on_last_line: @phan-x-a last (12)',
            '\custom_annotation_tag_on_last_line: @phan-x-a last (13)',
            // reduceMultiline() merges the lines of the (at)param and keeps the number of lines of the comment,
            // so the lines of the other annotations are still those of the source.
            '\custom_annotation_merged: @phan-x-a merged (17)',
            '\custom_annotation_merged: @phan-x-a merged (18)',
        ], $this->takeCalls());
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
/** @phan-x-a on A */
class CustomAnnotationA
{
    /** @phan-x-a on the property */
    public $prop;

    public function __construct(
        /** @phan-x-a on the promoted property */
        public int $promoted = 0
    ) {
    }

    /** @phan-x-a on the method */
    public function method(): void
    {
    }

    /** @phan-x-a on the constant */
    const C = 1;
}

/** @phan-x-a on the function */
function custom_annotation_f(): void
{
}

/** @phan-x-a on the global constant */
const CUSTOM_ANNOTATION_G = 1;
PHP);
        $b = $this->writeFile('b.php', <<<'PHP'
<?php
/** @phan-x-a on B */
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
            '\CUSTOM_ANNOTATION_G: @phan-x-a on the global constant (28)',
            '\CustomAnnotationA: @phan-x-a on A (2)',
            '\CustomAnnotationA::C: @phan-x-a on the constant (19)',
            '\CustomAnnotationA::method: @phan-x-a on the method (14)',
            '\CustomAnnotationA::promoted: @phan-x-a on the promoted property (9)',
            '\CustomAnnotationA::prop: @phan-x-a on the property (5)',
            '\CustomAnnotationB: @phan-x-a on B (2)',
            '\custom_annotation_f: @phan-x-a on the function (23)',
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
            ['@phan-x-a on A (2)'],
            ['@phan-x-a on the property (5)'],
            ['@phan-x-a on the promoted property (9)'],
            ['@phan-x-a on the method (14)'],
            ['@phan-x-a on the constant (19)'],
            ['@phan-x-a on the function (23)'],
            ['@phan-x-a on the global constant (28)'],
            ['@phan-x-a on B (2)'],
        ], $describe_all());

        // a.php changes: the annotations move one line down, two of them change, and the property has none.
        $this->writeFile('a.php', \str_replace(
            ["<?php\n", '/** @phan-x-a on the property */', 'on the method', 'on the function'],
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
            '\CUSTOM_ANNOTATION_G: @phan-x-a on the global constant (29)',
            '\CustomAnnotationA: @phan-x-a on A (3)',
            '\CustomAnnotationA::C: @phan-x-a on the constant (20)',
            '\CustomAnnotationA::method: @phan-x-a on the changed method (15)',
            '\CustomAnnotationA::promoted: @phan-x-a on the promoted property (10)',
            '\custom_annotation_f: @phan-x-a on the changed function (24)',
        ], self::sorted($this->takeCalls()));
        $this->assertSame([
            ['@phan-x-a on A (3)'],
            [],
            ['@phan-x-a on the promoted property (10)'],
            ['@phan-x-a on the changed method (15)'],
            ['@phan-x-a on the constant (20)'],
            ['@phan-x-a on the changed function (24)'],
            ['@phan-x-a on the global constant (29)'],
            ['@phan-x-a on B (2)'],
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
