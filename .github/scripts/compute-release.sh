#!/usr/bin/env bash
# The declared application version is authoritative. Releases are deliberately
# versioned by changing composer.json, so an ordinary deploy cannot silently
# renumber the production application from Git history.
set -euo pipefail

VERSION=$(php -r '$c=json_decode(file_get_contents("composer.json"), true); echo $c["version"] ?? "";')
if [[ ! "$VERSION" =~ ^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,3}$ ]]; then
  echo "Invalid composer.json version '$VERSION'; expected major.minor.patch (up to 2.2.3 digits)." >&2
  exit 1
fi

cat > version.json <<JSON
{
  "version": "${VERSION}",
  "date": "$(date -u +%Y-%m-%d)",
  "commit": "$(git rev-parse --short HEAD)",
  "changes": {"features": [], "fixes": [], "improvements": []}
}
JSON

NEW_RELEASE=false
git rev-parse "v${VERSION}" >/dev/null 2>&1 || NEW_RELEASE=true
echo "Declared version: ${VERSION} | New release tag: ${NEW_RELEASE}"
cat version.json

if [[ -n "${GITHUB_ENV:-}" ]]; then
  {
    echo "NEW_RELEASE=${NEW_RELEASE}"
    echo "RELEASE_VERSION=${VERSION}"
  } >> "$GITHUB_ENV"
fi
