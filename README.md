<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## School QR Share

Teachers sign in at `/login`, upload a PDF or PNG/JPEG image, and are taken to a page with the public link and a downloadable QR code. Students open the token link without signing in. There is no public registration. Create teacher accounts from an authorized terminal with `php artisan schoolqr:create-teacher`; passwords are entered through hidden console prompts and must be confirmed.

Uploads are validated using server-detected MIME types and lightweight format checks, then streamed to the configured private Laravel disk with generated storage names. The original filename is metadata only. The default limit is 20 MB; configure `DOCUMENT_MAX_UPLOAD_KB` and keep PHP/IIS request limits above the same value. The actual document bytes never enter MariaDB. QR PNGs are generated on demand from the stable public URL and are not stored.

Public token lookup uses the unique `public_token` index. Teacher history filters through the authenticated user's `documents()` relationship, orders by `created_at` and `id` descending, and uses cursor pagination backed by `(user_id, created_at, id)`. Management endpoints load documents through that same owner relationship; sequential IDs are not authorization credentials. The public token grants read access only. A configured `APP_URL` is used when generating QR links, so it must be the public HTTPS origin outside local development.

Files default to `storage/app/private`, outside the web root. Public routes validate the stored MIME type against the file signature, reject paths outside the private `documents/` directory, and stream content in bounded 8 KiB chunks. Single byte-range requests return `206 Partial Content`; unsatisfiable ranges return `416`. Responses use `nosniff`, a sanitized original basename with an extension derived from validated MIME, and never expose the storage path. Replacement stores the new file before a short compare-and-swap metadata update; only after success is the previous file removed. If the update fails, the staged file is removed. Deletion invalidates the public token before cleaning up its stored file. Since SQL and filesystem operations cannot be atomic together, failed cleanup is reported and logged. An administrator should audit generated files under the private `documents/` directory against current `documents.file_path` references and remove only confirmed unreferenced files. No automatic orphan purge is enabled.

### Local Setup

For XAMPP serving this checkout as `/school-qr/public`, set `APP_URL=http://localhost/school-qr/public`. When using `php artisan serve`, set it to `http://127.0.0.1:8000`; this configured origin is embedded in generated QR links.

1. Install PHP 8.2+, Composer dependencies, Node.js, and npm dependencies (`composer install` and `npm install`). Enable PHP `fileinfo`, `gd`, `mbstring`, and `pdo_mysql` for the configured database; `pdo_sqlite` is needed for the isolated test suite. These extensions are enabled in the inspected local PHP runtime.
2. Configure `.env` with the local database, a generated `APP_KEY`, `APP_URL=http://127.0.0.1:8000`, and `APP_DEBUG=true` only locally. `DOCUMENT_STORAGE_DISK=local` and `DOCUMENT_MAX_UPLOAD_KB=20480` are the defaults.
3. Set `DEFAULT_ADMIN_EMAIL` and `DEFAULT_ADMIN_PASSWORD` in the ignored local `.env`, then run `php artisan db:seed`; or visit `/seed-admin` on localhost and submit the CSRF-protected form. The browser route only works when `APP_ENV=local` and the client address is loopback. The seeder creates `admin@gmail.com` by default and never changes an existing account's password. Do not use the requested local sample password in production; configure a unique secret there.
4. Run `npm run build`, then `php artisan serve`. Visit `http://127.0.0.1:8000` and sign in. Run tests with `php artisan test`; PHPUnit pins database tests to isolated in-memory SQLite.

### Windows Server / Plesk Prerequisites

- Configure the IIS site physical path to Laravel's `public` directory, install/enable IIS URL Rewrite, and route non-file/non-directory requests to `public/index.php`. Keep the project root and private storage outside web-accessible paths; do not create a public storage link for document files.
- Set `APP_ENV=production` and `APP_DEBUG=false` before rebuilding Laravel's route/config caches. In the authorized Plesk terminal, from the Laravel project root, run `php artisan optimize:clear`, then `php artisan config:cache` and `php artisan route:cache`. Verify with `php artisan route:list --path=seed-admin`; production must show no matching route. The `/seed-admin` route is registered only when routes are loaded with `APP_ENV=local` and also requires a loopback client.
- Create the production admin only from the authorized Plesk terminal: configure a unique strong `DEFAULT_ADMIN_EMAIL` and `DEFAULT_ADMIN_PASSWORD` in Plesk's protected environment settings, then run `php artisan db:seed --class="Database\Seeders\AdminUserSeeder" --force`. The seeder never overwrites an existing account or password. Do not deploy the local sample password `admin@123#`.
- Use PHP 8.2+ with `fileinfo`, `gd`, `mbstring`, and `pdo_mysql`. Set `upload_max_filesize` to at least `20M`, `post_max_size` above it (for example `24M`), and IIS `maxAllowedContentLength` to at least 25 MiB. Confirm the Plesk PHP handler accepts multipart `PUT` requests or allows the application's POST method-override forms. Ensure IIS/Plesk forwards incoming `Range` headers and does not strip Laravel's `206`, `416`, `Content-Range`, or `Accept-Ranges` response headers for PDF viewing.
- Grant the IIS/PHP application-pool identity read access to application files and write access only to `storage` (including `storage/app/private`) and `bootstrap/cache`. Serve the site over HTTPS; set the public HTTPS `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and a production `APP_KEY`.
- Run `npm run build` before publishing so `public/build/manifest.json` and assets exist. Verify the configured MariaDB connection and apply pending migrations through the normal reviewed release process; this implementation adds no migration.
- Build a lean Windows-compatible deployment archive after building assets with `php scripts/package-production.php`. The archive retains `vendor` and `public/build`, excludes local `.env`, SQLite databases, uploaded files, logs/cache contents, `node_modules`, and tests, and creates empty writable storage/cache directories. It writes `dist/school-qr-production.zip`; `/dist` is ignored by Git. The script requires PHP's `zip` extension.
- Test upload limits and streamed PDF/image delivery through IIS/Plesk itself. PHP temp space, filesystem permissions, disk capacity, download bandwidth, and web-server concurrency remain operational limits outside the metadata database.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
