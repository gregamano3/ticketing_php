# Contributing

Thanks for helping improve Helpdesk! Bug reports, feature ideas and pull requests are all welcome.

## Development setup

You need Docker. Everything else runs in containers through [Laravel Sail](https://laravel.com/docs/sail). Follow the *Getting started* section of the [README](README.md), then log in with one of the demo accounts.

## Workflow

1. **Open an issue first** for anything bigger than a small fix, so we can agree on the approach.
2. Branch from `master`: `feature/<issue>-<short-name>` or `fix/<issue>-<short-name>`.
3. Keep pull requests focused, and reference the issue (`Closes #123`).
4. CI must be green before merging. It runs Pint, PHPUnit and the Playwright browser suite.

## Tests and code style

```bash
./vendor/bin/sail pint             # format PHP (CI runs `pint --test`)
./vendor/bin/sail artisan test     # PHPUnit feature tests against PostgreSQL
bin/e2e                            # Playwright browser tests (reseeds the dev database!)
```

- New behaviour needs a feature test. User-facing flows should also get a Playwright test in `tests/e2e/`.
- Business rules belong in `app/Services`, and authorization in `app/Policies`. Keep controllers thin.
- Follow the existing patterns. Admin-managed lookup tables, for example, are defined in `app/Support/Lookups.php`, not in new controllers.
- If you change the UI, regenerate the README screenshots with `SCREENSHOTS=1 bin/e2e -g @screenshots`.

## Commit messages

Use the imperative mood ("Add triage queue", not "Added…"), with a short summary line and a body that explains *why* when that isn't obvious.

## Code of conduct

This project follows the [Code of Conduct](CODE_OF_CONDUCT.md). By participating you agree to uphold it.
