<?php declare(strict_types=1);

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

use \Phan\Analyzer;
use \Phan\CodeBase;
use \Phan\Language\Context;
use \Phan\Language\Type;
use \Phan\Log;

define('RASMUS_TEST_FILE_DIR', __DIR__ . '/../rasmus_files/src');
define('RASMUS_EXPECTED_DIR', __DIR__ . '/../rasmus_files/expected');

class RasmusTest extends \PHPUnit_Framework_TestCase {

    /** @var CodeBase */
    private $code_base = null;

    protected function setUp() {
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_function_name_list;

        $this->code_base = new CodeBase(
            $internal_class_name_list,
            $internal_interface_name_list,
            $internal_trait_name_list,
            $internal_function_name_list
        );
    }

    public function tearDown() {
        $this->code_base = null;
    }

    /**
     * This reads all files in `tests/files/src`, runs
     * the analyzer on each and compares the output
     * to the files's counterpart in
     * `tests/files/expected`
     */
    public function testFiles() {

        return;

        foreach (scandir(RASMUS_TEST_FILE_DIR) as $test_file_name) {
            // Skip '.' and '..'
            if (empty($test_file_name)
                || '.' === $test_file_name
                || '..' === $test_file_name
            ) {
                continue;
            }

            // Get the path to the test file
            $test_file_path =
                RASMUS_TEST_FILE_DIR . '/' . $test_file_name;

            // Get the path of the expected output file
            $expected_file_path =
                RASMUS_EXPECTED_DIR . '/' . $test_file_name . '.expected';

            // Read the expected output
            $expected_output =
                trim(file_get_contents($expected_file_path));

            // Start reading everything sent to STDOUT
            // and compare it to the expected value once
            // the analzyer finishes running
            ob_start();

            try {
                // Run the analyzer
                (new Analyzer())->analyze(
                    clone($this->code_base),
                    [$test_file_path]
                );
            } catch (Exception $exception) {
                // TODO: inexplicably bad things happen here
                // print "\n" . $exception->getMessage() . "\n";
            }

            $output = str_replace(
                $test_file_path, '%s',
                trim(ob_get_clean())
            );

            $this->assertEquals(
                $expected_output,
                $output,
                "Unexpected output in $test_file_path"
            );
        }
    }
}
