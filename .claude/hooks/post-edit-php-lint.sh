#!/bin/bash
# Auto-lint PHP files after Edit or Write tool calls.
# Claude Code provides CLAUDE_TOOL_INPUT as a JSON string.

FILE=$(echo "$CLAUDE_TOOL_INPUT" | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
    print(d.get('file_path', ''))
except:
    print('')
" 2>/dev/null)

if [[ "$FILE" == *.php ]]; then
  RESULT=$(php -l "$FILE" 2>&1)
  if echo "$RESULT" | grep -q "Parse error\|syntax error"; then
    echo "⚠ PHP syntax error in $FILE:"
    echo "$RESULT"
    exit 1
  fi
fi
exit 0
