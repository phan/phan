<?php
declare(strict_types=1);

namespace Phan\Output;

use Phan\Config;
use Phan\Issue;
use Phan\Language\Element\TypedElementInterface;
use Phan\Language\Element\UnaddressableTypedElement;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Contains utilities for colorizing Phan's issue messages (and colorized CLI output in general)
 *
 * Colorizing codes are based on https://github.com/kevinlebrun/colors.php/
 */
class Colorizing
{
    const STYLES = [
        'none'             => '0',  // Alias of 'reset'
        'reset'            => '0',  // Use 'reset' for the absence of color.
        'bold'             => '1',
        'dark'             => '2',
        'italic'           => '3',
        'underline'        => '4',
        'blink'            => '5',
        'reverse'          => '7',
        'concealed'        => '8',
        'default'          => '39',
        'black'            => '30',
        'red'              => '31',
        'green'            => '32',
        'yellow'           => '33',
        'blue'             => '34',
        'magenta'          => '35',
        'cyan'             => '36',
        'light_gray'       => '37',
        'dark_gray'        => '90',
        'light_red'        => '91',
        'light_green'      => '92',
        'light_yellow'     => '93',
        'light_blue'       => '94',
        'light_magenta'    => '95',
        'light_cyan'       => '96',
        'white'            => '97',
        'bg_default'       => '49',
        'bg_black'         => '40',
        'bg_red'           => '41',
        'bg_green'         => '42',
        'bg_yellow'        => '43',
        'bg_blue'          => '44',
        'bg_magenta'       => '45',
        'bg_cyan'          => '46',
        'bg_light_gray'    => '47',
        'bg_dark_gray'     => '100',
        'bg_light_red'     => '101',
        'bg_light_green'   => '102',
        'bg_light_yellow'  => '103',
        'bg_light_blue'    => '104',
        'bg_light_magenta' => '105',
        'bg_light_cyan'    => '106',
        'bg_white'         => '107',
    ];

    const ESC_PATTERN = "\033[%sm";
    const ESC_RESET = "\033[0m";

    // NOTE: Keep sorted and in sync with Issue::UNCOLORED_FORMAT_STRING_FOR_TEMPLATE
    // By using 'color_scheme' in .phan/config.php, these settings can be overridden
    const DEFAULT_COLOR_FOR_TEMPLATE = [
        'CLASS'         => 'green',
        'CLASSLIKE'     => 'green',
        'CODE'          => 'light_magenta',
        'COMMENT'       => 'light_green',
        'CONST'         => 'light_red',
        'COUNT'         => 'light_magenta',
        'DETAILS'       => 'light_green',
        'FILE'          => 'light_cyan',
        'FUNCTIONLIKE'  => 'light_yellow',
        'FUNCTION'      => 'light_yellow',
        'INDEX'         => 'light_magenta',
        'INTERFACE'     => 'green',
        'ISSUETYPE'     => 'light_yellow',  // used by Phan\Output\Printer, for minor issues
        'ISSUETYPE_CRITICAL' => 'red',  // for critical issues, e.g. "PhanUndeclaredMethod"
        'ISSUETYPE_NORMAL' => 'light_red',  // for normal issues
        'LINE'          => 'light_gray',
        'METHOD'        => 'light_yellow',
        'NAMESPACE'     => 'green',
        'OPERATOR'      => 'red',
        'PARAMETER'     => 'cyan',
        'PROPERTY'      => 'cyan',
        'SCALAR'        => 'light_magenta',
        'STRING_LITERAL' => 'light_magenta',
        'SUGGESTION'    => 'light_gray',
        'TYPE'          => 'light_gray',
        'TRAIT'         => 'green',
        'VARIABLE'      => 'light_cyan',
    ];

    /**
     * @var array<string,string>|null - Lazily initialized from Config, if set
     */
    private static $color_scheme = null;

    /**
     * Returns a version of $template where template strings (e.g. `{FILE}`
     * are replaced with printf conversion specifiers (e.g. `%s`)
     * and color control codes are inserted before/after those conversion specifiers.
     *
     * @param string $template
     * @param list<int|string|float|FQSEN|Type|UnionType|TypedElementInterface|UnaddressableTypedElement> $template_parameters
     */
    public static function colorizeTemplate(
        string $template,
        array $template_parameters
    ) : string {
        $i = 0;
        /** @param list<string> $matches */
        return \preg_replace_callback('/(\$?){([A-Z_]+)}|%[sdf]/', static function (array $matches) use ($template, $template_parameters, &$i) : string {
            $j = $i++;
            if ($j >= \count($template_parameters)) {
                \error_log("Missing argument for colorized output ($template), offset $j");
                return '(MISSING)';
            }
            $arg = $template_parameters[$j];
            if (\is_object($arg)) {
                $arg = (string)$arg;
            }
            $format_str = $matches[0];
            if ($format_str[0] === '%') {
                // @phan-suppress-next-line PhanPluginPrintfVariableFormatString this is %s, %d, or %f
                return \sprintf($format_str, $arg);
            }
            $prefix = $matches[1];
            $template = $matches[2];
            if ($prefix) {
                $arg = $prefix . $arg;
            }
            return self::colorizeField($template, $arg);
        }, $template);
    }

    /**
     * @param string $template_type (A key of _UNCOLORED_FORMAT_STRING_FOR_TEMPLATE, e.g. "FILE")
     * @param int|string|float|FQSEN|Type|UnionType $arg (Argument for format string, e.g. a type name, method fqsen, line number, etc.)
     * @return string - Colorized for Unix terminals.
     */
    public static function colorizeField(string $template_type, $arg) : string
    {
        $fmt_directive = Issue::UNCOLORED_FORMAT_STRING_FOR_TEMPLATE[$template_type] ?? null;
        if ($fmt_directive === null) {
            \error_log(\sprintf(
                "Unknown template type '%s'. Known template types: %s",
                $template_type,
                \implode(', ', \array_keys(Issue::UNCOLORED_FORMAT_STRING_FOR_TEMPLATE))
            ));
            return (string)$arg;
        }
        // TODO: Add more complicated color coding, e.g. MyClass::method should have the option for multiple colors.
        // TODO: Allow choosing color schemes via .phan/config.php
        // @phan-suppress-next-line PhanPluginPrintfVariableFormatString this is %s/%d/%f
        $arg_str = \sprintf($fmt_directive, (string)$arg);
        $color = self::colorForTemplate($template_type);
        if ($color === null || $color === '') {
            \error_log("No color information for template type $template_type");
            return $arg_str;
        }
        $color_code = self::computeColorCode($color);
        if ($color_code === null) {
            \error_log("Invalid color name ($color) for template type $template_type");
            return $arg_str;
        }
        // TODO: Could extend this to support background colors.
        return self::colorizeTextWithColorCode($color_code, $arg_str);
    }

    /**
     * Compute the color codes (separated by `;`) for the color names.
     * @param string $color one or more comma separated color names without spaces (e.g. 'none', 'light_gray')
     */
    public static function computeColorCode(string $color) : ?string
    {
        $color_codes = [];
        foreach (\explode(',', $color) as $color_component) {
            $color_code = self::STYLES[$color_component] ?? null;
            $color_codes[] = $color_code;
            if ($color_code === null) {
                return null;
            }
        }
        return \implode(';', $color_codes);
    }

    /**
     * Wrap this section of text in the specified color.
     */
    public static function colorizeTextWithColorCode(string $color_code, string $text) : string
    {
        if ($color_code == '0') {
            return $text;
        }
        return \sprintf(self::ESC_PATTERN, $color_code) . $text . self::ESC_RESET;
    }

    /**
     * @return ?string - null if there is no valid color
     */
    private static function colorForTemplate(string $template_type) : ?string
    {
        if (self::$color_scheme === null) {
            self::initColorScheme();
        }
        return self::$color_scheme[$template_type] ?? null;
    }

    /**
     * @internal
     */
    const COLOR_SCHEMES = [
        'code' => \Phan\Output\ColorScheme\Code::class,
        'default' => self::class,
        'eclipse_dark' => \Phan\Output\ColorScheme\EclipseDark::class,
        'light' => \Phan\Output\ColorScheme\Light::class,
        'vim' => \Phan\Output\ColorScheme\Vim::class,
    ];

    /**
     * @param string $name the name of the color scheme
     * @return ?array<string,string> maps the template names to their comma separated color codes.
     */
    public static function loadColorScheme(string $name) : ?array
    {
        if (\array_key_exists($name, self::COLOR_SCHEMES)) {
            return \constant(self::COLOR_SCHEMES[$name] . '::DEFAULT_COLOR_FOR_TEMPLATE');
        }
        return null;
    }

    /**
     * Initialize the color scheme, merging it with Config::color_scheme
     */
    private static function initColorScheme() : void
    {
        self::$color_scheme = self::DEFAULT_COLOR_FOR_TEMPLATE;
        $env_color_scheme = \getenv('PHAN_COLOR_SCHEME');
        if ($env_color_scheme) {
            $data = self::loadColorScheme($env_color_scheme);
            if ($data) {
                self::$color_scheme = $data;
            } else {
                \fwrite(\STDERR, "Unknown PHAN_COLOR_SCHEME $env_color_scheme. Supported values: " . \implode(',', \array_keys(self::COLOR_SCHEMES)) . "\n");
            }
        }
        foreach (Config::getValue('color_scheme') ?? [] as $template_type => $color_name) {
            if (!\is_string($color_name) || !\array_key_exists($color_name, self::STYLES)) {
                \error_log("Invalid color name ($color_name)");
                continue;
            }
            if (!\array_key_exists($template_type, Colorizing::DEFAULT_COLOR_FOR_TEMPLATE)) {
                \error_log("Unknown template_type ($template_type)");
                continue;
            }
            self::$color_scheme[$template_type] = $color_name;
        }
    }

    /**
     * Used to reset the chosen color scheme in tests.
     */
    public static function resetColorScheme() : void
    {
        self::$color_scheme = null;
    }
}
