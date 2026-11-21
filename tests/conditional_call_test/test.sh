#!/usr/bin/env bash
# The fixture suite of ConditionalCallCapability (closures that change the context when a call is used as a condition).
#
# The test plugins in .phan/plugins make the effect of their closures visible in the output of '@phan-debug-var' statements.
# Runs Phan on src/ (with the classes of external/, which is parsed but excluded from analysis, as roots) without lazy entries
# (PHAN_LAZY_INHERITED_METHODS=0), with them (PHAN_LAZY_INHERITED_METHODS=1) and in verify mode (PHAN_LAZY_INHERIT_VERIFY=1),
# and with --processes 2 if pcntl is available. Each run must print the expected output, verify mode must find no violation,
# the extended state digests of the runs without lazy entries and in verify mode must be identical, and the run with
# lazy entries must have given a closure to a lazily created copy of a method.
EXPECTED_PATH=expected/all_output.expected
if [ ! -d expected  ]; then
	echo "Error: must run this script from tests/conditional_call_test folder" 1>&2
	exit 1
fi
for path in $(echo expected/*.php.expected | LC_ALL=C sort); do cat $path; done > $EXPECTED_PATH || exit 1

PHP_BINARY=${PHP_BINARY:-php}
FAILURES=0
TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

# run_mode <label> <mode> <extra phan args...>
# mode: off (no lazy entries), on, verify
function run_mode {
	local label="$1" mode="$2"
	shift 2
	local env_args=(-u PHAN_LAZY_INHERITED_METHODS -u PHAN_LAZY_INHERIT_VERIFY -u PHAN_DISABLE_LAZY_INHERIT -u PHAN_DUMP_METHOD_STATE_DIGEST -u PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED)
	case "$mode" in
		off) env_args+=(PHAN_LAZY_INHERITED_METHODS=0 PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED=1);;
		on) env_args+=(PHAN_LAZY_INHERITED_METHODS=1);;
		verify) env_args+=(PHAN_LAZY_INHERIT_VERIFY=1);;
	esac
	if [[ "$mode" != on ]]; then
		env_args+=(PHAN_DUMP_METHOD_STATE_DIGEST="$TMP_DIR/$label.digest")
	fi
	local actual="$TMP_DIR/$label.actual"
	echo "Running phan in '$PWD' ($label: $mode) ..."
	env "${env_args[@]}" ../../phan --no-progress-bar --memory-limit 1G "$@" > "$actual" 2> "$TMP_DIR/$label.stderr"
	local exit_code=$?
	sed -i -e 's/src\\/src\//g' "$actual"
	if [[ "$exit_code" != 1 ]]; then
		# Phan exits with 1 if it reports issues
		echo "Unexpected exit code $exit_code for $label (expected 1: there are issues)" 1>&2
		cat "$TMP_DIR/$label.stderr" 1>&2
		FAILURES=$((FAILURES + 1))
	fi
	if ! diff "$EXPECTED_PATH" "$actual"; then
		echo "The output of $label differs from $EXPECTED_PATH" 1>&2
		FAILURES=$((FAILURES + 1))
	fi
	if [[ "$mode" == verify ]]; then
		if grep 'PHAN_LAZY_INHERIT_VERIFY: .* (declared in ' "$TMP_DIR/$label.stderr" 1>&2 || \
			! grep -q '^PHAN_LAZY_INHERIT_VERIFY: [1-9][0-9]* lazy entries created, .*: 0 violation(s)$' "$TMP_DIR/$label.stderr"; then
			echo "PHAN_LAZY_INHERIT_VERIFY=1 found violations (or created no lazy entry) for $label:" 1>&2
			cat "$TMP_DIR/$label.stderr" 1>&2
			FAILURES=$((FAILURES + 1))
		fi
	fi
}

# compare_digests <label without lazy entries> <label in verify mode>
function compare_digests {
	if ! cmp "$TMP_DIR/$1.digest" "$TMP_DIR/$2.digest"; then
		echo "The extended state digests of $1 and $2 differ:" 1>&2
		diff "$TMP_DIR/$1.digest" "$TMP_DIR/$2.digest" | head -20 1>&2
		FAILURES=$((FAILURES + 1))
	fi
}

for mode in off on verify; do
	run_mode "$mode" "$mode"
done
compare_digests off verify

# The closure for ExternalBase::externalVerify() must have been recorded for, and replayed onto, the lazily created copy
# LocalChild::externalVerify() (counters of LazyInheritedMethods, see --phase-timings-json)
echo "Checking the counters of lazy_inherited_methods ..."
env -u PHAN_LAZY_INHERIT_VERIFY -u PHAN_DISABLE_LAZY_INHERIT -u PHAN_DUMP_METHOD_STATE_DIGEST PHAN_LAZY_INHERITED_METHODS=1 ../../phan --no-progress-bar --memory-limit 1G --phase-timings-json "$TMP_DIR/timings.json" > "$TMP_DIR/timings.actual" 2>/dev/null
"$PHP_BINARY" -r '
$report = json_decode((string)file_get_contents($argv[1]), true);
$notes = [];
array_walk_recursive($report, function ($value, $key) use (&$notes) {
    if (is_string($key) && str_starts_with($key, "lazy_")) {
        $notes[$key] = ($notes[$key] ?? 0) + (is_numeric($value) ? $value : 0);
    }
});
$missing = [];
foreach (["lazy_links", "lazy_ops_recorded", "lazy_ops_replayed"] as $name) {
    if (($notes[$name] ?? 0) <= 0) {
        $missing[] = $name;
    }
}
if ($missing) {
    fwrite(STDERR, "These counters of lazy_inherited_methods are 0: " . implode(", ", $missing) . "\n");
    exit(1);
}
' "$TMP_DIR/timings.json" || FAILURES=$((FAILURES + 1))

# With analysis workers (the closures and the registered names are set up before the workers are forked)
if "$PHP_BINARY" -r 'exit(function_exists("pcntl_fork") ? 0 : 1);'; then
	for mode in off on; do
		run_mode "processes_$mode" "$mode" --processes 2
	done
else
	echo "Skipping the runs with --processes 2 (pcntl is not available)"
fi

if [[ "$FAILURES" != 0 ]]; then
	echo "$FAILURES check(s) of the conditional call fixture suite failed" 1>&2
	exit 1
fi
echo "The output of all runs matches $EXPECTED_PATH, with no violation and identical digests"
exit 0
