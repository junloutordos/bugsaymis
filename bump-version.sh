#!/bin/bash
# Usage: ./bump-version.sh <new-version> "<remarks>"
# Example: ./bump-version.sh 1.1.0 "Added Document Tracking module and Dashboard pie charts."

set -e

VERSION_FILE="version.json"
NEW_VERSION="$1"
REMARKS="$2"
DATE=$(date +%Y-%m-%d)

if [ -z "$NEW_VERSION" ] || [ -z "$REMARKS" ]; then
  echo "Usage: ./bump-version.sh <version> \"<remarks>\""
  echo "Example: ./bump-version.sh 1.1.0 \"Added Document Tracking and Dashboard improvements.\""
  exit 1
fi

# Read current history and prepend new entry using PHP (already available in Laravel projects)
php - <<EOF
<?php
\$file = '$VERSION_FILE';
\$data = json_decode(file_get_contents(\$file), true);

\$newEntry = [
    'version' => '$NEW_VERSION',
    'date'    => '$DATE',
    'remarks' => '$REMARKS',
];

// Add new entry to history
\$data['history'][] = \$newEntry;
\$data['version'] = '$NEW_VERSION';

file_put_contents(\$file, json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
echo "Version bumped to $NEW_VERSION\n";
EOF

echo "Done. version.json updated."
echo ""
cat $VERSION_FILE
