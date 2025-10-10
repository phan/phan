#!/usr/bin/env bash
set -u
EXPECTED_PATH=expected/all_output.expected
ACTUAL_PATH=all_output.actual
if [ ! -d expected -o ! -d expected_src ]; then
	echo "Error: must run this script from tests/fixer_test folder" 1>&2
	exit 1
fi
echo "Generating test cases"

PHP_VERSION_ID=$(php -r "echo PHP_VERSION_ID;")
echo "PHP_VERSION_ID=$PHP_VERSION_ID";

for path in $(echo expected/*.php.expected | LC_ALL=C sort); do
    original_path="$path"
    if [[ "$PHP_VERSION_ID" -ge 80200 ]]; then
        alternate_path=${original_path/.expected/.expected82}
        if [ -f "$alternate_path" ]; then
            path="$alternate_path"
        fi
    fi
    if [[ "$PHP_VERSION_ID" -ge 80300 ]]; then
        alternate_path=${original_path/.expected/.expected83}
        if [ -f "$alternate_path" ]; then
            path="$alternate_path"
        fi
    fi
    if [[ "$PHP_VERSION_ID" -ge 80400 ]]; then
        alternate_path=${original_path/.expected/.expected84}
        if [ -f "$alternate_path" ]; then
            path="$alternate_path"
        fi
    fi
    if [[ "$PHP_VERSION_ID" -ge 80500 ]]; then
        alternate_path=${original_path/.expected/.expected85}
        if [ -f "$alternate_path" ]; then
            path="$alternate_path"
        fi
    fi
    cat $path;
done > $EXPECTED_PATH

if [[ $? != 0 ]]; then
	echo "Failed to concatenate test cases" 1>&2
	exit 1
fi
echo "Running phan in '$PWD' ..."
rm -f $ACTUAL_PATH || exit 1

# Create a copy so we can verify the copy is changed in place
rm -rf src_copy
cp -r src src_copy

# We use the polyfill parser because it behaves consistently in all php versions.
../../phan --redundant-condition-detection --automatic-fix --dead-code-detection --force-polyfill-parser --memory-limit 1G | tee $ACTUAL_PATH

# diff returns a non-zero exit code if files differ or are missing
# This outputs the difference between actual and expected output.
echo
echo "Comparing the output:"

sed -i -e 's/src_copy\\/src_copy\//g' $ACTUAL_PATH

if type colordiff >/dev/null; then
    DIFF=colordiff
else
    DIFF=diff
fi

$DIFF $EXPECTED_PATH $ACTUAL_PATH
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are identical"
	rm $ACTUAL_PATH
else
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are different"
	exit $EXIT_CODE
fi
FOUND_EXPECTED=0
UNEXPECTED_FIX=0
for expected_src_file in expected_src/*.php; do
    if [[ $expected_src_file =~ .php[0-9]+.php ]]; then
        # Alternate file, checked when processing the canonical version.
        continue
    fi
    original_path="$expected_src_file"
	FOUND_EXPECTED=1
    if [[ "$PHP_VERSION_ID" -ge 80200 ]]; then
        alternate_expected_path=${original_path/.php/.php82.php}
        if [ -f "$alternate_expected_path" ]; then
            expected_src_file="$alternate_expected_path"
        fi
    fi
    if [[ "$PHP_VERSION_ID" -ge 80300 ]]; then
        alternate_expected_path=${original_path/.php/.php83.php}
        if [ -f "$alternate_expected_path" ]; then
            expected_src_file="$alternate_expected_path"
        fi
    fi
    if [[ "$PHP_VERSION_ID" -ge 80400 ]]; then
        alternate_expected_path=${original_path/.php/.php84.php}
        if [ -f "$alternate_expected_path" ]; then
            expected_src_file="$alternate_expected_path"
        fi
    fi
    if [[ "$PHP_VERSION_ID" -ge 80500 ]]; then
        alternate_expected_path=${original_path/.php/.php85.php}
        if [ -f "$alternate_expected_path" ]; then
            expected_src_file="$alternate_expected_path"
        fi
    fi
	actual_src_file=${original_path/expected_src/src_copy}
	# diff returns a non-zero exit code if files differ or are missing
	if ! $DIFF -C 3 "$actual_src_file" "$expected_src_file"; then
		echo "phan --automatic-fix did not generate the expected fix for $actual_src_file and $expected_src_file"
		UNEXPECTED_FIX=1
	fi
done
if [ "$FOUND_EXPECTED" = 0 ]; then
	# should not happen
	echo "No php files found in expected_src"
	exit 1
fi
if [ "$UNEXPECTED_FIX" = 0 ]; then
	echo "Files in src_copy are identical to those in expected_src"
else
	echo "Files in src_copy are different from those in expected_src"
	EXIT_CODE=1
fi
exit $EXIT_CODE
