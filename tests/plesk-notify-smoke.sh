#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."
sandbox=$(mktemp -d)
trap 'rm -rf "$sandbox"' EXIT
export TEST_RELEASE=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
export PLESK_WEBHOOK_URL=https://panel.example.invalid/hook?secret=private
export DEPLOY_ORIGIN=https://cama.example.invalid
export REQUEST_LOG="$sandbox/requests"
cat > "$sandbox/curl" <<'SH'
#!/bin/bash
set -euo pipefail
[[ "$*" == *"--proto =https"* && "$*" != *"--insecure"* ]] || exit 99
if [[ ${*: -1} == "$PLESK_WEBHOOK_URL" ]]; then
    [[ "$*" == *'--request POST'* && "$*" == *'refs/heads/deploy'* ]] || exit 98
    echo hook >> "$REQUEST_LOG"
    case "$SCENARIO" in
        refused) echo 403;;
        redirect) echo 302;;
        network) exit 7;;
        *) echo 200;;
    esac
elif [[ ${*: -1} == *'/health?'* ]]; then
    echo health >> "$REQUEST_LOG"
    case "$SCENARIO" in
        stale) echo '{"status":"ok","release":"old"}';;
        invalid) echo '{"status":"error"}';;
        malformed) echo 'not-json';;
        *) printf '{"status":"ok","release":"%s"}\n' "$TEST_RELEASE";;
    esac
else
    echo home >> "$REQUEST_LOG"
    if [[ $SCENARIO == broken-home ]]; then exit 22; fi
    echo '<!doctype html>'
fi
SH
cat > "$sandbox/sleep" <<'SH'
#!/bin/sh
exit 0
SH
chmod +x "$sandbox/curl" "$sandbox/sleep"
export PATH="$sandbox:$PATH"
SCENARIO=success bash bin/notify-plesk "$TEST_RELEASE" > "$sandbox/output" 2>&1
grep -q 'is live' "$sandbox/output"
for scenario in refused redirect network stale invalid malformed broken-home; do
    if SCENARIO="$scenario" bash bin/notify-plesk "$TEST_RELEASE" > "$sandbox/output" 2>&1; then exit 1; fi
    if grep -q 'secret=private' "$sandbox/output"; then exit 1; fi
done
if SCENARIO=success PLESK_WEBHOOK_URL=http://insecure.invalid bash bin/notify-plesk "$TEST_RELEASE"; then exit 1; fi
if SCENARIO=success DEPLOY_ORIGIN=https://example.invalid/path bash bin/notify-plesk "$TEST_RELEASE"; then exit 1; fi
echo 'Plesk notification checks passed (HTTP simulated, real JSON validation).'
