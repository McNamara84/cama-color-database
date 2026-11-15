#!/bin/bash
(
set -eo pipefail
# Only generated hexadecimal local credentials are interpolated into bootstrap SQL.
for key in DB_PASSWORD DB_MIGRATION_PASSWORD DB_TEST_PASSWORD; do
    [[ ${!key} =~ ^[a-f0-9]{48}$ ]] || { echo "Invalid local credential format: $key" >&2; exit 1; }
done
docker_process_sql <<SQL
CREATE USER 'cama'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE ON cama.* TO 'cama'@'%';
CREATE USER 'cama_migration'@'%' IDENTIFIED BY '${DB_MIGRATION_PASSWORD}';
GRANT ALL PRIVILEGES ON cama.* TO 'cama_migration'@'%';
CREATE DATABASE cama_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cama_test'@'%' IDENTIFIED BY '${DB_TEST_PASSWORD}';
GRANT ALL PRIVILEGES ON cama_test.* TO 'cama_test'@'%';
SQL
)
