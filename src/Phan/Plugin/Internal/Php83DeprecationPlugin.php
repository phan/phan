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
 * Emits PHP 8.3-specific deprecation diagnostics.
 */
final class Php83DeprecationPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability,
    HandleLazyLoadInternalFunctionCapability,
    PostAnalyzeNodeCapability
{
    /** @var array<string,string> */
    private const PHP83_DEPRECATED_FUNCTIONS = [
        'mt_rand' => 'Deprecated in PHP 8.3. Use Random\\Randomizer::getInt() or random_int().',
        'mt_srand' => 'Deprecated in PHP 8.3. Use Random\\Randomizer with Random\\Engine\\Mt19937 instead.',
        'mt_getrandmax' => 'Deprecated in PHP 8.3. Use Random\\Randomizer or Random\\Engine\\Mt19937.',
        'rand' => 'Deprecated in PHP 8.3. Use random_int() or Random\\Randomizer::getInt().',
        'srand' => 'Deprecated in PHP 8.3. Use Random\\Randomizer with a dedicated engine instead.',
        'getrandmax' => 'Deprecated in PHP 8.3. Use Random\\Randomizer::getInt() as needed.',
    ];

    /** @var array<string,string> */
    private const PHP83_DEPRECATED_CLASS_CONSTANTS = [
        '\\NumberFormatter::TYPE_CURRENCY' => 'Deprecated in PHP 8.3. Use NumberFormatter::formatCurrency()/parseCurrency().',
    ];

    /** @var array<string,string> */
    private const PHP83_DEPRECATED_GLOBAL_CONSTANTS = [
        'MT_RAND_PHP' => 'Deprecated in PHP 8.3. Use Random\\Engine\\Mt19937 to reproduce legacy sequences.',
        'CRYPT_STD_DES' => 'Deprecated in PHP 8.3. These crypt() capability constants are always available.',
        'CRYPT_BLOWFISH' => 'Deprecated in PHP 8.3. These crypt() capability constants are always available.',
        'CRYPT_EXT_DES' => 'Deprecated in PHP 8.3. These crypt() capability constants are always available.',
        'CRYPT_MD5' => 'Deprecated in PHP 8.3. These crypt() capability constants are always available.',
        'CRYPT_SHA256' => 'Deprecated in PHP 8.3. These crypt() capability constants are always available.',
        'CRYPT_SHA512' => 'Deprecated in PHP 8.3. These crypt() capability constants are always available.',
    ];

    /**
     * Returns true when the configured target PHP version is 8.3 or later.
     */
    public static function isTargetingPhp83OrNewer(): bool
    {
        static $result;
        return $result ??= Config::get_closest_target_php_version_id() >= 80300;
    }

    /**
     * @return array<string,callable(CodeBase,Context,FunctionInterface,array,?Node):void>
     */
    /**
     * Provides call-analyzer closures for the PHP 8.3 deprecation checks.
     *
     * @unused-param $code_base
     *
     * @return array<string,callable(CodeBase,Context,FunctionInterface,list<Node|int|float|string>,?Node):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        if (!self::isTargetingPhp83OrNewer()) {
            return [];
        }

        return [
            '\\mb_strimwidth' =>
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
            },
            '\\ldap_connect' =>
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
            },
        ];
    }

    /** @unused-param $code_base */
    public function handleLazyLoadInternalFunction(CodeBase $code_base, Func $function): void
    {
        if (!self::isTargetingPhp83OrNewer()) {
            return;
        }
        if (!$function->isPHPInternal()) {
            return;
        }
        $name = strtolower(ltrim($function->getFQSEN()->getNamespacedName(), '\\'));
        $reason = self::PHP83_DEPRECATED_FUNCTIONS[$name] ?? null;
        if ($reason === null) {
            return;
        }
        if ($function->isDeprecated()) {
            return;
        }
        $function->setIsDeprecated(true);
        $function->setDocComment('/** @deprecated ' . $reason . ' */');
    }

    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return Php83DeprecationVisitor::class;
    }

    /**
     * Returns the map of deprecated class constants we warn about in PHP 8.3.
     *
     * @return array<string,string>
     */
    public static function getDeferredConstants(): array
    {
        return self::PHP83_DEPRECATED_CLASS_CONSTANTS;
    }

    /**
     * Returns the map of deprecated global constants we warn about in PHP 8.3.
     *
     * @return array<string,string>
     */
    public static function getDeprecatedGlobalConstants(): array
    {
        return self::PHP83_DEPRECATED_GLOBAL_CONSTANTS;
    }
}

/**
 * Visitor that emits class/global constant deprecation notices in PHP 8.3 mode.
 */
final class Php83DeprecationVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @override
     */
    public function visitClassConst(Node $node): void
    {
        if (!Php83DeprecationPlugin::isTargetingPhp83OrNewer()) {
            return;
        }
        try {
            $constants = (new ContextNode($this->code_base, $this->context, $node))->getClassConstList();
        } catch (\Throwable) {
            return;
        }
        $deprecated = Php83DeprecationPlugin::getDeferredConstants();
        foreach ($constants as $constant) {
            $this->maybeWarnDeprecatedClassConstant($constant, $node, $deprecated);
        }
    }

    /**
     * @override
     */
    public function visitConst(Node $node): void
    {
        if (!Php83DeprecationPlugin::isTargetingPhp83OrNewer()) {
            return;
        }
        try {
            $constant = (new ContextNode($this->code_base, $this->context, $node))->getConst();
        } catch (\Throwable) {
            return;
        }
        $this->maybeWarnDeprecatedGlobalConstant($constant, $node);
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

    private function maybeWarnDeprecatedGlobalConstant(GlobalConstant $constant, Node $node): void
    {
        $name = ltrim($constant->getFQSEN()->__toString(), '\\');
        $deprecated_map = Php83DeprecationPlugin::getDeprecatedGlobalConstants();
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
