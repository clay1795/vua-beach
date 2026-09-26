#!/usr/bin/env bash

set -euo pipefail

file_mode() {
  if stat -c '%a' "$1" >/dev/null 2>&1; then
    stat -c '%a' "$1"
  else
    stat -f '%Lp' "$1"
  fi
}

write_sha256() {
  local file="$1"
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$file"
  else
    shasum -a 256 "$file"
  fi
}

if [[ ! -f artisan || ! -f composer.lock || ! -f public/build/manifest.json ]]; then
  echo "Run this script from a built Laravel release root." >&2
  exit 1
fi

snapshot_dir="${RELEASE_SNAPSHOT_DIR:-storage/app/release-snapshots}"
if [[ ! -d "$snapshot_dir" ]]; then
  mkdir -p "$snapshot_dir"
  chmod 700 "$snapshot_dir"
fi

snapshot_mode="$(file_mode "$snapshot_dir")"
if (( (0$snapshot_mode & 0007) != 0 )); then
  echo "Refusing to snapshot: destination must not be accessible by other users (detected mode: $snapshot_mode)." >&2
  exit 1
fi
if [[ ! -w "$snapshot_dir" ]]; then
  echo "Refusing to snapshot: destination is not writable: $snapshot_dir" >&2
  exit 1
fi

release_stamp="$(date +%Y%m%d-%H%M%S)"
archive="${snapshot_dir%/}/release-${release_stamp}.tar.gz"

# Runtime state, credentials and reproducible dependencies are deliberately not
# archived. Restore those from the server's shared storage/.env and Composer.
# macOS bsdtar otherwise stores xattr/Finder metadata which is irrelevant to a
# Linux release and creates noisy warnings during restore. GNU tar supports
# --no-xattrs but not libarchive's --no-mac-metadata.
tar_metadata_args=(--no-xattrs)
if tar --version 2>&1 | grep -qi 'bsdtar'; then
  tar_metadata_args+=(--no-mac-metadata --no-fflags)
fi
COPYFILE_DISABLE=1 tar "${tar_metadata_args[@]}" -czf "$archive" \
  --exclude='./.env' \
  --exclude='./.env.*' \
  --exclude='./.git' \
  --exclude='./.DS_Store' \
  --exclude='./._*' \
  --exclude='*/.DS_Store' \
  --exclude='*/._*' \
  --exclude='./bootstrap/cache/*.php' \
  --exclude='./database/*.sqlite' \
  --exclude='./node_modules' \
  --exclude='./output' \
  --exclude='./public/storage' \
  --exclude='./storage' \
  --exclude='./test-results' \
  --exclude='./vendor' \
  .
chmod 600 "$archive"

if tar -tzf "$archive" | grep -Eq '(^|/)\.env($|\.)'; then
  rm -f "$archive"
  echo "Snapshot rejected: an environment file was included." >&2
  exit 1
fi
if tar -tzf "$archive" | grep -Eq '(^|/)bootstrap/cache/[^/]+\.php$|(^|/)database/[^/]+\.sqlite$|(^|/)test-results(/|$)'; then
  rm -f "$archive"
  echo "Snapshot rejected: runtime cache or disposable test data was included." >&2
  exit 1
fi
if tar -tzf "$archive" | grep -Eq '(^|/)\.DS_Store$|(^|/)\._[^/]+$'; then
  rm -f "$archive"
  echo "Snapshot rejected: macOS metadata files were included." >&2
  exit 1
fi

(cd "$(dirname "$archive")" && write_sha256 "$(basename "$archive")" > "$(basename "$archive").sha256")
chmod 600 "${archive}.sha256"

echo "Release snapshot created: $archive"
