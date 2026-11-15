#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
php -r 'if (extension_loaded("xdebug")) { fwrite(STDERR, "Use the package-test runtime service without Xdebug.\n"); exit(1); }'
stage=$(mktemp -d "$PWD/build/smoke.XXXXXXXX")
server_pid=''
cleanup() {
    if [ -n "$server_pid" ]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
    rm -rf "$stage"
}
trap cleanup EXIT HUP INT TERM
(cd build && sha256sum -c release.tar.gz.sha256)
tar -xzf build/release.tar.gz -C "$stage"
for item in .env .git tests docker compose.yaml phpmyadmin IMPLEMENTIERUNGSPLAN.md vendor/phpunit; do
    test ! -e "$stage/$item" || { echo "Unexpected package content: $item" >&2; exit 1; }
done
test -f "$stage/vendor/autoload.php"
test -f "$stage/vendor/bin/phinx"
test -f "$stage/bin/composer.phar"
test -f "$stage/bin/plesk-deploy.php"
(cd "$stage" && sha256sum -c MANIFEST.sha256 > /dev/null)
# Exercise the Plesk file importer with the complete real production dependency tree.
mkdir "$stage/hosting"
php -r 'require $argv[1]."/vendor/autoload.php"; $id=trim(file_get_contents($argv[1]."/RELEASE")); $import=new Cama\Infrastructure\StagedRelease(); $release=$import->install($argv[1],$argv[1]."/hosting",$id); if($release!==$import->install($argv[1],$argv[1]."/hosting",$id)){exit(1);}' "$stage"
APP_ENV=production APP_DEBUG=false APP_RUNTIME_DIR="$stage/var" php "$stage/bin/doctor.php"
APP_ENV=production APP_DEBUG=false APP_RUNTIME_DIR="$stage/var" php -S 127.0.0.1:8099 -t "$stage/public" "$stage/public/index.php" > "$stage/server.log" 2>&1 &
server_pid=$!
curl --silent --show-error --fail --retry 10 --retry-connrefused --retry-delay 1 http://127.0.0.1:8099/ > "$stage/home"
grep -q 'CAMA Color Database' "$stage/home"
curl --silent --show-error --fail http://127.0.0.1:8099/health > "$stage/health.json"
php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); if(($data["release"]??null)!==trim(file_get_contents($argv[2]))){exit(1);}' "$stage/health.json" "$stage/RELEASE"
echo 'Production package smoke checks passed.'
