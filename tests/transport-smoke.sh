#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."
sandbox=$(mktemp -d "$PWD/build/transport.XXXXXXXX")
trap 'rm -rf "$sandbox"' EXIT
mkdir -p "$sandbox/bin" "$sandbox/remote" "$sandbox/source/bin" "$sandbox/source/build"
cp bin/deploy "$sandbox/source/bin/deploy"
cp build/release.tar.gz build/release.tar.gz.sha256 "$sandbox/source/build/"
release_id=$(tar -xOf build/release.tar.gz ./RELEASE)
cat > "$sandbox/bin/ssh" <<'SH'
#!/bin/bash
set -euo pipefail
[[ "$*" == *'StrictHostKeyChecking=yes'* && "$*" == *'BatchMode=yes'* ]] || exit 90
while [[ $1 == -o ]]; do shift 2; done
shift
bash -ec "$1"
SH
cat > "$sandbox/bin/scp" <<'SH'
#!/bin/bash
set -euo pipefail
[[ "$*" == *'StrictHostKeyChecking=yes'* ]] || exit 90
while [[ $1 == -o ]]; do shift 2; done
cp "$1" "${2#*:}"
SH
cat > "$sandbox/bin/remote-php" <<'SH'
#!/bin/bash
set -euo pipefail
# PHP activation/rollback is tested separately; here exercise the real shell transfer.
[[ -f $1 && -f $2/releases/$3/public/index.php && $4 == https://example.invalid ]] || exit 91
printf '%s\n' "$3" > "$2/transport-ok"
SH
chmod +x "$sandbox/bin/ssh" "$sandbox/bin/scp" "$sandbox/bin/remote-php"
export PATH="$sandbox/bin:$PATH"
export DEPLOY_HOST=example.invalid DEPLOY_USER=test DEPLOY_PATH="$sandbox/remote" DEPLOY_PHP="$sandbox/bin/remote-php" DEPLOY_ORIGIN=https://example.invalid
bash "$sandbox/source/bin/deploy" "$release_id"
test "$(cat "$sandbox/remote/transport-ok")" = "$release_id"
test -f "$sandbox/remote/releases/$release_id/.archive-sha256"
# Same release and archive can be retried without overwriting an existing tree.
touch "$sandbox/remote/releases/$release_id/preserved"
bash "$sandbox/source/bin/deploy" "$release_id"
test -f "$sandbox/remote/releases/$release_id/preserved"
if DEPLOY_PATH='/tmp/bad;echo injected' bash "$sandbox/source/bin/deploy" "$release_id"; then exit 92; fi
if bash "$sandbox/source/bin/deploy" '../../bad'; then exit 93; fi
printf 'corruption' >> "$sandbox/source/build/release.tar.gz"
if bash "$sandbox/source/bin/deploy" "$release_id"; then exit 94; fi
test -f "$sandbox/remote/releases/$release_id/preserved"
echo 'Deployment transport checks passed (SSH/SCP simulated locally).'
