# Codebase Reality

Things that are true about this codebase but may not be obvious from CLAUDE.md alone. Read this before making architectural decisions.

## Format
Each entry: `- <date>: <observation> — <implication for new work>`

## Entries

- 2026-05-01: Despite being described as "microkernel" in early docs, the project uses Doctrine ORM with full Entity/Repository layers — New features should use Doctrine entities and Repository pattern, not raw SQL.
- 2026-05-01: Repository layer uses interface + implementation pattern (`CategoryRepositoryInterface` + `CategoryRepository`) — Always code against the interface in services, keep Doctrine implementation details in the repository class.
- 2026-05-01: Controllers are thin (CategoryController is ~30 lines per action) but Services can be substantial (CategoryService handles create/update/delete/move/tree operations with validation) — Don't split Services prematurely; one Service per domain aggregate is fine.
- 2026-05-01: NelmioApiDocBundle is installed for Swagger/OpenAPI — New API endpoints should add OpenAPI attributes for auto-documentation.
