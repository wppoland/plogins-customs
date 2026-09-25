#!/usr/bin/env bash
# Build a clean, installable plogins-customs zip for wp.org, honouring .distignore.
# Boots via the PSR-4 fallback in autoload.php, so /vendor is not shipped.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-/tmp/customs-build}"
STAGE="${OUT_DIR}/plogins-customs"

# Header Version, the VERSION constant and the readme's Stable tag are three
# copies of one number, and the constant is the one that drifts: 1.0.10 shipped
# with it still reading 1.0.9. Refuse to package when they disagree.
hdr=$(grep -m1 -E '^ \* Version:' "${ROOT_DIR}/customs.php" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
cst=$(grep -m1 -E "^const VERSION" "${ROOT_DIR}/customs.php" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
tag=$(grep -m1 -E '^Stable tag:' "${ROOT_DIR}/readme.txt" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
if [ "${hdr}" != "${cst}" ] || [ "${hdr}" != "${tag}" ]; then
    echo "version mismatch: header=${hdr} const=${cst} stable-tag=${tag}" >&2
    exit 1
fi

rm -rf "${OUT_DIR}"
mkdir -p "${STAGE}"

rsync -a --exclude-from="${ROOT_DIR}/.distignore" \
    --exclude '.git' --exclude 'node_modules' --exclude 'vendor' \
    --exclude '.DS_Store' \
    "${ROOT_DIR}/" "${STAGE}/"

find "${STAGE}" -name '.DS_Store' -delete

rm -f /tmp/plogins-customs.zip
# zip -r adds to an existing archive, so a stale one keeps files the build no longer ships.
rm -f /tmp/plogins-customs.zip
( cd "${OUT_DIR}" && zip -rqX /tmp/plogins-customs.zip plogins-customs -x '*.DS_Store' )
echo "✓ Built /tmp/plogins-customs.zip from ${STAGE}"
