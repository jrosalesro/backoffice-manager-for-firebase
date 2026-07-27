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

tar -c -C "${repository_root}" -- \
    assets \
    includes \
    languages \
    pages \
    backoffice-manager-for-firebase.php \
    bomff-scripts.js \
    bomff-settings.php \
    readme.txt \
    | tar -x -C "${staging_directory}/${plugin_slug}"

(
    cd "${staging_directory}"
    zip -qr "${output_directory}/${plugin_slug}.zip" "${plugin_slug}"
)

printf 'Created %s\n' "${output_directory}/${plugin_slug}.zip"

zip_listing="$(unzip -Z1 "${output_directory}/${plugin_slug}.zip")"
if printf '%s\n' "${zip_listing}" | grep -Eq '(^|/)(bin|tests|dist|\.git)(/|$)|(^|/)\.gitignore$|\.json$|(~|\.tmp|\.swp)$'; then
    printf 'Error: ZIP contains a development-only file.\n' >&2
    exit 1
fi
if printf '%s\n' "${zip_listing}" | grep -Ev "^${plugin_slug}(/|$)" | grep -q .; then
    printf 'Error: ZIP contains an entry outside %s/.\n' "${plugin_slug}" >&2
    exit 1
fi
printf 'Verified ZIP contents (%s files, one root directory).\n' "$(printf '%s\n' "${zip_listing}" | grep -vc '/$')"
