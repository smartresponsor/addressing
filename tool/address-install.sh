#!/usr/bin/env bash
set -euo pipefail
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
# Author: Oleksandr Tishchenko <dev@smartresponsor.com>
# Owner: Marketing America Corp
#
# Prereq: PHP 8.2 with PDO_pgsql and PDO_mysql extensions; Postgres and MySQL running.
# Env: PG_DSN, PG_USER, PG_PASS, MY_DSN, MY_USER, MY_PASS

echo "Applying Postgres schema..."
php bin/address-migrate

echo "Ensuring index policy (trgm + composite)..."
php bin/address-index-policy enable-trgm
php bin/address-index-policy enable-composite

echo "Projection full sync (initial)..."
php bin/address-projection-sync-since 1970-01-01T00:00:00Z

echo "GA install completed."
