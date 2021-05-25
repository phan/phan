<?php

declare(strict_types=1);

namespace Phan\Tests\Output;

use Phan\Output\HTML;
use Phan\Tests\BaseTest;

/**
 * Unit tests of converting to HTML and the color schemes
 */
final class HTMLTest extends BaseTest
{
    public function testHTMLTemplate(): void
    {
        $this->assertSame('Regular issue message', HTML::htmlTemplate('Regular issue message', []));
        $this->assertSame('Message at <span class="phan_file">test.php</span>:<span class="phan_line">23</span>', HTML::htmlTemplate('Message at {FILE}:{LINE}', ['test.php', 23]));
        $this->assertSame('Bad variable <span class="phan_variable">$varName</span>', HTML::htmlTemplate('Bad variable ${VARIABLE}', ['varName']));
    }
}
