#!/usr/bin/env bash

set -euo pipefail

file_mode() {
  if stat -c '%a' "$1" >/dev/null 2>&1; then
    stat -c '%a' "$1"
  else
    stat -f '%Lp' "$1"
  fi
}

file_uid() {
  if stat -c '%u' "$1" >/dev/null 2>&1; then
    stat -c '%u' "$1"
  else
    stat -f '%u' "$1"
  fi
}

read_sha256() {
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$1"
  else
    shasum -a 256 "$1"
  fi
}

archive="${1:-}"
if [[ -z "$archive" || ! -f "$archive" || ! -f "${archive}.sha256" ]]; then
  echo "Usage: bash scripts/verify-release-snapshot.sh /absolute/path/release-YYYYmmdd-HHMMSS.tar.gz" >&2
  exit 1
fi
if [[ "$archive" != /* || -L "$archive" || -L "${archive}.sha256" ]]; then
  echo "Refusing to verify: archive and checksum must be absolute, regular, non-symlink files." >&2
  exit 1
fi

archive_mode="$(file_mode "$archive")"
checksum_mode="$(file_mode "${archive}.sha256")"
if [[ "$archive_mode" != "400" && "$archive_mode" != "600" ]]; then
  echo "Refusing to verify: archive must have mode 400 or 600 (detected: $archive_mode)." >&2
  exit 1
fi
if [[ "$checksum_mode" != "400" && "$checksum_mode" != "600" ]]; then
  echo "Refusing to verify: checksum must have mode 400 or 600 (detected: $checksum_mode)." >&2
  exit 1
fi

current_uid="$(id -u)"
if [[ "$(file_uid "$archive")" != "$current_uid" || "$(file_uid "${archive}.sha256")" != "$current_uid" ]]; then
  echo "Refusing to verify: archive and checksum must be owned by the release user." >&2
  exit 1
fi

expected_hash="$(awk 'NR == 1 { print $1 }' "${archive}.sha256")"
actual_hash="$(read_sha256 "$archive" | awk '{ print $1 }')"
if [[ ! "$expected_hash" =~ ^[a-f0-9]{64}$ || "$actual_hash" != "$expected_hash" ]]; then
  echo "Snapshot verification failed: SHA-256 checksum does not match." >&2
  exit 1
fi

restore_dir="$(mktemp -d)"
trap 'rm -rf "$restore_dir"' EXIT
tar -xzf "$archive" -C "$restore_dir"

for required_path in artisan composer.json composer.lock public/build/manifest.json; do
  if [[ ! -f "$restore_dir/$required_path" ]]; then
    echo "Snapshot verification failed: missing $required_path." >&2
    exit 1
  fi
done
if find "$restore_dir" -type f \( -name '.env' -o -name '.env.*' \) -print -quit | grep -q .; then
  echo "Snapshot verification failed: environment credentials were included." >&2
  exit 1
fi
if find "$restore_dir/bootstrap/cache" -type f -name '*.php' -print -quit 2>/dev/null | grep -q . \
  || find "$restore_dir/database" -type f -name '*.sqlite' -print -quit 2>/dev/null | grep -q . \
  || [[ -d "$restore_dir/test-results" ]]; then
  echo "Snapshot verification failed: runtime cache or disposable test data was included." >&2
  exit 1
fi
if find "$restore_dir" -type f \( -name '.DS_Store' -o -name '._*' \) -print -quit | grep -q .; then
  echo "Snapshot verification failed: macOS metadata files were included." >&2
  exit 1
fi

php -r '$manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if (! isset($manifest["resources/js/app.js"]["file"])) { exit(1); }' "$restore_dir/public/build/manifest.json"
composer validate --working-dir="$restore_dir" --no-check-publish --no-interaction >/dev/null

rm -rf "$restore_dir"
trap - EXIT
echo "Release snapshot verified: checksum, extraction, manifest and Composer metadata are valid."
