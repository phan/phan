<?php declare(strict_types=1);

namespace Phan\Tests\Language\Internal;

use Phan\Language\UnionType;
use Phan\Tests\BaseTest;

/**
 * This is a sanity check that Phan's property signature map has the correct structure
 * and can be parsed into a property signature.
 */
final class PropertyMapTest extends BaseTest
{
    const CLASS_NAME_LOWER_REGEX = '/^([a-z_][a-z0-9_]*(\\\\[a-z_][a-z0-9_]*)*|\*)$/';

    const PROPERTY_KEY_REGEX = '/^([a-z_][a-z0-9_]*|\*)$/i';

    // Matches a union type of 0 or more parts.
    const ONLY_UNION_TYPE_REGEX = '/^(' . UnionType::union_type_regex . ')?$/';

    public function testPropertySignatureMap()
    {
        $map = require(realpath(__DIR__) . '/../../../../src/Phan/Language/Internal/PropertyMap.php');
        $failures = [];

        $prev_class_name = '';
        foreach ($map as $class_name => $signature) {
            $class_name = (string)$class_name;

            if (strcmp($class_name, $prev_class_name) < 0) {
                $failures[] = "Expected class name '$class_name' to be before '$prev_class_name'";
            }
            $prev_class_name = $class_name;

            if (!preg_match(self::CLASS_NAME_LOWER_REGEX, $class_name)) {
                $failures[] = "Expected class name '$class_name' to match the regular expression " . self::CLASS_NAME_LOWER_REGEX;
            }
            if (!is_array($signature)) {
                $failures[] = "Expected array for property signatures of  $class_name";
                continue;
            }
            $this->checkPropertySignaturesOfClassName($class_name, $signature, $failures);
        }
        $this->assertSame('', implode("\n", $failures), "Saw one or more issues for the property map signature");
    }

    /**
     * @param string $class_name
     * @param array<string,string> $signature
     * @param array<int,string> &$failures
     */
    private function checkPropertySignaturesOfClassName(string $class_name, array $signature, array &$failures)
    {
        $prev_prop_name = '';
        foreach ($signature as $prop_name => $value) {
            if (!is_string($prop_name) || !preg_match(self::PROPERTY_KEY_REGEX, $prop_name)) {
                $failures[] = "Expected property name $prop_name of class $class_name to match the regular expression " . self::PROPERTY_KEY_REGEX;
                continue;
            }
            if (strcasecmp($prop_name, $prev_prop_name) < 0) {
                $failures[] = "Expected property name '$prop_name' to be before '$prev_prop_name' in '$class_name' (sorted case insensitively)";
            }
            $prev_prop_name = $prop_name;
            if (!is_string($value)) {
                $failures[] = "Expected property value for $prop_name of class $class_name to be a string, but got " . json_encode($value);
                continue;
            }
            if (!preg_match(self::ONLY_UNION_TYPE_REGEX, $value)) {
                $failures[] = "Expected property value '$value' for $prop_name of class $class_name to be a valid union type, but got " . json_encode($value);
            }
        }
    }
}
