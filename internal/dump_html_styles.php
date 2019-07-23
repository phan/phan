#!/usr/bin/env php
<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Phan/Bootstrap.php';

use Phan\Output\Colorizing;

/**
 * A utility to dump the terminal color codes as HTML styles
 */
class DumpHTMLStyles {
    /**
     * Given a terminal color name such as 'light_red', return 'style: LightRed'
     */
    public static function generateCssForColor(string $color) : string {
        $combination = [];
        foreach (explode(',', $color) as $color_component) {
            if ($color_component === 'none') {
                continue;
            }
            $name = 'color';
            if (preg_match('/^bg_/', $color_component)) {
                $color_component = (string)substr($color_component, 3);
                $name = 'background_color';
            }
            $combination[] = sprintf('%s: %s;', $name, str_replace('_', '', ucwords($color_component, '_')));
        }
        return implode(' ', $combination);
    }
    /**
     * Returns the colorscheme name as an HTML scheme.
     */
    public static function generateHTMLStyle(string $color_scheme_name) : string {
        $scheme = Colorizing::loadColorScheme($color_scheme_name);
        if (!is_array($scheme)) {
            throw new TypeError("Expected loadColorScheme to return an array");
        }
        $groups = [];
        ksort($scheme);

        foreach ($scheme as $name => $value) {
            if (!isset($groups[$value])) {
                $groups[$value] = [];
            }
            $groups[$value][] = $name;
        }
        $entries = [];
        foreach ($groups as $color_name => $template_names) {
            if ($color_name === 'none') {
                continue;
            }
            $css_selector = implode(', ', array_map(function (string $template_name) : string {
                return '.phan_' . strtolower($template_name);
            }, $template_names));
            $entries[] = sprintf("%s {\n    %s\n}", $css_selector, self::generateCssForColor($color_name));
        }
        return implode("\n", $entries);
    }

    /**
     * Dumps all styles
     * @suppress PhanAccessClassConstantInternal
     */
    public static function main() : void {
        foreach (Colorizing::COLOR_SCHEMES as $name => $_) {
            $contents = self::generateHTMLStyle($name);
            echo "/* Colorscheme '$name': */\n" . $contents . "\n";
        }
    }
}

DumpHTMLStyles::main();
