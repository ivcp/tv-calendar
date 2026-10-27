# AGENTS.md

TV Show Calendar: PHP 8.3 / Slim 4 / Doctrine ORM + Postgres backend, Twig + TypeScript (webpack Encore) frontend. Show/episode data is synced from the TVMaze API.

## Commands

Run PHP tooling inside the `app` container. The host PHP (8.5) differs from the image (8.3), and tests resolve `db-test`/`ntfy-test` by Docker hostname.

- Start stack: `docker compose up -d` (add `--profile withcron` for the cron container)
- Shell: `docker exec -it tv-calendar-app bash`
- `./tv-calendar` uses `docker exec -it`, so it fails in non-TTY agent shells — prefer `docker exec tv-calendar-app <cmd>`
- Unit tests (no DB, ~5s): `docker exec tv-calendar-app ./vendor/bin/phpunit tests/Unit`
- Single test: `docker exec tv-calendar-app ./vendor/bin/phpunit tests/Unit/ConfigTest.php --filter testItGetsTheDefaultValueWhenSettingIsNotFound`
- Integration tests (needs `db-test` + `ntfy-test` up): `docker exec tv-calendar-app ./vendor/bin/phpunit tests/Integration` — run from repo root; tests run `migrations:migrate` and some drop the test schema
- Lint / style: `docker exec tv-calendar-app composer lint` and `composer phpcs` (PSR-1 only). CI also runs `composer phpcbf`, which rewrites files.
- Doctrine / migrations: `docker exec tv-calendar-app ./bin/doctrine <command>`. After changing entity mappings run `migrations:diff` and commit the generated file in `migrations/`.
- Frontend: `docker compose run --rm node npm run dev` (or `npm run build`). `public/build` is gitignored and required — Twig's Encore tags fail to render without it.
- Dev services: app `:8000`, Adminer `:8001`, Mailhog `:8025` (`localhost:1025` SMTP).

## Wiring

- Web entrypoint `public/index.php` → `bootstrap.php` → `configs/container/container.php`. Routes: `configs/routes/web.php`. Middleware: `configs/middleware.php` (order matters; body parsing enables `_METHOD` overrides).
- PHP-DI autowires most classes; explicit bindings (Twig, EntityManager, Guzzle, Mailer, Session, CSRF) live in `configs/container/container_bindings.php`.
- Settings go through `App\Config` and `configs/app.php`, which reads `$_ENV` loaded by immutable Dotenv. Adding an env var requires a `configs/app.php` entry; `getenv()` will not see it.
- Non-web entrypoints: `bin/doctrine`, plus cron scripts in `scripts/` (schedule in `docker/cron/crontab`): daily TVMaze sync, hourly notification scheduling, per-minute email/Discord sending.
- XHR is detected only via `X-Requested-With: XMLHttpRequest` and switches controllers between JSON and Twig responses. The TS layer (`resources/ts/utils/ajax.ts`) sends PATCH/DELETE as POST with `_METHOD` and injects CSRF fields from the `#csrfName` / `#csrfValue` meta tags.
- Domain: `UpdateService` pulls changed shows/episodes from TVMaze; `Show` 1—N `Episode`; subscriptions use the `UserShows` join entity (carries `notifications_enabled`). Guests keep show IDs in localStorage and send them as the `shows` query param to the calendar endpoint.
- Notifications: ntfy messages are scheduled through ntfy's HTTP API (`NtfyService` with an admin token); Discord messages are queued in the `notifications` table and delivered by the cron sender. Emails are queued in `emails` and sent by `scripts/sendEmails.php`.
- Tests: `tests/Unit` mocks Guzzle with no DB (TvMaze/notification scheduling). `tests/Integration` needs real Postgres/ntfy and uses JSON fixtures in `tests/Integration/`; `phpunit.xml` force-overrides DB/ntfy env for the test containers.

## Gotchas

- `.env` is gitignored and blocked from reading by `opencode.jsonc`; use `.env.example`. `app:generate-key` rewrites `.env` and invalidates signed URLs.
- Prod images exclude `.env`, `.git` (see `.dockerignore`); `prod.compose.yml` injects `.env` at runtime via `env_file`, and nginx mounts only `./public`. `app:generate-key` prints the key instead of writing when `.env` is absent.
- `vendor/`, `node_modules/`, `storage/`, `public/build/`, `sessions/` are gitignored. A fresh clone needs composer/npm install, a build, and a writable `storage/`.
- Code uses `declare(strict_types=1)` and Doctrine attribute mapping only (no XML/YAML mappings).
- `migrations:diff` treats anything that exists only as raw SQL as an orphan and emits a `DROP` for it: the `migrations` metadata table, `rate_limits` (created in `Version20261027091026`, intentionally unmapped), and the hand-written indexes `idx_episodes_airstamp_showid`, `idx_shows_weight_updated_id`, `idx_shows_sort_by_new`. Check with `./bin/doctrine orm:schema-tool:update --dump-sql` and strip those statements from generated migrations. Do not hide `migrations` behind a DBAL `schema_filter`: `tablesExist()` reads the filtered table list, so the migrations library would conclude its metadata table is missing and try to create it again. Filtering only `rate_limits`, or running `migrations:diff --filter-expression='~^(?!rate_limits$)~'`, is safe.
- New frontend entry: add `addEntry()` in `webpack.config.js` and a `resources/ts/<entry>/index.ts`; otherwise the bundle is never built.
- Dev mounts the repo into the container, so PHP/Twig/TS source changes need no restart (rebuild only for `docker/*.ini` changes). Prod deploy is `prod.build.sh` (`prod.compose.yml` + `prod.dockerfile`), which also generates proxies and runs migrations.
- CI (`.github/workflows/php.yml`) only validates/lints on PRs to `master`; the tests workflow is a placeholder, so run the suites yourself.
