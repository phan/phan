<?php declare(strict_types = 1);
namespace Phan\Tests\Language\Element;

use Phan\Tests\BaseTest;
use Phan\Language\Element\MarkupDescription;

/**
 * Unit tests of MarkupDescription functionality
 */
class MarkupDescriptionTest extends BaseTest
{
    /**
     * @dataProvider extractDocCommentProvider
     */
    public function testExtractDocComment(string $expected, string $doc_comment)
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        $this->assertSame($expected, MarkupDescription::extractDocComment($doc_comment));
    }

    /**
     * @return array<int,array{0:string,1:string}>
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
                '/** @var T $x A parameter annotation goes here */',
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
