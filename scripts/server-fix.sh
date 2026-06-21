#!/bin/bash
# Run on server via SSH after DB import and .env are configured.
# Usage: bash server-fix.sh

set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== PHP ==="
php -v
php -m | grep -i pdo || true

if ! php -m | grep -qi '^PDO$'; then
  echo ""
  echo "ERROR: PDO still missing. Try CloudLinux selector (as your cPanel user):"
  echo '  cloudlinux-selector set --json --interpreter php --version 8.3 --extensions '"'"'{"pdo":1,"pdo_mysql":1,"mysqli":1}'"'"
  echo "Or enable pdo + pdo_mysql in cPanel → Select PHP Version for hotelsystemback.osus.network"
  exit 1
fi

echo "=== Laravel ==="
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan migrate --force
php artisan hotel:ensure-admin

echo "=== Done ==="
echo "Test: curl -s https://hotelsystemback.osus.network/api/users/setup-check | head -c 500"
echo "Login: job 001 / admin123 (after ensure-admin)"
