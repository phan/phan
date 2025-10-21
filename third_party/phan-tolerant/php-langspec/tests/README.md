# The PHP Specification Test Suite

If you pass this suite, you aren't necessarily spec compliant, but it is a helpful bellwether.

## Usage

To run using the PHP5 test runner, you'll need to convert to PHP5's .phpt format first.  The following demonstrates writing the tests out to /tmp/phpt:

    ./make_phpt . /tmp/phpt
    TEST_PHP_EXECUTABLE=~/php-src/sapi/cli/php ~/php-src/run-tests.php /tmp/phpt

To use the HHVM test runner:

    ~/hhvm/hphp/test/run .
