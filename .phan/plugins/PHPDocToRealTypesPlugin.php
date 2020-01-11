<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Library\FileCacheEntry;
use Phan\Phan;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\AutomaticFixCapability;
use Phan\PluginV3\BeforeAnalyzePhaseCapability;
use PHPDocToRealTypesPlugin\Fixers;

/**
 * This plugin suggests real types that can be used instead of phpdoc types.
 *
 * It does not check if the change is safe to make.
 *
 * TODO: Always use the same type representation as phpdoc if possible in this plugin
 */
class PHPDocToRealTypesPlugin extends PluginV3 implements
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    AutomaticFixCapability,
    BeforeAnalyzePhaseCapability
{
    private const CanUsePHP71Void = 'PhanPluginCanUsePHP71Void';
    private const CanUseReturnType = 'PhanPluginCanUseReturnType';
    private const CanUseNullableReturnType = 'PhanPluginCanUseNullableReturnType';

    private const CanUseParamType = 'PhanPluginCanUseParamType';
    private const CanUseNullableParamType = 'PhanPluginCanUseNullableParamType';

    /** @var array<string,Method> */
    private $deferred_analysis_methods = [];

    /**
     * @return array<string,Closure(CodeBase,FileCacheEntry,IssueInstance):(?FileEditSet)>
     */
    public function getAutomaticFixers(): array
    {
        require_once __DIR__ .  '/PHPDocToRealTypesPlugin/Fixers.php';
        $param_closure = Closure::fromCallable([Fixers::class, 'fixParamType']);
        $return_closure = Closure::fromCallable([Fixers::class, 'fixReturnType']);
        return [
            self::CanUsePHP71Void => $return_closure,
            self::CanUseReturnType => $return_closure,
            self::CanUseNullableReturnType => $return_closure,
            self::CanUseNullableParamType => $param_closure,
            self::CanUseParamType => $param_closure,
        ];
    }

    public function analyzeFunction(CodeBase $code_base, Func $function): void
    {
        self::analyzeFunctionLike($code_base, $function);
    }

    public function analyzeMethod(CodeBase $unused_code_base, Method $method): void
    {
        if ($method->isFromPHPDoc() || $method->isMagic() || $method->isPHPInternal()) {
            return;
        }
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            return;
        }
        $this->deferred_analysis_methods[$method->getFQSEN()->__toString()] = $method;
    }

    public function beforeAnalyzePhase(CodeBase $code_base): void
    {
        $ignore_overrides = (bool)getenv('PHPDOC_TO_REAL_TYPES_IGNORE_INHERITANCE');
        foreach ($this->deferred_analysis_methods as $method) {
            if ($method->isOverride() || $method->isOverriddenByAnother()) {
                if (!$ignore_overrides) {
                    continue;
                }
            }
            self::analyzeFunctionLike($code_base, $method);
        }
    }

    private static function analyzeFunctionLike(CodeBase $code_base, FunctionInterface $method): void
    {
        if (Phan::isExcludedAnalysisFile($method->getContext()->getFile())) {
            // This has no side effects, so we can skip files that don't need to be analyzed
            return;
        }
        if ($method->getRealReturnType()->isEmpty()) {
            self::analyzeReturnTypeOfFunctionLike($code_base, $method);
        }
        $phpdoc_param_list = $method->getParameterList();
        foreach ($method->getRealParameterList() as $i => $parameter) {
            if (!$parameter->getNonVariadicUnionType()->isEmpty()) {
                continue;
            }
            $phpdoc_param = $phpdoc_param_list[$i];
            if (!$phpdoc_param) {
                continue;
            }
            $union_type = $phpdoc_param->getNonVariadicUnionType()->asNormalizedTypes();
            if ($union_type->typeCount() !== 1) {
                continue;
            }
            $type = $union_type->getTypeSet()[0];
            if (!$type->canUseInRealSignature()) {
                continue;
            }
            self::emitIssue(
                $code_base,
                $method->getContext(),
                $type->isNullable() ? self::CanUseNullableParamType : self::CanUseParamType,
                'Can use {TYPE} as the type of parameter ${PARAMETER} of {METHOD}',
                [$type->asSignatureType(), $parameter->getName(), $method->getName()]
            );
        }
    }

    private static function analyzeReturnTypeOfFunctionLike(CodeBase $code_base, FunctionInterface $method): void
    {
        $union_type = $method->getUnionType();
        if ($union_type->isVoidType()) {
            self::emitIssue(
                $code_base,
                $method->getContext(),
                self::CanUsePHP71Void,
                'Can use php 7.1\'s {TYPE} as a return type of {METHOD}',
                ['void', $method->getName()]
            );
            return;
        }
        $union_type = $union_type->asNormalizedTypes();
        if ($union_type->typeCount() !== 1) {
            return;
        }
        $type = $union_type->getTypeSet()[0];
        if (!$type->canUseInRealSignature()) {
            return;
        }
        self::emitIssue(
            $code_base,
            $method->getContext(),
            $type->isNullable() ? self::CanUseNullableReturnType : self::CanUseReturnType,
            'Can use {TYPE} as a return type of {METHOD}',
            [$type->asSignatureType(), $method->getName()]
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PHPDocToRealTypesPlugin();
