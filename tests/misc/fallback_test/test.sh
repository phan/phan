#!/usr/bin/env bash
EXPECTED_PATH=expected/all_output.expected
ACTUAL_PATH=all_output.actual
if [ ! -d expected  ]; then
	echo "Error: must run this script from tests/misc/fallback_test folder" 1>&2
	exit 1
fi
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION;")
echo "PHP_VERSION=$PHP_VERSION\n";

for path in $(echo expected/*.php.expected | LC_ALL=C sort); do
    if [[ "$PHP_VERSION" -ge 8 ]]; then
        alternate_path=${path/.expected/.expected80}
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
rm $ACTUAL_PATH -f || exit 1
../../../phan --use-fallback-parser | tee $ACTUAL_PATH
# normalize output for https://github.com/phan/phan/issues/1130
# This has a varying order for src/020_issue.php
sed -i "s/anonymous_class_\w\+/anonymous_class_%s/g" $ACTUAL_PATH $EXPECTED_PATH
# Normalize output that is seen only in certain minor version ranges
sed -i \
    -e "s/ expecting ';' or ','/ expecting ',' or ';'/" \
    -e 's/of type \\Countable|\(\\Iterator|\\RecursiveIterator|\)\?\\SimpleXMLElement/of type \\SimpleXMLElement/' \
    -e "s@047_invalid_define.php:3 PhanSyntaxError syntax error, unexpected 'a' (T_STRING), expecting ',' or ')'@047_invalid_define.php:3 PhanSyntaxError syntax error, unexpected 'a' (T_STRING), expecting ')'@" \
    -e "s@052_invalid_assign_ref.php:3 PhanSyntaxError syntax error, unexpected '=', expecting ',' or ')'@052_invalid_assign_ref.php:3 PhanSyntaxError syntax error, unexpected '=', expecting ')'@" \
    -e "s@069_invalid_coalesce_assign.php:2 PhanSyntaxError syntax error, unexpected '??=' (T_COALESCE_EQUAL) (at column 3)@069_invalid_coalesce_assign.php:2 PhanSyntaxError syntax error, unexpected '=' (at column 5)@" \
    -e "/069_invalid_coalesce_assign.php:2 PhanNoopBinaryOperator/d" \
    -e "/069_invalid_coalesce_assign.php:2 PhanInvalidNode Invalid left hand side for ??=/d" \
    -e "s@src/076_pipe.php:3 PhanSyntaxError syntax error, unexpected '|', expecting function (T_FUNCTION) or const (T_CONST)@src/076_pipe.php:3 PhanSyntaxError syntax error, unexpected '|', expecting variable (T_VARIABLE)@" \
    $ACTUAL_PATH

# diff returns a non-zero exit code if files differ or are missing
echo
echo "Comparing the output:"
if type colordiff 2>/dev/null >/dev/null; then
    DIFF=colordiff
else
    DIFF=diff
fi
$DIFF $EXPECTED_PATH $ACTUAL_PATH
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are identical"
    rm $ACTUAL_PATH
fi
exit $EXIT_CODE
