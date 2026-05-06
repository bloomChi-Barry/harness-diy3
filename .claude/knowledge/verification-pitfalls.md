# Verification Pitfalls

Lessons learned about verification command design. Read this before designing verification commands for new tasks.

## Format
Each entry: `- <date>: <problem> — wrong → correct`

## Entries

- 2026-05-01: Symfony config alias — `debug:config framework.validation` does not exist as an alias; use `debug:config framework` to check all framework config. Always verify aliases with `bin/console debug:config --list`.
- 2026-05-01: API routes registered via controller attributes may differ from what names suggest — always check with `bin/console debug:router` before writing curl verification commands.
- 2026-05-01: `set -e` in verification scripts causes the first failure to abort silently. For aggregate pass/fail reporting, use `set -o pipefail` without `set -e`, and check exit codes individually.
- 2026-05-01: `grep -q` returns 1 when no match — in a `set -e` script this will abort. Use `grep -q pattern || true` or avoid `set -e` when using grep for checks.
- 2026-05-01: `php bin/console doctrine:schema:validate` requires the database to exist first. Verification commands that depend on DB state must include `doctrine:database:create` or equivalent pre-check.
- 2026-05-06: `bash script.sh 2>&1 | tee log; VERIFY_EXIT=$?` captures tee's exit code (always 0), not the script's — use `VERIFY_EXIT=${PIPESTATUS[0]}` instead. Same bug affects feature-implement, bug-fixer, and refactor-expert verification templates. Fixed by extracting shared conventions to `.claude/knowledge/verification-conventions.md`.
- 2026-05-06: `set -e` without `pipefail` in task verification scripts — a command in the middle of a pipeline that fails won't abort. Task-level scripts should use `set -eo pipefail`. Fixed in feature-implement and bug-fixer templates.
