<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\GlobalConstant;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\PluginV3\HandleLazyLoadInternalFunctionCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

use function count;
use function is_int;
use function ltrim;
use function strtolower;

/**
 * Emits PHP version-specific deprecation diagnostics.
 *
 * This plugin tracks deprecations across different PHP versions and only
 * emits warnings when targeting the version where something was deprecated.
 */
final class PhpDeprecationPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability,
    HandleLazyLoadInternalFunctionCapability,
    PostAnalyzeNodeCapability
{
    /** @var array<string,array{min_php_version:int,reason:string}> */
    private const DEPRECATED_FUNCTIONS = [
        // PHP 8.1 deprecations
        'date_sunrise' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date_sun_info() instead.',
        ],
        'date_sunset' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date_sun_info() instead.',
        ],
        'strftime' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date() or IntlDateFormatter::format() instead.',
        ],
        'gmstrftime' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date() or IntlDateFormatter::format() instead.',
        ],
        'strptime' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use date_parse_from_format() instead.',
        ],
        'mhash' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use hash() instead.',
        ],
        'mhash_keygen_s2k' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use hash_pbkdf2() instead.',
        ],
        'mhash_count' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use count(hash_algos()) instead.',
        ],
        'mhash_get_block_size' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use hash() functions instead.',
        ],
        'mhash_get_hash_name' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use hash() functions instead.',
        ],
        // PHP 8.2 deprecations
        'utf8_encode' => [
            'min_php_version' => 80200,
            'reason' => 'Deprecated in PHP 8.2. Use mb_convert_encoding() instead.',
        ],
        'utf8_decode' => [
            'min_php_version' => 80200,
            'reason' => 'Deprecated in PHP 8.2. Use mb_convert_encoding() instead.',
        ],
        // PHP 8.4 deprecations
        'intlcal_set' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use IntlCalendar::set(), IntlCalendar::setDate(), or IntlCalendar::setDateTime() instead.',
        ],
        'intlgregcal_create_instance' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use IntlGregorianCalendar::__construct(), IntlGregorianCalendar::createFromDate(), or IntlGregorianCalendar::createFromDateTime() instead.',
        ],
        'lcg_value' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use Random\\Randomizer::getFloat() instead.',
        ],
        'mysqli_ping' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Reconnect manually instead.',
        ],
        'mysqli_kill' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use mysqli_query() with KILL command instead.',
        ],
        'mysqli_refresh' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Use specific FLUSH commands via mysqli_query() instead.',
        ],
        // PHP 8.5 deprecations
        'curl_close' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Has no effect since PHP 8.0; handles are freed automatically.',
        ],
        'curl_share_close' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Has no effect since PHP 8.0; handles are freed automatically.',
        ],
        'finfo_close' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. finfo objects are freed automatically.',
        ],
        'imagedestroy' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Has no effect since PHP 8.0; images are freed automatically.',
        ],
        'ldap_connect_wallet' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Oracle LDAP support is broken since PHP 8.0.',
        ],
        'mysqli_execute' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Use mysqli_stmt_execute() instead.',
        ],
        'socket_set_timeout' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Use stream_set_timeout() instead.',
        ],
        'xml_parser_free' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Has no effect since PHP 8.0; parsers are freed automatically.',
        ],
    ];

    /** @var array<string,array{min_php_version:int,reason:string}> */
    private const DEPRECATED_CLASS_CONSTANTS = [
        // PHP 8.3 deprecations
        '\\NumberFormatter::TYPE_CURRENCY' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3. Use NumberFormatter::formatCurrency()/parseCurrency().',
        ],
        // PHP 8.5 deprecations
        '\\DateTimeInterface::RFC7231' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Use DateTimeInterface::RFC7231_FORMAT instead.',
        ],
    ];

    /** @var array<string,array{min_php_version:int,reason:string}> */
    private const DEPRECATED_GLOBAL_CONSTANTS = [
        // PHP 8.1 deprecations
        'FILTER_SANITIZE_STRING' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Use htmlspecialchars() or FILTER_SANITIZE_FULL_SPECIAL_CHARS instead.',
        ],
        'FILTER_FLAG_SCHEME_REQUIRED' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Has no effect; do not use.',
        ],
        'FILTER_FLAG_HOST_REQUIRED' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Has no effect; do not use.',
        ],
        'FILE_BINARY' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Has no effect; do not use.',
        ],
        'FILE_TEXT' => [
            'min_php_version' => 80100,
            'reason' => 'Deprecated in PHP 8.1. Has no effect; do not use.',
        ],
        // PHP 8.3 deprecations
        'MT_RAND_PHP' => [
            'min_php_version' => 80300,
            'reason' => 'Deprecated in PHP 8.3. Use Random\\Engine\\Mt19937 to reproduce legacy sequences.',
        ],
        // PHP 8.4 deprecations
        'CURLOPT_BINARYTRANSFER' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. CURLOPT_BINARYTRANSFER has no effect since PHP 5.1.3.',
        ],
        'E_STRICT' => [
            'min_php_version' => 80400,
            'reason' => 'Deprecated in PHP 8.4. Error level is unused.',
        ],
        // PHP 8.5 deprecations
        'DATE_RFC7231' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Use DATE_RFC7231_FORMAT instead.',
        ],
        'GSLC_SSL_NO_AUTH' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Oracle LDAP support is broken since PHP 8.0.',
        ],
        'GSLC_SSL_ONEWAY_AUTH' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Oracle LDAP support is broken since PHP 8.0.',
        ],
        'GSLC_SSL_TWOWAY_AUTH' => [
            'min_php_version' => 80500,
            'reason' => 'Deprecated in PHP 8.5. Oracle LDAP support is broken since PHP 8.0.',
        ],
    ];

    /**
     * Returns true when the configured target PHP version is at least the given version.
     */
    private static function isTargetingPhpVersionOrNewer(int $min_version): bool
    {
        return Config::get_closest_target_php_version_id() >= $min_version;
    }

    /**
     * Provides call-analyzer closures for version-specific deprecation checks.
     *
     * This handles special cases where specific argument patterns are deprecated,
     * rather than the entire function being deprecated:
     *
     * - mb_strimwidth(): Only deprecated when passing a negative $width (arg 3)
     * - ldap_connect(): Only deprecated when called with 2 arguments (host, port);
     *   single-argument calls with a URI are still valid
     *
     * @unused-param $code_base
     *
     * @return array<string,callable(CodeBase,Context,FunctionInterface,list<Node|int|float|string>,?Node):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        $closures = [];

        // PHP 8.3: mb_strimwidth() with negative $width
        // See: https://github.com/php/php-src/commit/af3c220abbff2415ccd79aeac1888edfc106ffa6
        // Only the negative width argument usage is deprecated, not the function itself.
        // Phan can detect this when the value is a literal or can be inferred via type analysis.
        if (self::isTargetingPhpVersionOrNewer(80300)) {
            $closures['\\mb_strimwidth'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    // mb_strimwidth(string $string, int $start, int $width, ...)
                    if (!isset($args[2])) {
                        return;
                    }
                    $width_node = $args[2];
                    $width_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $width_node);
                    $known_value = $width_type->asSingleScalarValueOrNull();
                    if (is_int($known_value) && $known_value < 0) {
                        $lineno = ($width_node instanceof Node && isset($width_node->lineno))
                            ? $width_node->lineno
                            : ($call_node->lineno ?? $context->getLineNumberStart());
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::DeprecatedFunctionArgument,
                            $lineno,
                            $function->getRepresentationForIssue(),
                            'Passing a negative $width is deprecated as of PHP 8.3'
                        );
                    }
                };

            // PHP 8.3: ldap_connect() with separate host and port arguments
            // See: https://github.com/php/php-src/commit/69a8b63ecf4fec1b35ef4da1ac9579321c45f97f
            // Only the two-argument signature ldap_connect($host, $port) is deprecated.
            // Single-argument calls with a URI like ldap_connect('ldap://host:port') are still valid.
            $closures['\\ldap_connect'] =
                /** @param list<Node|int|float|string> $args */
                static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $call_node): void {
                    if (count($args) === 2) {
                        $lineno = $call_node->lineno ?? $context->getLineNumberStart();
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::DeprecatedFunctionArgument,
                            $lineno,
                            $function->getRepresentationForIssue(),
                            'Calling with host and port parameters is deprecated as of PHP 8.3; pass a URI instead'
                        );
                    }
                };
        }

        return $closures;
    }

    /** @unused-param $code_base */
    public function handleLazyLoadInternalFunction(CodeBase $code_base, Func $function): void
    {
        if (!$function->isPHPInternal()) {
            return;
        }

        $name = strtolower(ltrim($function->getFQSEN()->getNamespacedName(), '\\'));
        $deprecation_info = self::DEPRECATED_FUNCTIONS[$name] ?? null;

        if ($deprecation_info === null) {
            return;
        }

        if (!self::isTargetingPhpVersionOrNewer($deprecation_info['min_php_version'])) {
            return;
        }

        if ($function->isDeprecated()) {
            return;
        }

        // Mark as deprecated but don't add a custom doc comment with the reason.
        // When the extension is loaded, PHP's own deprecation attributes provide the info.
        // When the extension is not loaded, we still want consistent test output.
        // Users can look up the deprecation reason in PHP documentation.
        $function->setIsDeprecated(true);
    }

    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return PhpDeprecationVisitor::class;
    }

    /**
     * Returns the map of deprecated class constants for the current target PHP version.
     *
     * @return array<string,string>
     */
    public static function getDeprecatedClassConstants(): array
    {
        $target_version = Config::get_closest_target_php_version_id();
        $result = [];

        foreach (self::DEPRECATED_CLASS_CONSTANTS as $fqsen => $info) {
            if ($target_version >= $info['min_php_version']) {
                $result[$fqsen] = $info['reason'];
            }
        }

        return $result;
    }

    /**
     * Returns the map of deprecated global constants for the current target PHP version.
     *
     * @return array<string,string>
     */
    public static function getDeprecatedGlobalConstants(): array
    {
        $target_version = Config::get_closest_target_php_version_id();
        $result = [];

        foreach (self::DEPRECATED_GLOBAL_CONSTANTS as $name => $info) {
            if ($target_version >= $info['min_php_version']) {
                $result[$name] = $info['reason'];
            }
        }

        return $result;
    }
}

/**
 * Visitor that emits class/global constant deprecation notices.
 */
final class PhpDeprecationVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @override
     */
    public function visitClassConst(Node $node): void
    {
        $deprecated = PhpDeprecationPlugin::getDeprecatedClassConstants();
        if (empty($deprecated)) {
            return;
        }

        try {
            $constants = (new ContextNode($this->code_base, $this->context, $node))->getClassConstList();
        } catch (\Throwable) {
            return;
        }

        foreach ($constants as $constant) {
            $this->maybeWarnDeprecatedClassConstant($constant, $node, $deprecated);
        }
    }

    /**
     * @override
     */
    public function visitConst(Node $node): void
    {
        $deprecated_map = PhpDeprecationPlugin::getDeprecatedGlobalConstants();
        if (empty($deprecated_map)) {
            return;
        }

        try {
            $constant = (new ContextNode($this->code_base, $this->context, $node))->getConst();
        } catch (\Throwable) {
            return;
        }

        $this->maybeWarnDeprecatedGlobalConstant($constant, $node, $deprecated_map);
    }

    /**
     * @param ClassConstant $constant
     * @param array<string,string> $deprecated_map
     */
    private function maybeWarnDeprecatedClassConstant(ClassConstant $constant, Node $node, array $deprecated_map): void
    {
        $fqsen = $constant->getFQSEN()->__toString();
        $reason = $deprecated_map[$fqsen] ?? null;
        if ($reason === null) {
            return;
        }
        $this->emitIssue(
            Issue::DeprecatedClassConstant,
            $node->lineno,
            $fqsen,
            $constant->getFileRef()->getFile(),
            $constant->getFileRef()->getLineNumberStart(),
            ' (Deprecated because: ' . $reason . ')'
        );
    }

    /**
     * @param array<string,string> $deprecated_map
     */
    private function maybeWarnDeprecatedGlobalConstant(GlobalConstant $constant, Node $node, array $deprecated_map): void
    {
        $name = ltrim($constant->getFQSEN()->__toString(), '\\');
        $reason = $deprecated_map[$name] ?? null;
        if ($reason === null) {
            return;
        }
        $this->emitIssue(
            Issue::DeprecatedGlobalConstant,
            $node->lineno,
            $constant->getFQSEN()->__toString(),
            ' (Deprecated because: ' . $reason . ')'
        );
    }
}
