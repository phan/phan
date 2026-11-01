#!/usr/bin/env bash
# The fixture suite of lazy_inherited_methods (Analysis\LazyInheritedMethods).
#
# Runs Phan on src/ (with the classes of external/, which is parsed but excluded from analysis, as roots)
# three times: without lazy entries (PHAN_LAZY_INHERITED_METHODS=0, which overrides the config setting), with them
# (PHAN_LAZY_INHERITED_METHODS=1) and in verify mode (PHAN_LAZY_INHERIT_VERIFY=1). Each run must print the expected
# output, verify mode must find no violation, and the extended state digests (PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED=1)
# of the runs without lazy entries and in verify mode must be identical.
# Then the same for the quick_mode=false variant (.phan/config_slow.php, src_slow/), and the three modes with
# --processes 2 if pcntl is available.
EXPECTED_PATH=expected/all_output.expected
EXPECTED_SLOW_PATH=expected_slow/all_output.expected
if [ ! -d expected  ]; then
	echo "Error: must run this script from tests/lazy_inherit_test folder" 1>&2
	exit 1
fi
for path in $(echo expected/*.php.expected | LC_ALL=C sort); do cat $path; done > $EXPECTED_PATH || exit 1
for path in $(echo expected_slow/*.php.expected | LC_ALL=C sort); do cat $path; done > $EXPECTED_SLOW_PATH || exit 1

PHP_BINARY=${PHP_BINARY:-php}
FAILURES=0
TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

# run_mode <label> <mode> <expected path> <extra phan args...>
# mode: off (no lazy entries), on, verify
function run_mode {
	local label="$1" mode="$2" expected="$3"
	shift 3
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
	sed -i -e 's/src\\/src\//g' -e 's/external\\/external\//g' "$actual"
	if [[ "$exit_code" != 1 ]]; then
		# Phan exits with 1 if it reports issues (and with PHAN_LAZY_INHERIT_VERIFY=1, if a violation was found)
		echo "Unexpected exit code $exit_code for $label (expected 1: there are issues)" 1>&2
		cat "$TMP_DIR/$label.stderr" 1>&2
		FAILURES=$((FAILURES + 1))
	fi
	if ! diff "$expected" "$actual"; then
		echo "The output of $label differs from $expected" 1>&2
		FAILURES=$((FAILURES + 1))
	fi
	case "$mode" in
		off|on)
			if grep -q 'lazy_inherited_methods\|PHAN_LAZY_INHERIT_VERIFY' "$TMP_DIR/$label.stderr"; then
				echo "Unexpected messages about lazy_inherited_methods for $label:" 1>&2
				cat "$TMP_DIR/$label.stderr" 1>&2
				FAILURES=$((FAILURES + 1))
			fi
			;;
		verify)
			if grep 'PHAN_LAZY_INHERIT_VERIFY: .* (declared in ' "$TMP_DIR/$label.stderr" 1>&2 || \
				! grep -q '^PHAN_LAZY_INHERIT_VERIFY: [1-9][0-9]* lazy entries created, .*: 0 violation(s)$' "$TMP_DIR/$label.stderr"; then
				echo "PHAN_LAZY_INHERIT_VERIFY=1 found violations (or created no lazy entry) for $label:" 1>&2
				cat "$TMP_DIR/$label.stderr" 1>&2
				FAILURES=$((FAILURES + 1))
			fi
			;;
	esac
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
	run_mode "$mode" "$mode" "$EXPECTED_PATH"
done
compare_digests off verify

# The run with lazy entries must have exercised them (counters of LazyInheritedMethods, see --phase-timings-json)
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
foreach (["lazy_links", "lazy_links_relaxed_return_link", "lazy_template_recaptures", "lazy_materialized_class_pin", "lazy_pending_drained",
          "lazy_nested_processed", "lazy_materialized_late_lookup", "lazy_late_pipelines", "lazy_ops_recorded", "lazy_ops_replayed",
          "lazy_history_records", "lazy_history_pruned"] as $name) {
    if (($notes[$name] ?? 0) <= 0) {
        $missing[] = $name;
    }
}
if ($missing) {
    fwrite(STDERR, "These counters of lazy_inherited_methods are 0: " . implode(", ", $missing) . "\n");
    exit(1);
}
' "$TMP_DIR/timings.json" || FAILURES=$((FAILURES + 1))

# The quick_mode=false variant
for mode in off on verify; do
	run_mode "slow_$mode" "$mode" "$EXPECTED_SLOW_PATH" -k .phan/config_slow.php
done
compare_digests slow_off slow_verify

# With analysis workers (the parent creates the copies first needed in a worker, and workers report their violations).
# UnusedSuppressionPlugin only reports unused suppressions without --processes, so its issues are left out.
if "$PHP_BINARY" -r 'exit(function_exists("pcntl_fork") ? 0 : 1);'; then
	grep -v ' UnusedSuppression ' $EXPECTED_PATH > "$TMP_DIR/expected_processes"
	for mode in off on verify; do
		run_mode "processes_$mode" "$mode" "$TMP_DIR/expected_processes" --processes 2
	done
	compare_digests processes_off processes_verify
else
	echo "Skipping the runs with --processes 2 (pcntl is not available)"
fi

if [[ "$FAILURES" != 0 ]]; then
	echo "$FAILURES check(s) of the lazy_inherited_methods fixture suite failed" 1>&2
	exit 1
fi
echo "The output of all runs matches $EXPECTED_PATH and $EXPECTED_SLOW_PATH, with no violation and identical digests"
exit 0
