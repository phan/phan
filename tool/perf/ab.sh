#!/usr/bin/env bash
# ab.sh <shaA> <shaB> -- <bench.sh args>
#
# A/B benchmark of two phan commits. Each commit gets a detached worktree under
# $PHAN_PERF_HOME/wt/<sha> (vendor/ copied from the main checkout when composer.lock
# is identical, else `composer install`). Runs W warmups per side, then K rounds of
# A,B interleaved (bench.sh -r 1 -w 0 each), summarizes both sides, prints the
# deltas against the noise rule, and diffs the issues of the first run of each.
#
# -r K / -w W in the bench.sh args set the rounds / warmups (defaults as bench.sh).
# Gate: at -j1 the issue diff must be IDENTICAL; at -j>1 only suggestion-only
# groups are tolerated (issue_diff exit 2); real and union-order-only groups fail.
# Exit status: 0 gate passed, 1 gate failed or a run failed.
#
# Git use is limited to `rev-parse`, `worktree list` and `worktree add`.

set -u -o pipefail

die() { echo "ab.sh: $*" >&2; exit 1; }
warn() { echo "ab.sh: warning: $*" >&2; }
info() { echo "ab.sh: $*" >&2; }

SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
PERF=${PHAN_PERF_HOME:-$HOME/phan-perf}
PHP_BIN=${PHP_BIN:-php}
REPO=${PHAN_REPO:-$(cd -- "$SCRIPT_DIR/../.." && pwd)}

if (($# < 2)) || [[ $1 == -h || $1 == --help ]]; then
    sed -n '2,15p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
    exit 2
fi
refA=$1 refB=$2
shift 2
[[ ${1-} == -- ]] && shift

bench_args=()
K= W=1 target= label= jobs=1
while (($#)); do
    case $1 in
        -r|--runs) K=${2?missing value for $1}; shift 2 ;;
        -r?*) K=${1:2}; shift ;;
        -w|--warmup) W=${2?missing value for $1}; shift 2 ;;
        -w?*) W=${1:2}; shift ;;
        -t|--target) target=${2?missing value for $1}; bench_args+=("$1" "$2"); shift 2 ;;
        -t?*) target=${1:2}; bench_args+=("$1"); shift ;;
        --label) label=${2?missing value for $1}; bench_args+=("$1" "$2"); shift 2 ;;
        -j|--processes) jobs=${2?missing value for $1}; bench_args+=("$1" "$2"); shift 2 ;;
        -j?*) jobs=${1:2}; bench_args+=("$1"); shift ;;
        --phan|--run-dir|--run-offset|--no-summary) die "$1 is set by ab.sh" ;;
        --) bench_args+=("$@"); break ;;
        *) bench_args+=("$1"); shift ;;
    esac
done
[[ -n $target ]] || die "bench.sh args must include -t TARGET"
if [[ -z $K ]]; then
    [[ $target == project-subset ]] && K=3 || K=5
fi
[[ $K =~ ^[1-9][0-9]*$ ]] || die "-r must be a positive integer"
[[ $W =~ ^[0-9]+$ ]] || die "-w must be a non-negative integer"

shaA=$(git -C "$REPO" rev-parse --verify --quiet "$refA^{commit}") || die "unknown commit: $refA"
shaB=$(git -C "$REPO" rev-parse --verify --quiet "$refB^{commit}") || die "unknown commit: $refB"
[[ $shaA != "$shaB" ]] || warn "A and B are the same commit ($shaA): this measures noise"

# ensure_worktree <sha> -> prints the worktree path
ensure_worktree() {
    local sha=$1 wt=$PERF/wt/$1 head cli
    if [[ ! -e $wt ]]; then
        mkdir -p "$PERF/wt"
        info "creating worktree $wt"
        git -C "$REPO" worktree add --detach "$wt" "$sha" >&2 || die "git worktree add failed for $sha"
    elif ! git -C "$REPO" worktree list --porcelain | grep -qxF -e "worktree $wt" -e "worktree $(realpath "$wt")"; then
        die "$wt exists but is not a worktree of $REPO"
    fi
    head=$(git -C "$wt" rev-parse HEAD) || die "cannot read HEAD of $wt"
    [[ $head == "$sha" ]] || die "$wt is at $head, expected $sha"
    if [[ -L $wt/vendor ]]; then
        # A symlinked vendor/ makes composer autoload Phan from the symlink target's checkout.
        warn "$wt/vendor is a symlink; replacing it with a copy"
        rm -f -- "$wt/vendor"
    fi
    if [[ ! -d $wt/vendor ]]; then
        if [[ -d $REPO/vendor ]] && cmp -s "$REPO/composer.lock" "$wt/composer.lock"; then
            info "copying vendor/ into $wt"
            cp -a -- "$REPO/vendor" "$wt/vendor" || die "copying vendor/ failed"
        else
            warn "composer.lock of $sha differs from $REPO; running composer install in $wt"
            command -v composer >/dev/null 2>&1 || die "composer not found"
            (cd "$wt" && composer install --no-interaction --no-progress --quiet) >&2 || die "composer install failed in $wt"
        fi
    fi
    cli=$(cd "$wt" && "$PHP_BIN" -n -d extension=ast.so -r \
        'require "vendor/autoload.php"; echo (new ReflectionClass("Phan\\CLI"))->getFileName();' 2>&1)
    [[ $cli == "$(realpath "$wt")"/* ]] || die "Phan\\CLI in $wt resolves to '$cli'"
    printf '%s\n' "$wt"
}

wtA=$(ensure_worktree "$shaA") || exit 1
wtB=$(ensure_worktree "$shaB") || exit 1

utc=$(date -u +%Y%m%dT%H%M%SZ)
ab_dir=$PERF/results/ab/$utc-${shaA:0:10}-vs-${shaB:0:10}${label:+-$label}
dirA=$ab_dir/A
dirB=$ab_dir/B
mkdir -p "$dirA" "$dirB" || die "cannot create $ab_dir"
info "results: $ab_dir (rounds=$K warmup=$W)"
{
    echo "A $shaA $wtA"
    echo "B $shaB $wtB"
    printf 'bench args:'; printf ' %q' "${bench_args[@]}"; echo
} > "$ab_dir/ab.txt"

bench() {
    "$SCRIPT_DIR/bench.sh" "$@"
}

if ((W > 0)); then
    for side in A B; do
        [[ $side == A ]] && wt=$wtA dir=$dirA || wt=$wtB dir=$dirB
        info "warmup $side"
        bench --phan "$wt/phan" --run-dir "$dir" --no-summary -r 0 -w "$W" "${bench_args[@]}" ||
            die "warmup $side failed (see $dir)"
    done
fi
for ((k = 1; k <= K; k++)); do
    for side in A B; do
        [[ $side == A ]] && wt=$wtA dir=$dirA || wt=$wtB dir=$dirB
        info "round $k/$K: $side"
        bench --phan "$wt/phan" --run-dir "$dir" --run-offset $((k - 1)) --no-summary -r 1 -w 0 "${bench_args[@]}" ||
            die "round $k $side failed (see $dir/run-$k)"
    done
done

echo "== A ($shaA)"
"$PHP_BIN" -n "$SCRIPT_DIR/bench_summarize.php" "$dirA"
echo
echo "== B ($shaB)"
"$PHP_BIN" -n "$SCRIPT_DIR/bench_summarize.php" "$dirB"
echo
echo "== B vs A"
"$PHP_BIN" -n "$SCRIPT_DIR/bench_summarize.php" --compare "$dirA" "$dirB"
echo
echo "== issue diff (run-1)"
"$PHP_BIN" -n "$SCRIPT_DIR/issue_diff.php" "$dirA/run-1/issues.txt" "$dirB/run-1/issues.txt"
rc=$?
if ((rc == 0 || (rc == 2 && jobs > 1))); then
    echo "GATE (-j$jobs): PASS (issue_diff exit $rc)"
    exit 0
fi
echo "GATE (-j$jobs): FAIL (issue_diff exit $rc)"
exit 1
