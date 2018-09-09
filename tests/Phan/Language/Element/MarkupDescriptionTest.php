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
                Comment::ON_METHOD,
            ],
            [
                '',
                '/** @var T $x A local variable annotation of a function goes here*/',
                Comment::ON_METHOD,
            ],
            [
                '@var MyClass An annotation of a property goes here',
                '/** @var MyClass An annotation of a property goes here */',
                Comment::ON_PROPERTY,
            ],
            // Allow the description of (at)var to be a summary for the property if there is no earlier summary
            [
                <<<EOT
@var MyClass A annotation of a constant goes here

Rest of this comment
EOT
                ,
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
            // Preserve leading whitespace when parsing the comment description
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
            // Preserve leading whitespace when parsing markup after (at)return
            [
                <<<EOT
@return int

Rest of this description

-  Example markup list
   Rest of that list
EOT
                ,
            <<<EOT
/**
 * @return int
 *
 * Rest of this description
 *
 * -  Example markup list
 *    Rest of that list
 *
 * @internal
 */
EOT
                ,
                Comment::ON_METHOD
            ],
            // Only parse (at)return on comments of function-likes
            [
                ''
                ,
            <<<EOT
/**
 * @return int
 *
 * Rest of this description
 */
EOT
                ,
                Comment::ON_PROPERTY
            ],
            // Parse summaries on adjacent lines
            [
                <<<EOT
@return int
Rest of this description
EOT
                ,
            <<<EOT
/**
 * @return int
 * Rest of this description
 *
 * @internal
 */
EOT
                ,
                Comment::ON_FUNCTION
            ],
            // Treat informative (at)return as function-like summaries.
            [
                '@return int positive',
            <<<EOT
/**
 * @return int positive
 */
EOT
                ,
                Comment::ON_FUNCTION
            ],
            // Treat informative single-line (at)return as function-like summaries.
            [
                '@return int positive',
                '/**   @return int positive */',
                Comment::ON_METHOD
            ],
            [
                '@return int self::MY_ENUM_* description',
                '/**   @return int self::MY_ENUM_* description */',
                Comment::ON_METHOD
            ],
            // Don't treat uninformative (at)return as function-like summaries.
            [
                '',
            <<<EOT
/**
 * @return string|false
 */
EOT
                ,
                Comment::ON_FUNCTION
            ],
        ];
    }
}
