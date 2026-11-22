<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\UnionType;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\PluginV3;
use Phan\PluginV3\BeforeAnalyzePhaseCapability;

require_once dirname(__DIR__) . '/check_signature_map_against_reflection.php';

/**
 * Compares the internal functions and methods as Phan sees them during analysis
 * (signature map, bundled stubs and Reflection combined) with Reflection.
 *
 * Usage: ./phan -n --target-php-version X.Y --plugin internal/lib/SignatureReflectionCheckPlugin.php any_file.php
 * Writes the differences to stdout (or to the file named by $PHAN_SIGNATURE_CHECK_OUT as JSON).
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny
 */
final class SignatureReflectionCheckPlugin extends PluginV3 implements BeforeAnalyzePhaseCapability
{
    public function beforeAnalyzePhase(CodeBase $code_base): void
    {
        $report = ['missing' => [], 'differences' => []];
        $map = UnionType::internalFunctionSignatureMap(Config::get_closest_target_php_version_id());
        foreach (SignatureMapReflectionChecker::collectReflection() as $key => $reflection) {
            if (isset($map[$key . "'1"]) || isset(SignatureMapReflectionChecker::DELIBERATE_DIFFERENCES[$key])) {
                // Phan models this function with several alternative signatures (e.g. implode()), checked separately.
                continue;
            }
            $function = self::findFunction($code_base, $reflection);
            if (!$function) {
                $report['missing'][] = $key;
                continue;
            }
            $phan_params = [];
            foreach ($function->getParameterList() as $parameter) {
                $phan_params[] = [
                    'name' => $parameter->getName(),
                    'optional' => $parameter->isOptional(),
                    'by_ref' => $parameter->isPassByReference(),
                    'variadic' => $parameter->isVariadic(),
                ];
            }
            $reflection_params = array_map([SignatureMapReflectionChecker::class, 'describeReflectionParameter'], $reflection->getParameters());
            $kinds = SignatureMapReflectionChecker::compare($phan_params, $reflection_params);
            if ($kinds) {
                $describe = /** @param list<array{name:string,optional:bool,by_ref:bool,variadic:bool}> $params */ static fn(array $params): string => implode(', ', array_map(/** @param array{name:string,optional:bool,by_ref:bool,variadic:bool} $p */ static fn(array $p): string => ($p['by_ref'] ? '&' : '') . ($p['variadic'] ? '...' : '') . $p['name'] . ($p['optional'] && !$p['variadic'] ? '=' : ''), $params));
                $report['differences'][$key] = [
                    'kinds' => $kinds,
                    'phan' => $describe($phan_params),
                    'reflection' => $describe($reflection_params),
                    'source' => $function->getFileRef()->getFile() ?: 'map/reflection',
                ];
            }
        }
        $out = getenv('PHAN_SIGNATURE_CHECK_OUT');
        if (is_string($out) && $out !== '') {
            file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            return;
        }
        printf("missing: %d, differences: %d\n", count($report['missing']), count($report['differences']));
        foreach ($report['missing'] as $key) {
            echo "missing $key\n";
        }
        foreach ($report['differences'] as $key => $d) {
            printf("%s [%s] (%s)\n  phan:       %s\n  reflection: %s\n", $key, implode(',', $d['kinds']), $d['source'], $d['phan'], $d['reflection']);
        }
    }

    private static function findFunction(CodeBase $code_base, ReflectionFunctionAbstract $reflection): ?FunctionInterface
    {
        try {
            if ($reflection instanceof ReflectionMethod) {
                $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\' . $reflection->getDeclaringClass()->getName());
                if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                    return null;
                }
                $class = $code_base->getClassByFQSEN($class_fqsen);
                if (!$class->hasMethodWithName($code_base, $reflection->getName(), true)) {
                    return null;
                }
                return $class->getMethodByName($code_base, $reflection->getName());
            }
            $fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString('\\' . $reflection->getName());
            if (!$code_base->hasFunctionWithFQSEN($fqsen)) {
                return null;
            }
            return $code_base->getFunctionByFQSEN($fqsen);
        } catch (Exception $e) {
            fwrite(STDERR, "Error looking up {$reflection->getName()}: {$e->getMessage()}\n");
            return null;
        }
    }
}

return new SignatureReflectionCheckPlugin();
