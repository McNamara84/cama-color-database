#!/bin/sh
set -eu
origin=${1:-http://web}
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT HUP INT TERM
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 1 "$origin/" > "$tmp/home"
grep -q 'CAMA Color Database' "$tmp/home"
curl --fail --silent --show-error "$origin/health" > "$tmp/health"
php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); if(($data["status"]??null)!=="ok"){exit(1);}' "$tmp/health"
curl --fail --silent --show-error "$origin/assets/app.css" > /dev/null
curl --fail --silent --show-error --head "$origin/" > "$tmp/headers"
grep -qi 'X-Content-Type-Options: nosniff' "$tmp/headers"
for path in /.env /composer.json /vendor/autoload.php /phinx.php /bin/doctor.php /.git/config /missing; do
    code=$(curl --silent --show-error --path-as-is -o "$tmp/response" -w '%{http_code}' "$origin$path")
    case "$code" in 403|404) ;; *) echo "Unexpected HTTP $code at $path" >&2; exit 1;; esac
done
code=$(curl --silent --show-error -X POST -o /dev/null -w '%{http_code}' "$origin/")
test "$code" = 405
echo 'Apache/FPM smoke checks passed.'
