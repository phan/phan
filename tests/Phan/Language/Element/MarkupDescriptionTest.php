<?php declare(strict_types = 1);
namespace Phan\Tests\Language\Element;

use Phan\Tests\BaseTest;
use Phan\Language\Element\Comment;
use Phan\Language\Element\MarkupDescription;

/**
 * Unit tests of MarkupDescription functionality
 */
final class MarkupDescriptionTest extends BaseTest
{
    /**
     * @dataProvider extractDocCommentProvider
     */
    public function testExtractDocComment(string $expected, string $doc_comment, int $category = null)
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        $this->assertSame($expected, MarkupDescription::extractDocComment($doc_comment, $category));
    }

    /**
     * @return array<int,array{0:string,1:string,2?:int}>
     */
    public function extractDocCommentProvider()
    {
        return [
            [
                'A description goes here',
                '/** A description goes here */',
            ],
            [
                '',
                '/** @param T $x A parameter annotation goes here */',
            ],
            [
                '',
                '/** @var T $x A local variable annotation of a function goes here*/',
                Comment::ON_METHOD
            ],
            [
                '@var MyClass An annotation of a property goes here',
                '/** @var MyClass An annotation of a property goes here */',
                Comment::ON_PROPERTY,
            ],
            [
                '@var MyClass A annotation of a constant goes here',
                <<<EOT
/**
 * @var MyClass A annotation of a constant goes here
 *
 * Rest of this comment
 */
EOT
                ,
                Comment::ON_CONST,
            ],
            [
                <<<EOT
A description goes here

Rest of this description

-  Example markup list
   Rest of that list
EOT
                ,
            <<<EOT
/**
 * A description goes here
 *
 * Rest of this description
 *
 * -  Example markup list
 *    Rest of that list
 */
EOT
            ],
        ];
    }
}
