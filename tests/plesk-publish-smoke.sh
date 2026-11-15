#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."
sandbox=$(mktemp -d "$PWD/build/plesk-git.XXXXXXXX")
trap 'rm -rf "$sandbox"' EXIT
git init --bare --initial-branch=main "$sandbox/remote.git" > /dev/null
git init --initial-branch=main "$sandbox/source" > /dev/null
mkdir -p "$sandbox/source/bin" "$sandbox/source/build" "$sandbox/package"
cp bin/publish-plesk "$sandbox/source/bin/"
tar -xzf build/release.tar.gz -C "$sandbox/package"
git -C "$sandbox/source" config user.name 'Deployment test'
git -C "$sandbox/source" config user.email test@example.invalid
git -C "$sandbox/source" remote add origin "$sandbox/remote.git"
git -C "$sandbox/source" add bin/publish-plesk
git -C "$sandbox/source" commit -m 'Source one' > /dev/null
git -C "$sandbox/source" push origin main
first=$(git -C "$sandbox/source" rev-parse HEAD)
package() {
    printf '%s\n' "$1" > "$sandbox/package/RELEASE"
    (cd "$sandbox/package" && find . -type f ! -name MANIFEST.sha256 -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sed 's#  \./#  #') > "$sandbox/package/MANIFEST.sha256"
    tar -czf "$sandbox/source/build/release.tar.gz" -C "$sandbox/package" .
    (cd "$sandbox/source/build" && sha256sum release.tar.gz > release.tar.gz.sha256)
}
deploy_head() { git --git-dir="$sandbox/remote.git" rev-parse refs/heads/deploy; }
export GITHUB_OUTPUT="$sandbox/output"
package "$first"
bash "$sandbox/source/bin/publish-plesk" "$first"
initial=$(deploy_head)
test "$(git --git-dir="$sandbox/remote.git" show deploy:RELEASE)" = "$first"
test "$(git --git-dir="$sandbox/remote.git" rev-parse main)" = "$first"
test "$(tail -n 1 "$GITHUB_OUTPUT")" = published=true
git --git-dir="$sandbox/remote.git" cat-file -e deploy:vendor/autoload.php
for path in .env .github/workflows/ci.yml tests IMPLEMENTIERUNGSPLAN.md; do
    if git --git-dir="$sandbox/remote.git" cat-file -e "deploy:$path" 2>/dev/null; then exit 1; fi
done
# Repeating an identical deployment does not add commits, but can re-trigger the hook.
bash "$sandbox/source/bin/publish-plesk" "$first"
test "$(deploy_head)" = "$initial"
git -C "$sandbox/source" diff --exit-code --cached
git -C "$sandbox/source" commit --allow-empty -m 'Source two' > /dev/null
git -C "$sandbox/source" push origin main
second=$(git -C "$sandbox/source" rev-parse HEAD)
# A slow old CI run cannot replace a newer main, and no package gets published on mismatch.
bash "$sandbox/source/bin/publish-plesk" "$first"
test "$(tail -n 1 "$GITHUB_OUTPUT")" = published=false
test "$(deploy_head)" = "$initial"
if bash "$sandbox/source/bin/publish-plesk" "$second"; then exit 1; fi
package "$second"
bash "$sandbox/source/bin/publish-plesk" "$second"
latest=$(deploy_head)
test "$latest" != "$initial"
git --git-dir="$sandbox/remote.git" merge-base --is-ancestor "$initial" "$latest"
test "$(git --git-dir="$sandbox/remote.git" show deploy:RELEASE)" = "$second"
printf 'corrupt' >> "$sandbox/source/build/release.tar.gz"
if bash "$sandbox/source/bin/publish-plesk" "$second"; then exit 1; fi
if bash "$sandbox/source/bin/publish-plesk" '../bad'; then exit 1; fi
git -C "$sandbox/source" remote set-url origin "$sandbox/nonexistent.git"
if bash "$sandbox/source/bin/publish-plesk" "$second"; then exit 1; fi
test "$(deploy_head)" = "$latest"
echo 'Plesk publication checks passed (real Git, local remote).'
