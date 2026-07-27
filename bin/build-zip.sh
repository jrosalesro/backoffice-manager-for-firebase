#!/usr/bin/env bash
set -euo pipefail

plugin_slug="backoffice-manager-for-firebase"
repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output_directory="${1:-${repository_root}/dist}"
staging_directory="$(mktemp -d)"

cleanup() {
    rm -rf "${staging_directory}"
}
trap cleanup EXIT

mkdir -p "${output_directory}" "${staging_directory}/${plugin_slug}"

git -C "${repository_root}" archive --format=tar HEAD \
    | tar -x -C "${staging_directory}/${plugin_slug}"

rm -rf \
    "${staging_directory}/${plugin_slug}/.gitignore" \
    "${staging_directory}/${plugin_slug}/bin" \
    "${staging_directory}/${plugin_slug}/dist"

(
    cd "${staging_directory}"
    zip -qr "${output_directory}/${plugin_slug}.zip" "${plugin_slug}"
)

printf 'Created %s\n' "${output_directory}/${plugin_slug}.zip"
