# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Conventions

### Tech Stack

- **PHP 8.2+** / Symfony 7.2 — backend API in `app/demo-backend-api/`
- PHP dependencies managed via Composer with proxy `127.0.0.1:6244`

### Working Directory

All PHP commands run from `app/demo-backend-api/`. Composer always needs the proxy:

```bash
cd app/demo-backend-api
HTTP_PROXY=http://127.0.0.1:6244 HTTPS_PROXY=http://127.0.0.1:6244 composer ...
```

### Development Commands

```bash
# Start dev server
php -S localhost:8000 -t public/ public/router.php

# Symfony console
php bin/console

# Run tests (PHPUnit)
php bin/phpunit

# Run a single test
php bin/phpunit --filter=TestName
```

### Code Quality

Before committing code, these checks must pass clean (exit code 0). GrumPHP enforces both via pre-commit hook.

```bash
vendor/bin/php-cs-fixer fix --allow-unsupported-php-version true
vendor/bin/phpstan analyze
```

### Architecture

- Standard Symfony 7.2 microkernel (`Kernel` + `MicroKernelTrait`) with `FrameworkBundle` as the only bundle — no Doctrine, no Twig.
- **Routing**: controller attributes (`#[Route]`) — see `config/routes.yaml` which maps `App\Controller\` to `../src/Controller/`.
- **DI**: autowiring and autoconfiguration enabled by default (`config/services.yaml`). Every class under `src/` becomes a service automatically.
- **PHPStan level 6** with Symfony extension (`phpstan/phpstan-symfony`).

## Best Practices

### Code Layering

Follow Symfony's thin-controller / fat-service pattern:

```
src/
  Controller/   — thin layer: parse request, call service, return response
  Service/      — business logic lives here
  Dto/          — data transfer objects for request/response boundaries
  Exception/    — domain-specific exceptions
```

- **Controllers** must not contain business logic. They parse input, delegate to a service, and return a response. If a controller action exceeds ~15 lines, it's doing too much.
- **Services** are where business rules, transformations, and orchestration happen. One service class per logical domain (e.g. `UserService`, `OrderService`).
- **DTOs** (data transfer objects): use `readonly` classes with constructor promotion to define request/response shapes at system boundaries. This keeps internals decoupled from HTTP details.
- **No repository layer** — this project has no Doctrine ORM, so data access belongs in the service or a dedicated client/adapter class if it grows complex.

### PSR Compliance

This project follows PSR-1, PSR-4, and PSR-12. PHP-CS-Fixer enforces these via the `@Symfony` ruleset plus custom rules in `.php-cs-fixer.dist.php`.

- **PSR-1**: Basic coding standard (class names in `StudlyCaps`, methods in `camelCase`, constants in `UPPER_SNAKE_CASE`).
- **PSR-4**: Autoloading — `App\` namespace maps to `src/`, `App\Tests\` maps to `tests/`.
- **PSR-12**: Extended coding style (braces, whitespace, visibility, etc.) — enforced by CS fixer.

### PHP 8.2+ Features to Use

- **Constructor property promotion** — prefer `public function __construct(private FooService $foo) {}` over separate property + assignment.
- **`readonly` classes** — use for DTOs and value objects. A DTO that shouldn't mutate after construction should be `readonly class`.
- **Enums** — use for finite sets of values (statuses, types, roles). Prefer backed enums (`enum Status: string`) when values cross system boundaries.
- **`match` expression** — prefer over `switch` for exhaustive value mapping.
- **Named arguments** — use at call sites only when the parameter meaning is ambiguous from context (e.g. `setUser(name: 'Alice', active: false)`).

### Dependency Injection

- **Constructor injection only** — no setter injection, no `$container->get()`. Every dependency is declared in the constructor.
- **Type-hint interfaces**, not concretions. If a service might have multiple implementations, extract an interface.
- Use `#[AsCommand]` attribute for console commands instead of `$container->register()`.

### API Response Conventions

- All responses return `Symfony\Component\HttpFoundation\JsonResponse`.
- Use appropriate HTTP status codes:
  - `200` — success with body
  - `201` — resource created, include `Location` header
  - `204` — success with no body (delete, no-op update)
  - `400` — invalid input / validation failure
  - `404` — resource not found
  - `422` — unprocessable entity (semantic errors)
  - `500` — unexpected server error
- Error responses follow a consistent shape: `{"error": {"code": "...", "message": "..."}}`.
- Always return JSON — this is an API, never render HTML or redirect.

### Exception Handling

- Throw domain-specific exceptions from services (e.g. `UserNotFoundException`, `InvalidOrderStateException`), not generic `\Exception`.
- Let Symfony's exception listener convert them to proper JSON error responses — don't catch everything in controllers.
- Use `HttpException` subclasses only at the controller level for HTTP-specific failures.

### Testing

- Tests live in `tests/` under `App\Tests\` namespace.
- One test class per source class, suffixed with `Test` (e.g. `src/Service/FooService.php` → `tests/Service/FooServiceTest.php`).
- **Unit tests** for services (fast, no kernel boot). Mock dependencies via `$this->createMock()`.
- **Integration tests** for controllers (boot kernel with `WebTestCase`). Hit endpoints and assert response status + JSON payload.
- Use `php bin/phpunit --filter=TestName` to run a single test.

### General Rules

- **Strict types**: every PHP file starts with `declare(strict_types=1);`. Enforced by CS fixer.
- **No dead code**: unused imports, variables, or methods break CI. Enforced by `no_unused_imports` + PHPStan level 6.
- **Immutability by default**: DTOs and value objects should be `readonly`. Services should be stateless where possible.
- **Composition over inheritance**: prefer injecting collaborators over base classes. Traits are acceptable for cross-cutting concerns (logging, timing).
- **No runtime configuration**: environment-specific values go in `.env` / `.env.dev`, not in code. Access via constructor-injected parameters, never `$_ENV` directly.
- **Never ignore CS/static analysis failures**: commits blocked by GrumPHP mean the code has a real problem — fix it, don't bypass the hook.
