# Security policy

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report vulnerabilities privately through GitHub: go to the repository's **Security** tab, then **Report a vulnerability**. Include steps to reproduce, the affected version or commit, and the impact you expect.

You'll get an acknowledgement within a few days. Once a fix is available, we'll publish an advisory and credit you, unless you'd rather stay anonymous.

## Supported versions

Only the latest commit on `master` receives security fixes.

## Deployment notes

- The demo accounts (`*@example.com` / `password`) are seeded only outside production (`APP_ENV=production` skips `DemoSeeder`). Never run the demo seeder on a real installation.
- Set `APP_DEBUG=false` and a unique `APP_KEY` in production.
- Attachments are stored on the private `local` disk and served only through an authorization check. Don't expose `storage/app/private` through the web server.
