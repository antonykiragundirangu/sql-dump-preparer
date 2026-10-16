# SQL Dump Preparer

Laravel 10 tool that cleans and splits MySQL dumps into a downloadable ZIP.

It removes `COLLATE=utf8mb4_0900_ai_ci` and mysqldump session `SET` directives, prefixes tables with `wpkm_`, resets table `AUTO_INCREMENT` values to `1`, and isolates `wpkm_refs`.

## Run locally

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan serve
```

Open `http://127.0.0.1:8000`. For large dumps, increase PHP's `upload_max_filesize` and `post_max_size`; the application limit is 512 MB.

## Later: direct database import

Keep transformation and import as separate steps. Add a confirmation screen, then queue an import job that executes the ordinary-tables file first and the refs file second. Use a dedicated database user, encrypted environment credentials, backups, progress logs, and a single-import lock. Avoid running a large import inside the browser request.
