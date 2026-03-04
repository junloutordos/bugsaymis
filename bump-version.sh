#!/bin/bash
# Usage:
#   Manual:    ./bump-version.sh 1.2.0 "Added new feature XYZ."
#   Auto-patch: ./bump-version.sh auto "Remarks here."

set -e

VERSION_FILE="version.json"
NEW_VERSION="$1"
REMARKS="$2"
DATE=$(date +%Y-%m-%d)

if [ -z "$NEW_VERSION" ] || [ -z "$REMARKS" ]; then
  echo "Usage: ./bump-version.sh <version|auto> \"<remarks>\""
  echo "Example: ./bump-version.sh 1.1.0 \"Added Document Tracking.\""
  echo "Example: ./bump-version.sh auto \"Auto-patch bump.\""
  exit 1
fi

# If version is "auto", read current and increment patch
if [ "$NEW_VERSION" = "auto" ]; then
  CURRENT=$(php -r "echo json_decode(file_get_contents('$VERSION_FILE'), true)['version'] ?? '1.0.0';")
  IFS='.' read -ra PARTS <<< "$CURRENT"
  MAJOR=${PARTS[0]:-1}
  MINOR=${PARTS[1]:-0}
  PATCH=$(( ${PARTS[2]:-0} + 1 ))
  NEW_VERSION="$MAJOR.$MINOR.$PATCH"
fi

php - <<EOF
<?php
\$file = '$VERSION_FILE';
\$data = json_decode(file_get_contents(\$file), true);

\$newEntry = [
    'version' => '$NEW_VERSION',
    'date'    => '$DATE',
    'remarks' => '$REMARKS',
];

\$data['history'][] = \$newEntry;
\$data['version'] = '$NEW_VERSION';

file_put_contents(\$file, json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
echo "Version bumped to $NEW_VERSION\n";
EOF

echo "Done. version.json updated."
echo ""
cat $VERSION_FILE
