# Bug Patterns

Recurring bug patterns discovered during fixes. Read this before analyzing a new bug.

## Format
Each entry: `- <date>: <pattern> — <typical root cause> — <fix approach>`

## Entries

- 2026-05-01: PHP built-in server does not route `.json` extension files correctly — MIME type handling issue — `public/router.php` needs explicit handling for `.json` paths before falling through to the default router.
- 2026-05-01: Deletion of entity with children fails silently unless explicitly checked — no cascade validation in service layer — add child-existence check in Service before Delete, with domain-specific exception.
