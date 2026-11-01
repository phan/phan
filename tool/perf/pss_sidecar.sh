#!/usr/bin/env bash
# pss_sidecar.sh <parent pid> <out.tsv>
#
# Every second, append one row per process (the parent and each direct child from
# `pgrep -P`) with smaps_rollup memory counters (kB) plus system MemAvailable (kB).
# Exits when the parent is gone. Example, for a Phan run with -j4:
#   php ... phan -j4 ... & tool/perf/pss_sidecar.sh $! pss.tsv

set -u

if (($# != 2)); then
    echo "usage: pss_sidecar.sh <parent pid> <out.tsv>" >&2
    exit 2
fi
parent=$1
out=$2
[[ $parent =~ ^[0-9]+$ ]] || { echo "pss_sidecar.sh: bad pid: $parent" >&2; exit 2; }

if [[ ! -s $out ]]; then
    printf 'ts\tpid\trole\tRss\tPss\tPrivate_Clean\tPrivate_Dirty\tShared_Clean\tShared_Dirty\tMemAvailable\n' > "$out"
fi

alive() {
    local state
    [[ -r /proc/$1/stat ]] || return 1
    # field 3 of /proc/<pid>/stat is the state; Z/X = gone
    read -r _ _ state _ < "/proc/$1/stat" 2>/dev/null || return 1
    [[ $state != Z && $state != X ]]
}

row() {
    local pid=$1 role=$2 ts=$3 avail=$4 k v rest
    local rss= pss= pc= pd= sc= sd=
    while read -r k v rest; do
        case $k in
            Rss:) rss=$v ;;
            Pss:) pss=$v ;;
            Private_Clean:) pc=$v ;;
            Private_Dirty:) pd=$v ;;
            Shared_Clean:) sc=$v ;;
            Shared_Dirty:) sd=$v ;;
        esac
    done < "/proc/$pid/smaps_rollup" 2>/dev/null || return 0
    [[ -n $rss ]] || return 0
    printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$ts" "$pid" "$role" "$rss" "$pss" "$pc" "$pd" "$sc" "$sd" "$avail"
}

while alive "$parent"; do
    ts=$(date +%s.%3N)
    avail=$(awk '$1 == "MemAvailable:" { print $2; exit }' /proc/meminfo)
    {
        row "$parent" parent "$ts" "$avail"
        for child in $(pgrep -P "$parent"); do
            row "$child" child "$ts" "$avail"
        done
    } >> "$out"
    sleep 1
done
exit 0
