#!/bin/bash
# Test script for SPL template support
# This runs Phan with the main config (which loads the SPL stub)
# and verifies the output matches expected

cd "$(dirname "$0")/../.." || exit 1

echo "Testing SPL template stub support..."

# Run Phan on the test file
./phan --no-progress-bar tests/misc/spl_templates_test.php 2>&1 | \
    sed 's/internal:[0-9]\+/internal:%d/g' > /tmp/spl_test_actual.txt

# Compare with expected
if diff -u tests/misc/spl_templates_test.expected /tmp/spl_test_actual.txt; then
    echo "✓ SPL template test PASSED"
    exit 0
else
    echo "✗ SPL template test FAILED - output doesn't match expected"
    exit 1
fi
