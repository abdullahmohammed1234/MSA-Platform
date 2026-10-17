#!/usr/bin/env bash
# SFU MSA Platform — match a production preflight fingerprint to a repository revision (local, read-only).
#
# Usage: identify-production-release.sh <preflight-output.txt>
#
# Reads the FINGERPRINT section written by production-readonly-preflight.sh (git blob hashes of the
# deployed files) and compares it with every commit reachable from any ref, plus the current working
# tree. msa-backend/ maps to backend/, public_html/ maps to frontend/dist/.
#
# Exit codes: 0 exact match found, 1 no exact match (closest candidates listed), 2 usage / no fingerprint.

set -uo pipefail

INPUT="${1:-}"
if [[ -z "$INPUT" || ! -f "$INPUT" ]]; then
    echo "Usage: $0 <preflight-output.txt>" >&2
    exit 2
fi

REPO="$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "Run inside the repository." >&2; exit 2; }
cd "$REPO"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

awk '$1 == "BLOB" && NF >= 3 {
        path = $3
        for (i = 4; i <= NF; i++) path = path " " $i
        sub(/^msa-backend\//, "backend/", path)
        sub(/^public_html\//, "frontend/dist/", path)
        print $2 "\t" path
     }' "$INPUT" | tr -d '\r' | sort -k2 > "$WORK/prod"

PROD_COUNT="$(wc -l < "$WORK/prod" | tr -d ' ')"
if (( PROD_COUNT == 0 )); then
    echo "No FINGERPRINT entries found in $INPUT." >&2
    exit 2
fi

SCOPE=(backend/app backend/bootstrap/app.php backend/config backend/routes backend/database/migrations
       backend/resources/views backend/composer.json backend/composer.lock backend/public/index.php
       backend/public/.htaccess frontend/dist/index.html frontend/dist/.htaccess frontend/dist/assets)

compare() {
    local label="$1" listing="$2"
    awk -F'\t' -v label="$label" -v details="$WORK/details-$label" '
        NR == FNR { prod[$2] = $1; next }
        { repo[$2] = $1 }
        END {
            same = 0; differ = 0; only_prod = 0; only_repo = 0
            for (p in prod) {
                if (!(p in repo)) { only_prod++; print "only-in-production " p > details }
                else if (repo[p] == prod[p]) same++
                else { differ++; print "differs " p > details }
            }
            for (p in repo) if (!(p in prod)) { only_repo++; print "only-in-repo " p > details }
            printf "%d\t%d\t%d\t%d\t%s\n", differ + only_prod + only_repo, same, differ, only_prod "/" only_repo, label
        }' "$WORK/prod" "$listing"
}

: > "$WORK/scores"
while read -r commit; do
    git ls-tree -r "$commit" -- "${SCOPE[@]}" | awk -F'\t' '{ split($1, m, " "); print m[3] "\t" $2 }' > "$WORK/tree"
    compare "$commit" "$WORK/tree" >> "$WORK/scores"
done < <(git rev-list --all)

git ls-files -c -o --exclude-standard -- "${SCOPE[@]}" | while IFS= read -r p; do
    [[ -f "$p" ]] && printf '%s\t%s\n' "$(git hash-object -- "$p")" "$p"
done > "$WORK/tree"
compare WORKTREE "$WORK/tree" >> "$WORK/scores"

echo "Production fingerprint: $PROD_COUNT files"
echo
echo "Closest revisions (mismatches, identical, changed, only-in-production/only-in-repo):"
sort -s -n -k1,1 "$WORK/scores" | head -n 5 | while IFS=$'\t' read -r total same differ only label; do
    if [[ "$label" == WORKTREE ]]; then
        desc="current working tree (uncommitted)"
    else
        desc="$(git log -1 --format='%h %ad %s' --date=short "$label")"
    fi
    printf '  %5s mismatches  %5s identical  %5s changed  %9s  %s\n' "$total" "$same" "$differ" "$only" "$desc"
done

read -r best_total _ _ _ best_label < <(sort -s -n -k1,1 "$WORK/scores" | head -n 1)
echo
if (( best_total == 0 )); then
    echo "Result: EXACT MATCH — production corresponds to ${best_label}."
    exit 0
fi
echo "Result: NO EXACT MATCH — closest is ${best_label} with ${best_total} mismatching files (first 40):"
sort "$WORK/details-$best_label" | head -n 40 | sed 's/^/  /'
exit 1
