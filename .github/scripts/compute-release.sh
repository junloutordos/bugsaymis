#!/usr/bin/env bash
# Computes the next semver release from Conventional Commits since the last
# v* tag and writes version.json into the build context. Also exports
# NEW_RELEASE / RELEASE_VERSION to $GITHUB_ENV when running in Actions.
#
# Bump rules: BREAKING CHANGE or "!" -> major, feat -> minor,
# fix/perf/refactor -> patch, anything else -> no release (redeploy current).
set -euo pipefail

LAST_TAG=$(git describe --tags --abbrev=0 --match 'v[0-9]*' 2>/dev/null || echo '')

if [[ -z "$LAST_TAG" ]]; then
  LAST_TAG="v1.0.0"
  RANGE="HEAD"          # no baseline tag yet — treat HEAD as 1.0.0, no release
  NO_BASELINE=1
else
  RANGE="${LAST_TAG}..HEAD"
  NO_BASELINE=0
fi

CURRENT_VERSION="${LAST_TAG#v}"

MAJOR=0; MINOR=0; PATCH=0
FEATURES=(); FIXES=(); IMPROVEMENTS=()

clean_subject() {
  # strip "type(scope)!: " prefix, then capitalize the first letter
  local s="$1"
  s=$(sed -E 's/^[a-z]+(\([^)]*\))?!?:[[:space:]]*//' <<<"$s")
  printf '%s' "$(tr '[:lower:]' '[:upper:]' <<<"${s:0:1}")${s:1}"
}

if [[ "$NO_BASELINE" -eq 0 ]]; then
  while IFS= read -r hash; do
    [[ -z "$hash" ]] && continue
    subject=$(git log -1 --format='%s' "$hash")
    body=$(git log -1 --format='%b' "$hash")

    if [[ "$subject" =~ ^[a-z]+(\([^\)]*\))?!: ]] || grep -q '^BREAKING CHANGE' <<<"$body"; then
      MAJOR=1
    fi

    case "$subject" in
      feat:*|feat\(*)              MINOR=1; FEATURES+=("$(clean_subject "$subject")") ;;
      fix:*|fix\(*)                PATCH=1; FIXES+=("$(clean_subject "$subject")") ;;
      perf:*|perf\(*|refactor:*|refactor\(*)
                                   PATCH=1; IMPROVEMENTS+=("$(clean_subject "$subject")") ;;
    esac
  done < <(git log "$RANGE" --format='%H')
fi

IFS='.' read -r V_MAJOR V_MINOR V_PATCH <<<"$CURRENT_VERSION"

NEW_RELEASE=false
if [[ "$MAJOR" -eq 1 ]]; then
  NEW_RELEASE=true; V_MAJOR=$((V_MAJOR + 1)); V_MINOR=0; V_PATCH=0
elif [[ "$MINOR" -eq 1 ]]; then
  NEW_RELEASE=true; V_MINOR=$((V_MINOR + 1)); V_PATCH=0
elif [[ "$PATCH" -eq 1 ]]; then
  NEW_RELEASE=true; V_PATCH=$((V_PATCH + 1))
fi

VERSION="${V_MAJOR}.${V_MINOR}.${V_PATCH}"

json_array() {
  local out="" item
  for item in "$@"; do
    item=${item//\\/\\\\}
    item=${item//\"/\\\"}
    out+="\"${item}\","
  done
  printf '[%s]' "${out%,}"
}

cat > version.json <<JSON
{
  "version": "${VERSION}",
  "date": "$(date -u +%Y-%m-%d)",
  "commit": "$(git rev-parse --short HEAD)",
  "changes": {
    "features": $(json_array "${FEATURES[@]+"${FEATURES[@]}"}"),
    "fixes": $(json_array "${FIXES[@]+"${FIXES[@]}"}"),
    "improvements": $(json_array "${IMPROVEMENTS[@]+"${IMPROVEMENTS[@]}"}")
  }
}
JSON

echo "Last tag: ${LAST_TAG} | New release: ${NEW_RELEASE} | Version: ${VERSION}"
cat version.json

if [[ -n "${GITHUB_ENV:-}" ]]; then
  {
    echo "NEW_RELEASE=${NEW_RELEASE}"
    echo "RELEASE_VERSION=${VERSION}"
  } >> "$GITHUB_ENV"
fi
