#!/usr/bin/env bash
# bench.sh - run one Phan benchmark target K times (after W discarded warmups),
# each run in a memory-capped systemd user scope, then summarize.
# All data goes under ${PHAN_PERF_HOME:-$HOME/phan-perf}. See tool/perf/README.md.

set -u -o pipefail

usage() {
    cat <<'EOF'
Usage: bench.sh -t TARGET [-j N] [-r K] [-w W] [-v VARIANT] [--phan PATH]
                [--baseline] [--mem-max 12G] [--pin] [--no-timings] [--label X]
                [-- extra phan args]

  -t TARGET     self | self-quick | self-noisy | project-subset
  -j N          phan --processes (default 1)
  -r K          measured runs (default 5 for self*, 3 for project-subset)
  -w W          warmup runs, discarded (default 1)
  -v VARIANT    ci | nocache | shm | jit (default ci)
  --phan PATH   phan entry point to benchmark (default: this checkout's ./phan);
                its directory is the phan repo used for git info and self targets
  --baseline    project-subset only: --load-baseline .phan/baseline.php
  --mem-max SZ  systemd MemoryMax for the run's scope (default 12G)
  --pin         taskset -c 0-3
  --no-timings  never pass --phase-timings-json
  --label X     appended to the result directory name

Internal (used by ab.sh):
  --run-dir DIR     write runs into DIR instead of a new timestamped directory
  --run-offset N    number the first measured run N+1
  --no-summary      do not call bench_summarize.php

Environment:
  PHAN_PERF_HOME    data root (default $HOME/phan-perf)
  PHAN_HELPERS_SO   phan_helpers extension path, or "none" to not load it
                    (default /usr/lib/php/20240924/phan_helpers.so)
  PHP_BIN           php binary (default php)
  PHAN_PERF_NO_CGROUP=1  do not use systemd-run (no memory cap, no cgroup stats)
  PHAN_PERF_PROJECT_ROOT       project tree for project-subset
                               (default $PHAN_PERF_HOME/project-src)
  PHAN_PERF_SUBSET_DIR         config.frozen.php / subset.files / subset.analyze.files /
                               tree.pin location (default $PHAN_PERF_HOME/project)
  PHAN_PERF_SUBSET_EXTENSIONS  extra extensions for project-subset
                               (default "imap.so mcrypt.so xmlrpc.so")
EOF
}

die() { echo "bench.sh: $*" >&2; exit 2; }
warn() { echo "bench.sh: warning: $*" >&2; }
info() { echo "bench.sh: $*" >&2; }

json_str() {
    local s=$1
    s=${s//\\/\\\\}
    s=${s//\"/\\\"}
    s=${s//$'\t'/ }
    s=${s//$'\r'/}
    s=${s//$'\n'/ }
    printf '"%s"' "$s"
}

SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
PERF=${PHAN_PERF_HOME:-$HOME/phan-perf}
PHP_BIN=${PHP_BIN:-php}
PHAN_HELPERS_SO=${PHAN_HELPERS_SO:-/usr/lib/php/20240924/phan_helpers.so}
TIME_FMT='{"wall_s":%e,"user_s":%U,"sys_s":%S,"maxrss_kb":%M,"minflt":%R,"majflt":%F,"nvcsw":%w,"nivcsw":%c,"exit":%x}'
FAIL_RE='Child terminated with return code|Saw errors for an analysis worker|PHP Fatal error|Allowed memory size'
STDOUT_FAIL_RE='^(PHP )?Fatal error:|Allowed memory size of'

target= j=1 runs= warm=1 variant=ci phan= baseline=0 mem_max=12G pin=0 timings=auto label=
run_dir= run_offset=0 summarize=1
extra=()

# Accept attached short-option values (-j4, -r2, -vjit) and --opt=value.
norm=()
while (($#)); do
    case $1 in
        --) norm+=("$@"); break ;;
        -[tjrwv]?*) norm+=("${1:0:2}" "${1:2}") ;;
        --*=*) norm+=("${1%%=*}" "${1#*=}") ;;
        *) norm+=("$1") ;;
    esac
    shift
done
set -- "${norm[@]}"
unset norm

while (($#)); do
    case $1 in
        -t|--target) target=${2?missing value for $1}; shift 2 ;;
        -j|--processes) j=${2?missing value for $1}; shift 2 ;;
        -r|--runs) runs=${2?missing value for $1}; shift 2 ;;
        -w|--warmup) warm=${2?missing value for $1}; shift 2 ;;
        -v|--variant) variant=${2?missing value for $1}; shift 2 ;;
        --phan) phan=${2?missing value for $1}; shift 2 ;;
        --baseline) baseline=1; shift ;;
        --mem-max) mem_max=${2?missing value for $1}; shift 2 ;;
        --pin) pin=1; shift ;;
        --no-timings) timings=off; shift ;;
        --label) label=${2?missing value for $1}; shift 2 ;;
        --run-dir) run_dir=${2?missing value for $1}; shift 2 ;;
        --run-offset) run_offset=${2?missing value for $1}; shift 2 ;;
        --no-summary) summarize=0; shift ;;
        -h|--help) usage; exit 0 ;;
        --) shift; extra=("$@"); break ;;
        *) usage >&2; die "unknown option: $1" ;;
    esac
done

[[ -n $target ]] || { usage >&2; die "-t TARGET is required"; }
[[ $j =~ ^[1-9][0-9]*$ ]] || die "-j must be a positive integer"
[[ $warm =~ ^[0-9]+$ ]] || die "-w must be a non-negative integer"
[[ $run_offset =~ ^[0-9]+$ ]] || die "--run-offset must be a non-negative integer"
[[ $label =~ ^[A-Za-z0-9._+-]*$ ]] || die "--label may only contain [A-Za-z0-9._+-]"
case $variant in ci|nocache|shm|jit) ;; *) die "unknown variant: $variant" ;; esac

if [[ -z $phan ]]; then
    phan=$SCRIPT_DIR/../../phan
fi
phan=$(realpath -e -- "$phan") || die "phan entry point not found: $phan"
phan_repo=$(dirname -- "$phan")
[[ -f $phan_repo/src/Phan/Phan.php ]] || warn "$phan_repo does not look like a phan checkout"

# The autoloader must load Phan's classes from this checkout. A symlinked vendor/
# makes composer's __DIR__-relative baseDir point at the other checkout.
phan_cli_path=
if [[ -f $phan_repo/vendor/autoload.php ]]; then
    phan_cli_path=$(cd "$phan_repo" && "$PHP_BIN" -n -d extension=ast.so -r \
        'require "vendor/autoload.php"; echo (new ReflectionClass("Phan\\CLI"))->getFileName();' 2>&1)
    [[ $phan_cli_path == "$phan_repo"/* ]] ||
        die "Phan\\CLI resolves to '$phan_cli_path', which is outside $phan_repo (symlinked vendor/?)"
else
    warn "$phan_repo/vendor/autoload.php not found; cannot verify where Phan's classes load from"
fi

php_extra=()
analyze_list=
case $target in
    self|self-quick|self-noisy)
        workdir=$phan_repo
        target_args=(-k .phan/config.php)
        [[ $target == self-quick ]] && target_args+=(--quick)
        [[ $target == self-noisy ]] && target_args+=(--analyze-all-files)
        default_runs=5
        ((baseline)) && warn "--baseline is ignored for $target"
        ;;
    project-subset)
        project_root=${PHAN_PERF_PROJECT_ROOT:-$PERF/project-src}
        subset_dir=${PHAN_PERF_SUBSET_DIR:-$PERF/project}
        project_root=$(realpath -e -- "$project_root") || die "project root not found (set PHAN_PERF_PROJECT_ROOT)"
        [[ -f $subset_dir/config.frozen.php ]] || die "missing $subset_dir/config.frozen.php"
        [[ -f $subset_dir/subset.files ]] || die "missing $subset_dir/subset.files"
        workdir=$project_root
        for ext in ${PHAN_PERF_SUBSET_EXTENSIONS-imap.so mcrypt.so xmlrpc.so}; do
            php_extra+=(-d "extension=$ext")
        done
        subset_dir=$(realpath -- "$subset_dir")
        # Parse the whole selection, analyze only seeds + hard closure (make_subset.php
        # --analyze-list). target_args are built once the result directory exists.
        [[ -f $subset_dir/subset.analyze.files ]] && analyze_list=$subset_dir/subset.analyze.files
        target_args=()
        default_runs=3
        ;;
    *) die "unknown target: $target" ;;
esac
runs=${runs:-$default_runs}
[[ $runs =~ ^[0-9]+$ ]] || die "-r must be a non-negative integer"

# ---- phan identity -------------------------------------------------------
if sha=$(git -C "$phan_repo" rev-parse HEAD 2>/dev/null); then
    diff_sha=$(git --no-optional-locks -C "$phan_repo" diff HEAD 2>/dev/null | sha1sum | cut -c1-40)
    if [[ $diff_sha == da39a3ee5e6b4b0d3255bfef95601890afd80709 ]]; then
        dirty=
    else
        dirty=${diff_sha:0:8}
    fi
else
    warn "$phan_repo is not a git checkout; sha=unknown"
    sha=unknown
    dirty=
fi
sha10=${sha:0:10}
build_id=$sha10${dirty:+-dirty-$dirty}

# ---- project-subset tree pin ----------------------------------------------
tree_pin=
if [[ $target == project-subset ]]; then
    if head=$(git -C "$project_root" rev-parse HEAD 2>/dev/null); then
        status_sha=$(git --no-optional-locks -C "$project_root" status --porcelain 2>/dev/null | sha1sum | cut -c1-40)
        lock_sha=$( { sha1sum < "$project_root/composer.lock" 2>/dev/null || echo none; } | cut -c1-40)
        tree_pin="head=$head status=$status_sha composer_lock=$lock_sha"
        if [[ -f $subset_dir/tree.pin ]]; then
            [[ $(<"$subset_dir/tree.pin") == "$tree_pin" ]] || die "project tree changed since $subset_dir/tree.pin was written:
  pinned:  $(<"$subset_dir/tree.pin")
  current: $tree_pin
(restore the tree, or delete tree.pin to re-pin)"
        else
            printf '%s\n' "$tree_pin" > "$subset_dir/tree.pin"
            info "pinned project tree in $subset_dir/tree.pin"
        fi
    else
        warn "$project_root is not a git checkout; tree pin check skipped"
    fi
fi

# ---- php command line ------------------------------------------------------
php_cmd=("$PHP_BIN" -n -d extension=ast.so)
[[ $PHAN_HELPERS_SO != none ]] && php_cmd+=(-d "extension=$PHAN_HELPERS_SO")
php_cmd+=("${php_extra[@]}")
opcache_common=(-d zend_extension=opcache.so -d opcache.enable_cli=1 -d opcache.interned_strings_buffer=128)
case $variant in
    ci)
        opcache_dir=$PERF/opcache/$build_id
        mkdir -p "$opcache_dir" || die "cannot create $opcache_dir"
        php_cmd+=("${opcache_common[@]}" -d "opcache.file_cache=$opcache_dir" -d opcache.file_cache_only=1)
        ;;
    # SHM modes carve the 128 MB interned strings buffer out of memory_consumption
    # (default 128 MB), so raise it or opcache refuses to start.
    shm) php_cmd+=("${opcache_common[@]}" -d opcache.memory_consumption=512) ;;
    jit) php_cmd+=("${opcache_common[@]}" -d opcache.memory_consumption=512 -d opcache.jit=tracing
             -d opcache.jit_buffer_size=128M) ;;
    nocache) ;;
esac

if [[ $timings == auto ]]; then
    help_text=$("$PHP_BIN" -n -d extension=ast.so "$phan" --extended-help 2>&1)
    if [[ $help_text == *--phase-timings-json* ]]; then timings=on; else timings=off; fi
    unset help_text
fi

common_args=(--no-progress-bar --no-color -j "$j" -m text --always-exit-successfully-after-analysis)

# ---- cgroup availability -----------------------------------------------------
cg_ok=0
if [[ ${PHAN_PERF_NO_CGROUP:-0} == 1 ]]; then
    :
elif command -v systemd-run >/dev/null 2>&1; then
    probe=$(systemd-run --user --scope --quiet -p MemoryMax=64M -p MemorySwapMax=0 -- \
        sh -c 'cg=$(sed -n "s/^0:://p" /proc/self/cgroup); cat "/sys/fs/cgroup$cg/memory.max"' 2>/dev/null)
    [[ $probe == 67108864 ]] && cg_ok=1
fi
((cg_ok)) || warn "systemd-run --user scope with a memory controller is unavailable; running without a MemoryMax cap and without cgroup memory stats"
if ((pin)) && ! command -v taskset >/dev/null 2>&1; then
    warn "taskset not found; --pin ignored"
    pin=0
fi
[[ -x /usr/bin/time ]] || die "/usr/bin/time (GNU time) is required"

# ---- result directory ------------------------------------------------------
utc=$(date -u +%Y%m%dT%H%M%SZ)
if [[ -z $run_dir ]]; then
    base=$PERF/results/$target/$utc-$build_id-j$j-$variant${label:+-$label}
    run_dir=$base
    n=2
    while [[ -e $run_dir ]]; do run_dir=$base-$((n++)); done
fi
mkdir -p "$run_dir" || die "cannot create $run_dir"
run_dir=$(realpath -- "$run_dir")

# ---- project-subset arguments ----------------------------------------------
analyze_count=
if [[ $target == project-subset ]]; then
    subset_config=$subset_dir/config.frozen.php
    if [[ -n $analyze_list ]]; then
        # --include-analysis-file-list takes a comma-separated list of files, not a list
        # file, and a large list would exceed the kernel's per-argument limit, so set
        # include_analysis_file_list from a wrapper config instead.
        analyze_count=$("$PHP_BIN" -n -r '[, $frozen, $list, $out] = $argv;
$files = file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$files) { fwrite(STDERR, "empty or unreadable analyze list $list\n"); exit(1); }
$code = "<?php\n// Generated by tool/perf/bench.sh: the frozen config, analyzing only the analyze list.\n"
    . "\$config = require " . var_export($frozen, true) . ";\n"
    . "\$config[\"include_analysis_file_list\"] = file(" . var_export($list, true)
    . ", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);\nreturn \$config;\n";
if (file_put_contents($out, $code) === false) { exit(1); }
echo count($files);' -- "$subset_config" "$analyze_list" "$run_dir/config.analyze.php") ||
            die "cannot write $run_dir/config.analyze.php"
        subset_config=$run_dir/config.analyze.php
    fi
    target_args=(--project-root-directory "$project_root" -k "$subset_config" --file-list "$subset_dir/subset.files")
    ((baseline)) && target_args+=(--load-baseline .phan/baseline.php)
fi

# ---- preflight / env.json ---------------------------------------------------
read -r load1 _ < /proc/loadavg
awk -v l="$load1" 'BEGIN { exit !(l > 1.0) }' && warn "load1 is $load1 (> 1.0); timings will be noisy"
mem_avail_kb=$(awk '$1 == "MemAvailable:" { print $2 }' /proc/meminfo)

probe_php='$s = function_exists("opcache_get_status") ? @opcache_get_status(false) : false;
echo json_encode([
    "php_version" => PHP_VERSION,
    "ast" => phpversion("ast") ?: null,
    "phan_helpers" => extension_loaded("phan_helpers") ? (phpversion("phan_helpers") ?: "loaded") : null,
    "extension_dir" => ini_get("extension_dir"),
    "extensions" => get_loaded_extensions(),
    "zend_extensions" => get_loaded_extensions(true),
    "opcache_enabled" => is_array($s) ? ($s["opcache_enabled"] ?? null) : false,
    "opcache_file_cache_only" => is_array($s) ? ($s["file_cache_only"] ?? null) : null,
    "jit" => is_array($s) ? ($s["jit"] ?? null) : null,
]);'
php_info_json=$("${php_cmd[@]}" -r "$probe_php" 2>"$run_dir/preflight.err") || die "php preflight failed: $(head -c 2000 "$run_dir/preflight.err")"
php_v=$("${php_cmd[@]}" -v 2>&1)
phan_version=$(cd "$workdir" && "${php_cmd[@]}" "$phan" --version 2>&1 | head -n 1)
ext_dir=$("$PHP_BIN" -n -r 'echo ini_get("extension_dir");')
ast_so=$ext_dir/ast.so
ast_sha256=$(sha256sum "$ast_so" 2>/dev/null | cut -c1-64)
helpers_sha256=
if [[ $PHAN_HELPERS_SO != none ]]; then
    helpers_path=$PHAN_HELPERS_SO
    [[ $helpers_path == /* ]] || helpers_path=$ext_dir/$helpers_path
    helpers_sha256=$(sha256sum "$helpers_path" 2>/dev/null | cut -c1-64)
fi

export BENCH_ENV_FILE=$run_dir/env.json BENCH_PHP_INFO=$php_info_json BENCH_PHP_V=$php_v \
    BENCH_UTC=$utc BENCH_TARGET=$target BENCH_VARIANT=$variant BENCH_J=$j BENCH_RUNS=$runs \
    BENCH_WARM=$warm BENCH_LABEL=$label BENCH_PHAN=$phan BENCH_PHAN_REPO=$phan_repo \
    BENCH_PHAN_VERSION=$phan_version BENCH_PHAN_CLI_PATH=$phan_cli_path BENCH_SHA=$sha BENCH_DIRTY=$dirty BENCH_WORKDIR=$workdir \
    BENCH_AST_SO=$ast_so BENCH_AST_SHA256=$ast_sha256 BENCH_HELPERS_SO=$PHAN_HELPERS_SO \
    BENCH_HELPERS_SHA256=$helpers_sha256 BENCH_LOAD1=$load1 BENCH_NPROC=$(nproc) \
    BENCH_MEM_AVAIL_KB=$mem_avail_kb BENCH_MEM_MAX=$mem_max BENCH_CGROUP=$cg_ok BENCH_PIN=$pin \
    BENCH_TIMINGS=$timings BENCH_PERF=$PERF BENCH_TREE_PIN=$tree_pin BENCH_ANALYZE_LIST=$analyze_list BENCH_ANALYZE_COUNT=$analyze_count BENCH_KERNEL=$(uname -r) \
    BENCH_CPU=$(awk -F': ' '/^model name/ { print $2; exit }' /proc/cpuinfo)
"$PHP_BIN" -n -- "${php_cmd[@]}" -- "${target_args[@]}" -- "${extra[@]}" <<'PHP' || die "cannot write env.json"
<?php
$e = static fn(string $k): string => (string)getenv($k);
$args = array_slice($argv, 1);
$groups = [[]];
foreach ($args as $a) {
    if ($a === '--' && count($groups) < 3) {
        $groups[] = [];
        continue;
    }
    $groups[count($groups) - 1][] = $a;
}
$env = [
    'schema' => 'phan-perf-env/1',
    'utc' => $e('BENCH_UTC'),
    'target' => $e('BENCH_TARGET'),
    'variant' => $e('BENCH_VARIANT'),
    'j' => (int)$e('BENCH_J'),
    'runs' => (int)$e('BENCH_RUNS'),
    'warmup' => (int)$e('BENCH_WARM'),
    'label' => $e('BENCH_LABEL'),
    'perf_home' => $e('BENCH_PERF'),
    'phan' => [
        'path' => $e('BENCH_PHAN'),
        'repo' => $e('BENCH_PHAN_REPO'),
        'version' => $e('BENCH_PHAN_VERSION'),
        'sha' => $e('BENCH_SHA'),
        'dirty' => $e('BENCH_DIRTY'),
        'phase_timings' => $e('BENCH_TIMINGS') === 'on',
        'cli_class_file' => $e('BENCH_PHAN_CLI_PATH') ?: null,
    ],
    'workdir' => $e('BENCH_WORKDIR'),
    'php_cmd' => $groups[0],
    'target_args' => $groups[1] ?? [],
    'extra_args' => $groups[2] ?? [],
    'php_v' => $e('BENCH_PHP_V'),
    'php' => json_decode($e('BENCH_PHP_INFO'), true),
    'ast_so' => ['path' => $e('BENCH_AST_SO'), 'sha256' => $e('BENCH_AST_SHA256')],
    'phan_helpers_so' => ['path' => $e('BENCH_HELPERS_SO'), 'sha256' => $e('BENCH_HELPERS_SHA256')],
    'load1' => (float)$e('BENCH_LOAD1'),
    'nproc' => (int)$e('BENCH_NPROC'),
    'mem_available_kb' => (int)$e('BENCH_MEM_AVAIL_KB'),
    'mem_max' => $e('BENCH_MEM_MAX'),
    'cgroup' => $e('BENCH_CGROUP') === '1',
    'pin' => $e('BENCH_PIN') === '1' ? '0-3' : null,
    'kernel' => $e('BENCH_KERNEL'),
    'cpu' => $e('BENCH_CPU'),
    'tree_pin' => $e('BENCH_TREE_PIN') ?: null,
    'analysis_file_list' => $e('BENCH_ANALYZE_LIST') ?: null,
    'analysis_file_list_used' => $e('BENCH_ANALYZE_LIST') !== '',
    'analysis_file_count' => $e('BENCH_ANALYZE_COUNT') !== '' ? (int)$e('BENCH_ANALYZE_COUNT') : null,
];
file_put_contents($e('BENCH_ENV_FILE'), json_encode($env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") !== false || exit(1);
PHP

# ---- per-run machinery -------------------------------------------------------
# Runs inside the scope: records its cgroup, runs the command, then snapshots
# memory.events / memory.peak before the scope disappears.
INNER='out=$1; use_cg=$2; shift 2
cg=$(sed -n "s/^0:://p" /proc/self/cgroup)
[ "$use_cg" = 1 ] && printf "%s\n" "$cg" > "$out/cgroup.path"
"$@"
rc=$?
if [ "$use_cg" = 1 ] && [ -r "/sys/fs/cgroup$cg/memory.events" ]; then
    { cat "/sys/fs/cgroup$cg/memory.events"; printf "peak %s\n" "$(cat "/sys/fs/cgroup$cg/memory.peak" 2>/dev/null)"; } > "$out/cgroup.final" 2>/dev/null
fi
exit $rc'

# Background poller: 0.25 s samples of memory.current, memory.stat anon, memory.events oom_kill.
poll_mem() {
    local pid=$1 rd=$2 cg= cur anon oom k v peak_cur=0 peak_anon=0 peak_oom=0 samples=0 tries=0
    while [[ ! -s $rd/cgroup.path ]] && kill -0 "$pid" 2>/dev/null && ((tries++ < 400)); do sleep 0.05; done
    [[ -s $rd/cgroup.path ]] && cg=/sys/fs/cgroup$(<"$rd/cgroup.path")
    while kill -0 "$pid" 2>/dev/null; do
        if [[ -n $cg ]]; then
            cur= anon= oom=
            { read -r cur < "$cg/memory.current"; } 2>/dev/null
            while read -r k v; do
                [[ $k == anon ]] && { anon=$v; break; }
            done < "$cg/memory.stat" 2>/dev/null
            while read -r k v; do
                [[ $k == oom_kill ]] && { oom=$v; break; }
            done < "$cg/memory.events" 2>/dev/null
            [[ -n $cur ]] && ((cur > peak_cur)) && peak_cur=$cur
            [[ -n $anon ]] && ((anon > peak_anon)) && peak_anon=$anon
            [[ -n $oom ]] && ((oom > peak_oom)) && peak_oom=$oom
            [[ -n $cur ]] && ((samples++))
        fi
        sleep 0.25
    done
    printf 'peak_current %s\npeak_anon %s\noom_kill %s\nsamples %s\n' "$peak_cur" "$peak_anon" "$peak_oom" "$samples" > "$rd/mem.poll"
}

# run_one <dir> -> 0 if the run passed all checks
run_one() {
    local rd=$1 unit rc pid poller reasons=() line t_exit peak_final oom_final k v
    local peak_cur= peak_anon= oom= samples=0 run_load1
    rm -rf -- "$rd"
    mkdir -p "$rd"
    read -r run_load1 _ < /proc/loadavg
    local cmd=(/usr/bin/time -o "$rd/time.raw" -f "$TIME_FMT" "${php_cmd[@]}" "$phan" "${common_args[@]}"
        -o "$rd/issues.txt" "${target_args[@]}")
    [[ $timings == on ]] && cmd+=(--phase-timings-json "$rd/timings.json")
    cmd+=("${extra[@]}")
    ((pin)) && cmd=(taskset -c 0-3 "${cmd[@]}")
    printf '%q ' "${cmd[@]}" > "$rd/cmd.txt"
    echo >> "$rd/cmd.txt"
    local full=(bash -c "$INNER" bench-inner "$rd" "$cg_ok" "${cmd[@]}")
    if ((cg_ok)); then
        unit=phan-bench-$$-$RANDOM$RANDOM
        full=(systemd-run --user --scope --quiet "--unit=$unit" -p "MemoryMax=$mem_max" -p MemorySwapMax=0 -- "${full[@]}")
    fi
    ( cd "$workdir" && exec "${full[@]}" ) > "$rd/stdout.txt" 2> "$rd/stderr.txt" &
    pid=$!
    poll_mem "$pid" "$rd" &
    poller=$!
    wait "$pid"
    rc=$?
    wait "$poller"

    grep '^{' "$rd/time.raw" 2>/dev/null | tail -n 1 > "$rd/time.json"
    if [[ -s $rd/mem.poll ]]; then
        while read -r k v; do
            case $k in
                peak_current) peak_cur=$v ;; peak_anon) peak_anon=$v ;;
                oom_kill) oom=$v ;; samples) samples=$v ;;
            esac
        done < "$rd/mem.poll"
    fi
    if [[ -s $rd/cgroup.final ]]; then
        while read -r k v; do
            case $k in
                oom_kill) oom_final=$v; ((oom_final > ${oom:-0})) && oom=$oom_final ;;
                peak) peak_final=$v; [[ -n $peak_final ]] && ((peak_final > ${peak_cur:-0})) && peak_cur=$peak_final ;;
            esac
        done < "$rd/cgroup.final"
    fi
    if ((cg_ok)); then
        printf '{"cgroup":true,"peak_current_kb":%s,"peak_anon_kb":%s,"oom_kill":%s,"samples":%s}\n' \
            "$(( ${peak_cur:-0} / 1024 ))" "$(( ${peak_anon:-0} / 1024 ))" "${oom:-0}" "$samples" > "$rd/mem.json"
    else
        printf '{"cgroup":false,"peak_current_kb":null,"peak_anon_kb":null,"oom_kill":null,"samples":0}\n' > "$rd/mem.json"
    fi

    ((rc != 0)) && reasons+=("wrapper exit status $rc")
    t_exit=$(grep -o '"exit":[0-9-]*' "$rd/time.json" 2>/dev/null | cut -d: -f2)
    if [[ -z $t_exit ]]; then
        reasons+=("no GNU time output")
    elif ((t_exit != 0)); then
        reasons+=("phan exit status $t_exit")
    fi
    grep -q 'Command terminated by signal' "$rd/time.raw" 2>/dev/null &&
        reasons+=("$(grep -m1 'Command terminated by signal' "$rd/time.raw")")
    ((${oom:-0} > 0)) && reasons+=("oom_kill=$oom")
    if line=$(grep -E -m1 "$FAIL_RE" "$rd/stderr.txt"); then reasons+=("stderr: ${line:0:300}"); fi
    if line=$(grep -E -m1 "$STDOUT_FAIL_RE" "$rd/stdout.txt"); then reasons+=("stdout: ${line:0:300}"); fi
    [[ -f $rd/issues.txt ]] || reasons+=("missing issues.txt")

    {
        printf '{"ok":%s,"rc":%d,"load1":%s,"reasons":[' "$( ((${#reasons[@]} == 0)) && echo true || echo false)" "$rc" "$run_load1"
        local first=1 r
        for r in "${reasons[@]}"; do
            ((first)) || printf ','
            first=0
            json_str "$r"
        done
        printf ']}\n'
    } > "$rd/result.json"
    if ((${#reasons[@]})); then
        info "$(basename "$rd") FAILED: ${reasons[*]}"
        return 1
    fi
    return 0
}

info "results: $run_dir"
info "target=$target variant=$variant j=$j runs=$runs warmup=$warm phan=$phan ($build_id) timings=$timings cgroup=$cg_ok"

status=0
for ((w = 1; w <= warm; w++)); do
    info "warmup $w/$warm"
    if ! run_one "$run_dir/warmup-$w"; then
        status=1
        break
    fi
done
if ((status == 0)); then
    for ((k = 1; k <= runs; k++)); do
        idx=$((run_offset + k))
        start=$SECONDS
        if ! run_one "$run_dir/run-$idx"; then
            status=1
            break
        fi
        info "run $idx: $(<"$run_dir/run-$idx/time.json") ($((SECONDS - start)) s)"
    done
fi

if ((summarize && runs > 0)); then
    "$PHP_BIN" -n "$SCRIPT_DIR/bench_summarize.php" "$run_dir" || status=1
fi
exit $status
