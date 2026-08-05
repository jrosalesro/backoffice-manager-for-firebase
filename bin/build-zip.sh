#!/usr/bin/env bash
set -euo pipefail

plugin_slug="backoffice-manager-for-firebase"
repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output_directory="${1:-${repository_root}/dist}"
zip_path="${output_directory}/${plugin_slug}.zip"
staging_directory="$(mktemp -d)"

cleanup() {
    rm -rf "${staging_directory}"
}
trap cleanup EXIT

# Runtime files required by the WordPress plugin. Keep this list explicit so
# development-only files are never copied into the WordPress.org package.
runtime_paths=(
    assets
    includes
    languages
    pages
    backoffice-manager-for-firebase.php
    bomff-scripts.js
    bomff-settings.php
    readme.txt
)

for runtime_path in "${runtime_paths[@]}"; do
    if [[ ! -e "${repository_root}/${runtime_path}" ]]; then
        printf 'Error: required runtime path is missing: %s\n' "${runtime_path}" >&2
        exit 1
    fi
done

mkdir -p "${output_directory}" "${staging_directory}/${plugin_slug}"
rm -f "${zip_path}"

tar -c -C "${repository_root}" -- "${runtime_paths[@]}" \
    | tar -x -C "${staging_directory}/${plugin_slug}"

(
    cd "${staging_directory}"
    zip -qr "${zip_path}" "${plugin_slug}"
)

zip_listing="$(unzip -Z1 "${zip_path}")"

# WordPress.org expects one top-level plugin folder and no files beside it.
if printf '%s\n' "${zip_listing}" | grep -Ev "^${plugin_slug}(/|$)" | grep -q .; then
    printf 'Error: ZIP contains an entry outside %s/.\n' "${plugin_slug}" >&2
    exit 1
fi

# Defense-in-depth: fail the build if any development-only artifact is present.
forbidden_pattern="^${plugin_slug}/(\.git/|\.github/|tests/|bin/|dist/|\.vscode/|\.idea/|node_modules/|logs/|docs/|phpunit\.xml([^/]*)$|composer\.(json|lock)$|package(-lock)?\.json$|pnpm-lock\.yaml$|yarn\.lock$|\.gitignore$|\.editorconfig$|.*(~|\.tmp|\.swp)$)"
if printf '%s\n' "${zip_listing}" | grep -Eq "${forbidden_pattern}"; then
    printf 'Error: ZIP contains a development-only file or folder.\n' >&2
    printf '%s\n' "${zip_listing}" | grep -E "${forbidden_pattern}" >&2
    exit 1
fi

root_entries="$(printf '%s\n' "${zip_listing}" | awk -F/ 'NF > 1 {print $1}' | sort -u)"
if [[ "${root_entries}" != "${plugin_slug}" ]]; then
    printf 'Error: ZIP does not contain exactly one root plugin folder.\n' >&2
    exit 1
fi

file_count="$(printf '%s\n' "${zip_listing}" | grep -vc '/$')"
printf 'Created %s\n' "${zip_path}"
printf 'Verified ZIP contents (%s files, one root directory: %s/).\n' "${file_count}" "${plugin_slug}"
