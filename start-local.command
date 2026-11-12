#!/bin/sh
cd "$(dirname "$0")"
if ! command -v php >/dev/null 2>&1; then
  echo "PHP is not installed or is not available in PATH. Install PHP and try again."
  read -r _
  exit 1
fi
echo "Faculty portfolio: http://127.0.0.1:8001"
echo "Admin page: http://127.0.0.1:8001/admin/"
echo "Leave this window open while you use the portfolio. Press Ctrl+C to stop."
php -S 127.0.0.1:8001 -t .
