#!/usr/bin/env bash
#
# Backfill the performance-over-releases series on ONE machine.
#
# A cross-release trend is only meaningful measured on identical hardware, so the
# `history` block in benchmarks.json is a deliberate, same-machine backfill rather
# than something CI appends per release (CI hardware would mix into the trend).
#
# For each git tag this checks it out into a throwaway worktree, runs that release's
# own benchmark harness (so the numbers reflect the engine as it shipped), reads the
# "full: parse+validate+execute (100)" median, and writes the series into the given
# benchmarks.json — leaving meta/phases/scaling/comparison untouched.
#
# Requirements: a PHP 8.4 binary (the engine requires ^8.4). Point $PHP at it if the
# default `php` on PATH is a different version:
#
#   PHP=/path/to/php8.4 benchmarks/measure-history.sh docs/playground/benchmarks.json
#   PHP=php84 benchmarks/measure-history.sh docs/playground/benchmarks.json v1.2.0 v1.2.1 v1.3.0
#
set -euo pipefail

PHP="${PHP:-php}"
TARGET="${1:?usage: measure-history.sh <benchmarks.json> [tag...]}"
shift || true

TAGS=("$@")
if [ ${#TAGS[@]} -eq 0 ]; then
    # Default: every release tag, oldest first (natural version sort).
    # Avoids `mapfile` so it works on the stock macOS bash 3.2.
    while IFS= read -r _tag; do
        [ -n "$_tag" ] && TAGS+=("$_tag")
    done < <(git tag --list 'v*' | sort -V)
fi

ROOT="$(git rev-parse --show-toplevel)"
VENDOR="$ROOT/vendor"
if [ ! -d "$VENDOR" ]; then
    echo "vendor/ not found — run 'composer install' first." >&2
    exit 1
fi

PHPVER="$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if [ "$PHPVER" != "8.4" ] && [ "$PHPVER" != "8.5" ]; then
    echo "warning: \$PHP is $PHPVER; the engine needs 8.4+. Set PHP=<your php8.4>." >&2
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "measuring ${#TAGS[@]} tags with PHP $PHPVER ..." >&2
declare -a PAIRS=()
for tag in "${TAGS[@]}"; do
    if ! git cat-file -e "$tag:benchmarks/run.php" 2>/dev/null; then
        echo "  $tag: no benchmark harness, skipping" >&2
        continue
    fi
    WT="$TMP/wt-$tag"
    git worktree add -q "$WT" "$tag"
    cp -R "$VENDOR" "$WT/vendor"
    ( cd "$WT" && composer dump-autoload -q >/dev/null 2>&1 || true )
    line="$( cd "$WT" && "$PHP" benchmarks/run.php 2>/dev/null | grep -E '^full: parse' || true )"
    git worktree remove --force "$WT" >/dev/null 2>&1 || true

    # Median is the trailing "<num> µs" or "<num> ms" column -> normalise to ms.
    ms="$(printf '%s\n' "$line" | python3 -c '
import re, sys
s = sys.stdin.read()
m = re.search(r"([0-9][0-9.,]*)\s*(µs|ms)\s*$", s)
if not m:
    sys.exit(1)
val = float(m.group(1).replace(",", ""))
print(round(val / 1000 if m.group(2) == "µs" else val, 3))
')"
    ver="${tag#v}"
    echo "  $tag -> ${ms} ms" >&2
    PAIRS+=("$ver=$ms")
done

# Rewrite only the `history` block of the target JSON.
HISTORY_JSON="$(printf '%s\n' "${PAIRS[@]}" | python3 -c '
import json, sys
out = []
for line in sys.stdin:
    line = line.strip()
    if not line:
        continue
    ver, ms = line.split("=", 1)
    out.append({"version": ver, "fullQuery100Ms": float(ms)})
print(json.dumps(out))
')"

python3 - "$TARGET" "$HISTORY_JSON" <<'PY'
import json, sys
target, history = sys.argv[1], json.loads(sys.argv[2])
with open(target) as f:
    doc = json.load(f)
doc["history"] = history
with open(target, "w") as f:
    json.dump(doc, f, indent=2, ensure_ascii=False)
    f.write("\n")
print(f"wrote {len(history)} history points into {target}")
PY
