<?php declare(strict_types = 1);
namespace Phan\Tests\Language\Element;

use Phan\Tests\BaseTest;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Comment;
use Phan\Language\Type;
use Phan\Language\Type\StaticType;
use Phan\Library\None;

/**
 * Unit tests of Type
 */
class CommentTest extends BaseTest
{
    /** @var CodeBase|null */
    protected $code_base = null;

    /** @var bool */
    const overrides = [
        'read_type_annotations' => true,
        'read_magic_property_annotations' => true,
        'read_magic_method_annotations' => true,
    ];

    protected $old_values = [];

    protected function setUp()
    {
        $this->code_base = new CodeBase([], [], [], [], []);
        foreach (self::overrides as $key => $value) {
            $this->old_values[$key] = Config::getValue($key);
            Config::setValue($key, $value);
        }
    }

    protected function tearDown()
    {
        $this->code_base = null;
        foreach ($this->old_values as $key => $value) {
            Config::setValue($key, $value);
        }
    }

    public function testEmptyComment()
    {
        $comment = Comment::fromStringInContext(
            '/** foo */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $this->assertFalse($comment->isDeprecated());
        $this->assertFalse($comment->isOverrideIntended());
        $this->assertFalse($comment->isNSInternal());
        $this->assertSame('', (string)$comment->getReturnType());
        $this->assertFalse($comment->hasReturnUnionType());
        $this->assertInstanceOf(None::class, $comment->getClosureScopeOption());
        $this->assertSame([], $comment->getParameterList());
        $this->assertSame([], $comment->getParameterMap());
        $this->assertSame([], $comment->getSuppressIssueList());
        $this->assertFalse($comment->hasParameterWithNameOrOffset('bar', 0));
        $this->assertSame([], $comment->getVariableList());
    }

    public function testGetParameterMap()
    {
        $comment = Comment::fromStringInContext(
            '/** @param int $myParam */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['myParam'], \array_keys($parameter_map));
        $this->assertSame([], $comment->getParameterList());
        $my_param_doc = $parameter_map['myParam'];
        $this->assertSame('int $myParam', (string)$my_param_doc);
        $this->assertFalse($my_param_doc->isOptional());
        $this->assertTrue($my_param_doc->isRequired());
        $this->assertFalse($my_param_doc->isVariadic());
        $this->assertSame('myParam', $my_param_doc->getName());
        $this->assertFalse($my_param_doc->isOutputReference());
    }

    public function testGetParameterMapReferenceIgnored()
    {
        $comment = Comment::fromStringInContext(
            '/** @param int &$myParam */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['myParam'], \array_keys($parameter_map));
        $this->assertSame([], $comment->getParameterList());
        $my_param_doc = $parameter_map['myParam'];
        $this->assertSame('int $myParam', (string)$my_param_doc);
        $this->assertFalse($my_param_doc->isOptional());
        $this->assertTrue($my_param_doc->isRequired());
        $this->assertFalse($my_param_doc->isVariadic());
        $this->assertSame('myParam', $my_param_doc->getName());
        $this->assertFalse($my_param_doc->isOutputReference());
    }

    public function testGetVariadicParameterMap()
    {
        $comment = Comment::fromStringInContext(
            '/** @param int|string ...$args */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['args'], \array_keys($parameter_map));
        $this->assertSame([], $comment->getParameterList());
        $my_param_doc = $parameter_map['args'];
        $this->assertSame('int|string ...$args', (string)$my_param_doc);
        $this->assertTrue($my_param_doc->isOptional());
        $this->assertFalse($my_param_doc->isRequired());
        $this->assertTrue($my_param_doc->isVariadic());
        $this->assertSame('args', $my_param_doc->getName());
        $this->assertFalse($my_param_doc->isOutputReference());
    }

    public function testGetOutputParameter()
    {
        $comment = Comment::fromStringInContext(
            "/** @param int|string \$args @phan-output-reference\n@param string \$other*/",
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );

        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['args', 'other'], \array_keys($parameter_map));
        $this->assertTrue($parameter_map['args']->isOutputReference());
        $this->assertFalse($parameter_map['other']->isOutputReference());
    }


    public function testGetReturnType()
    {
        $comment = Comment::fromStringInContext(
            '/** @return int|string */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $this->assertTrue($comment->hasReturnUnionType());
        $return_type = $comment->getReturnType();
        $this->assertSame('int|string', (string)$return_type);
    }

    public function testGetReturnTypeThis()
    {
        $comment = Comment::fromStringInContext(
            '/** @return $this */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $this->assertTrue($comment->hasReturnUnionType());
        $return_type = $comment->getReturnType();
        $this->assertSame('static', (string)$return_type);
        $this->assertTrue($return_type->hasType(StaticType::instance(false)));
    }

    public function testGetMagicProperty()
    {
        $comment = Comment::fromStringInContext(
            '/** @property int|string   $myProp */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_CLASS
        );
        $this->assertTrue($comment->hasMagicPropertyWithName('myProp'));
        $property = $comment->getMagicPropertyMap()['myProp'];
        $this->assertSame('int|string $myProp', (string)$property);
    }

    public function testGetMagicMethod()
    {
        $commentText = <<<'EOT'
/**
 * @method static int|string my_method(int $x, stdClass ...$rest) description
 * @method myInstanceMethod2(int, $other = 'myString') description
 */
EOT;
        $comment = Comment::fromStringInContext(
            $commentText,
            $this->code_base,
            new Context(),
            1,
            Comment::ON_CLASS
        );
        $methodMap = $comment->getMagicMethodMap();
        $this->assertSame(['my_method', 'myInstanceMethod2'], \array_keys($methodMap));
        $methodDefinition = $methodMap['my_method'];
        $this->assertSame('static function my_method(int $x, \stdClass ...$rest) : int|string', (string)$methodDefinition);
        $this->assertSame('my_method', $methodDefinition->getName());
        $instanceMethodDefinition = $methodMap['myInstanceMethod2'];
        $this->assertSame('function myInstanceMethod2(int $p1, $other = default) : void', (string)$instanceMethodDefinition);
        $this->assertSame('myInstanceMethod2', $instanceMethodDefinition->getName());
    }

    public function testGetTemplateType()
    {
        $commentText = <<<'EOT'
/**
 * The check for template is case sensitive.
 * @template T1
 * @Template TestIgnored
 * @template u
 */
EOT;
        $comment = Comment::fromStringInContext(
            $commentText,
            $this->code_base,
            new Context(),
            1,
            Comment::ON_CLASS
        );
        $templateTypes = $comment->getTemplateTypeList();
        $this->assertCount(2, $templateTypes);
        $t1Info = $templateTypes[0];
        $this->assertSame('T1', $t1Info->getName());
        $uInfo = $templateTypes[1];
        $this->assertSame('u', $uInfo->getName());
    }

    public function testGetParameterArrayNew()
    {
        // Currently, we ignore the array key. This may change in a future release.
        $commentText = <<<'EOT'
/**
 * @param array<mixed, string> $myParam
 * @param array<string , stdClass> ...$rest
 */
EOT;
        $comment = Comment::fromStringInContext(
            $commentText,
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['myParam', 'rest'], \array_keys($parameter_map));
        $this->assertSame([], $comment->getParameterList());
        $my_param_doc = $parameter_map['myParam'];
        $this->assertSame('string[] $myParam', (string)$my_param_doc);
        $this->assertFalse($my_param_doc->isOptional());
        $this->assertTrue($my_param_doc->isRequired());
        $this->assertFalse($my_param_doc->isVariadic());
        $this->assertSame('myParam', $my_param_doc->getName());

        // Argument #2, #3, etc. passed by callers are arrays of stdClasses
        $restDoc = $parameter_map['rest'];
        $this->assertSame('\stdClass[] ...$rest', (string)$restDoc);
        $this->assertTrue($restDoc->isOptional());
        $this->assertFalse($restDoc->isRequired());
        $this->assertTrue($restDoc->isVariadic());
        $this->assertSame('rest', $restDoc->getName());
    }

    public function testGetVarArrayNew()
    {
        // Currently, we ignore the array key. This may change in a future release.
        $commentText = <<<'EOT'
/**
 * @var int $my_int
 * @var array<string , stdClass> $array
 * @var float (Unparseable)
 */
EOT;
        $comment = Comment::fromStringInContext(
            $commentText,
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $this->assertSame([], $comment->getParameterMap());
        $this->assertSame([], $comment->getParameterList());
        $var_map = $comment->getVariableList();
        $this->assertSame([0, 1], \array_keys($var_map));
        $my_int_doc = $var_map[0];
        $this->assertSame('int $my_int', (string)$my_int_doc);
        $this->assertSame('my_int', $my_int_doc->getName());

        $array_doc = $var_map[1];
        $this->assertSame('\stdClass[] $array', (string)$array_doc);
        $this->assertSame('array', $array_doc->getName());
    }

    public function testGetClosureScope()
    {
        $comment = Comment::fromStringInContext(
            '/** @phan-closure-scope MyNS\MyClass */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_FUNCTION  // ON_CLOSURE doesn't exist yet.
        );
        $scope_option = $comment->getClosureScopeOption();
        $this->assertTrue($scope_option->isDefined());
        $scope_type = $scope_option->get();
        $expected_type = Type::fromFullyQualifiedString('MyNS\MyClass');
        $this->assertEquals($expected_type, $scope_type);
        $this->assertSame($expected_type, $scope_type);
    }
}
