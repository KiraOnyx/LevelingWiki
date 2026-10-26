#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
compose_test() { docker compose --env-file .env -f tests/compose.yaml "$@"; }
# Ce projet nommé levelingwiki-tests ne partage ni base, ni réseau, ni stockage avec levelingwiki.
compose_test down
trap 'compose_test down' EXIT
compose_test up -d --build
compose_test exec -T web php bin/migrate.php
compose_test exec -T web php tests/seed.php
compose_test exec -T web php tests/admin.php
compose_test exec -T web php tests/phase4.php
compose_test exec -T web php tests/phase5.php
compose_test exec -T web php tests/quick-status.php
compose_test exec -T web php tests/dynamic-progress.php
compose_test exec -T web php tests/valhalla.php
compose_test exec -T web php tests/illustrations.php
compose_test exec -T web php tests/binary-migration.php
compose_test exec -T web php tests/import.php
compose_test exec -T web php tests/category-identity.php
compose_test exec -T web php tests/database.php
compose_test exec -T web php tests/catalog.php
compose_test exec -T web php tests/http.php
compose_test exec -T web php tests/guild-tower.php
compose_test exec -T web php tests/guild-catalog.php
compose_test exec -T web php tests/tower-prototype.php
compose_test exec -T web php tests/ux-unified.php
compose_test exec -T web php tests/admin-forms.php
compose_test exec -T web php tests/admin-dashboard.php

previous_owner_path=$(compose_test exec -T web php -r 'echo getenv("OWNER_LOGIN_PATH");')
next_owner_path=$(compose_test exec -T web php bin/owner-access.php --generate)
TEST_OWNER_LOGIN_PATH="$next_owner_path" compose_test up -d web
compose_test exec -T -e OWNER_PREVIOUS_PATH="$previous_owner_path" web php tests/owner-rotation.php
