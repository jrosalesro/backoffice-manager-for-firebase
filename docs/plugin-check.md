# Plugin Check validation notes

Run Plugin Check against both supported installation forms:

1. **Git Updater / repository installation.** The repository intentionally includes
   `bin/`, `tests/`, and `.gitignore`. Development-file reports for these paths do
   not describe the production package and are expected in this installation.
2. **Distribution installation.** Build `dist/backoffice-manager-for-firebase.zip`
   with `bin/build-zip.sh`, install that ZIP in a clean WordPress site, and run
   Plugin Check again. The ZIP must not contain `bin/`, `tests/`, `.gitignore`,
   `.git/`, or `dist/`.

## GET-only navigation findings

The production templates use GET parameters only to select and render admin views:

- `page`, `tab`, `uid`, `s`, and `page_token` navigate, filter, or paginate admin
  screens. They are sanitized before use and do not mutate state.
- `bomff_saved`, `bomff_deleted`, `bomff_error`, `bomff_auth_updated`, and
  `bomff_auth_error` display notices after a nonce-protected POST handler redirects.
- `bomff_show_welcome` is navigation state protected by its own nonce.

Nonce warnings for those read-only parameters are justified false positives. All
state-changing admin forms and AJAX requests require capability checks and nonces.
