# AGENTS.md

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran. Installed: Doctrine ORM +
Migrations, Serializer, Validator, Security, Workflow, Rate Limiter, LexikJWT,
gesdinet refresh-token. Not installed: Twig, API Platform, Messenger, Lock,
Translation, web profiler.

## Project decisions

- Persistence: Doctrine ORM + Migrations on MySQL 8.4.
- Interface: JSON API only (Serializer + Validator), no Twig, no API Platform.
- Auth: SecurityBundle + LexikJWT (access token) + gesdinet refresh token in an HttpOnly cookie.
- API contract and domain model: `docs/backend-requirements.md`; project overview: `CLAUDE.md`.

## Adding features: Flex, not hand-wiring

Install new capabilities with `composer require <package>` (e.g. `symfony/lock`,
`symfony/messenger`, `orm-pack`) and let the Flex recipe register the bundle and
generate its config. Don't hand-edit `config/bundles.php` or hand-write a bundle's
base config; that's what the recipe is for. Don't skip a good-fit component just
because it isn't installed yet; installing it is one command.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `App\Controller\ApiController`, stay thin, and delegate to services.
- Access control lives in `security.yaml` (`access_control`) and voters
  (`denyAccessUnlessGranted`); don't repeat it with `#[IsGranted]`.
- Errors are `application/problem+json` with Russian texts; give every `#[Assert]`
  a Russian `message:` (Translation isn't installed).
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, `composer require` them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
- For mutual exclusion inside a request, lock the DB row in a transaction
  (`LockMode::PESSIMISTIC_WRITE`); for anything wider, `composer require symfony/lock`.
  A hand-built flag or lock file is usually wrong under concurrency.

## Everyday workflow

- Everything runs in Docker via `make` (see `README.md`): `make start`, then
  `docker compose exec backend bin/console ...` or `make console c="..."`.
- When something fails, read `var/log/dev.log` before changing code.
- Schema changes go through migrations (`make db-migration`, then `make db-migrate`),
  never `doctrine:schema:update` or hand-written SQL.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Testing

Functional/HTTP tests extend `App\Tests\Functional\ApiTestCase` (`WebTestCase` with JWT
and problem+json helpers); service-level tests extend `KernelTestCase`; pure units
live in `tests/Unit`. `make test` runs PHPUnit and needs `make test-db` first;
`php bin/phpunit tests/Unit` works without a database. A feature isn't done until
it has a test that exercises it the way a caller would, an HTTP request for a
controller or a service call for a service, not just "it didn't throw."

## Code style

The `@Symfony` php-cs-fixer ruleset: `make cs-fix` to fix, `make lint` to check.
Keep comments short and avoid one-off or duplicated code (`.claude/skills/code-style`).

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `make lint` (`lint:container`, `lint:yaml`, Doctrine mapping, php-cs-fixer):
  validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).
