<?php declare(strict_types=1);

namespace Phan\Tests\Language\Internal;

use Phan\Language\UnionType;
use Phan\Tests\BaseTest;

/**
 * This is a sanity check that Phan's function signature map has the correct structure
 * and can be parsed into a function signature.
 */
final class FunctionSignatureMapTest extends BaseTest
{
    const FUNCTION_KEY_REGEX = '/^[a-z_][a-z0-9_]*(\\\\[a-z_][a-z0-9_]*)*(::[a-z_][a-z0-9_]*)?(\'[1-9][0-9]*)?$/i';
    const PARAM_KEY_REGEX = '/^\&?(\.\.\.)?[a-z_][a-z0-9_]*=?$/i';

    // Matches a union type of 0 or more parts.
    const ONLY_UNION_TYPE_REGEX = '/^(' . UnionType::union_type_regex . ')?$/';

    /**
     * @dataProvider phpVersionIdProvider
     */
    public function testFunctionSignatureMap(int $php_version_id)
    {
        $map = UnionType::internalFunctionSignatureMap($php_version_id);
        $failures = [];
        foreach ($map as $function_name => $signature) {
            if (!is_string($function_name)) {
                $failures[] = "Expected array for entry $function_name with values " . var_export($signature, true);
            } elseif (!preg_match(self::FUNCTION_KEY_REGEX, $function_name)) {
                $failures[] = "Expected $function_name to match the regular expression " . self::FUNCTION_KEY_REGEX;
            }
            if (!is_array($signature)) {
                $failures[] = "Expected array for entry $function_name";
                continue;
            }
            $return_type_signature = $signature[0] ?? null;
            if (!is_string($return_type_signature)) {
                $failures[] = "Missing or invalid entry for array key 0 of signature for $function_name";
            } elseif (!preg_match(self::ONLY_UNION_TYPE_REGEX, $return_type_signature)) {
                $failures[] = "Invalid union type string for return type of $function_name : value = " . var_export($return_type_signature, true);
            }
            unset($signature[0]);
            foreach ($signature as $param_name => $type_string) {
                if (!is_string($param_name) || !preg_match(self::PARAM_KEY_REGEX, $param_name)) {
                    $failures[] = "Invalid param name $param_name of $function_name : does not match regex " . self::PARAM_KEY_REGEX;
                }
                if (!is_string($type_string) || !preg_match(self::ONLY_UNION_TYPE_REGEX, $type_string)) {
                    $failures[] = "Invalid union type string for param $param_name of $function_name : value = " . var_export($type_string, true);
                }
            }
        }
        $this->assertSame('', implode("\n", $failures), "Saw one or more issues for the signature for PHP_VERSION_ID " . $php_version_id);
    }

    public function phpVersionIdProvider() : array
    {
        return [
            [70000],  // PHP 7.0
            [70100],  // PHP 7.1
            [70200],  // PHP 7.2
        ];
    }
}
