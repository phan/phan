<?php

declare(strict_types=1);

namespace Phan\Tests\Output;

use Phan\Output\Colorizing;
use Phan\Tests\BaseTest;

/**
 * Unit tests of Colorizing and the color schemes
 * @phan-file-suppress PhanAccessClassConstantInternal
 */
final class ColorizingTest extends BaseTest
{
    public function testColorSchemesAreValid(): void
    {
        foreach (Colorizing::COLOR_SCHEMES as $scheme_name => $_) {
            $colors = Colorizing::loadColorScheme($scheme_name);
            $this->assertNotNull($colors, "could not fetch $scheme_name");
            $this->assertSame(\array_keys($colors), \array_keys(Colorizing::DEFAULT_COLOR_FOR_TEMPLATE));
            foreach ($colors as $template_name => $color) {
                $this->assertNotNull(Colorizing::computeColorCode($color), "Unexpected value for scheme $scheme_name template $template_name (color=$color)");
            }
        }
    }
}
