Finance App - InfinityFree upload package

1. In the InfinityFree control panel, select PHP 8.2 or newer and enable HTTPS
   for the site before launch. Laravel 12 requires PHP 8.2; BCMath is mandatory.
2. Create a MySQL database in the control panel. Copy its exact host, database
   name, and username from the panel; InfinityFree MySQL is not localhost.
3. Extract this ZIP on your computer. Using FTP (for example FileZilla), upload
   the contents at the ZIP root directly into htdocs. Do not upload any local
   .env, backup, SQL dump, or other archive.
4. Before uploading the complete package, you may upload
   tools/check-hosting.php temporarily into htdocs root and open
   https://YOUR-DOMAIN/check-hosting.php. PHP must be at least 8.2 and every
   listed extension must pass. Delete this check file from htdocs afterward.
5. In htdocs/core, rename .env.example to .env and fill in:
   APP_URL, a newly generated APP_KEY, DB_HOST, DB_DATABASE, DB_USERNAME,
   DB_PASSWORD, and real SMTP host/port/user/password/sender values. Quote
   .env values containing spaces or special characters. Keep APP_DEBUG=false.
   Do not copy a local APP_KEY or credentials. On your computer, generate a fresh key with
   php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;" and paste
   it only into the server's .env. Enable SESSION_SECURE_COOKIE=true only
   after HTTPS is working.
6. Set core/storage, core/bootstrap/cache, and htdocs/storage writable using
   the FTP client's permissions tool. Prefer the host's normal writable
   directory permissions; do not use 777. Avatar uploads need no symlink:
   PUBLIC_STORAGE_PATH=../storage maps them to htdocs/storage.
7. Fresh database only: take no action to import an old/local SQLite database.
   Temporarily add ALLOW_WEB_MIGRATIONS=true and a strong random hex
   MIGRATION_WEB_TOKEN to core/.env. Upload tools/run-migrations-once.php from
   the extracted package temporarily into htdocs root. Open
   https://YOUR-DOMAIN/run-migrations-once.php, enter the token, and run the
   pending migrations. Delete the runner from htdocs and remove both temporary
   environment values immediately after success. Generate the token on your
   computer with php -r "echo bin2hex(random_bytes(32)), PHP_EOL;".
8. Existing database only: export a backup from phpMyAdmin before any schema
   change. Verify that it is this app's MySQL database and inspect its migrations
   table. Do not import a SQLite file or run pending migrations blindly. Import
   only a matching MySQL export into the intended database via phpMyAdmin, then
   run reviewed pending migrations.
9. Test email OTP with your SMTP provider, avatar upload and refresh, transfers,
   and login. Google sign-in needs real client values and the exact callback
   https://YOUR-DOMAIN/auth/google/callback added as an Authorized redirect URI
   in Google Cloud Console. Do not enable signup until OTP delivery works.

The application source and .env are kept under core/ and denied by .htaccess.
Uploaded avatars are stored under htdocs/storage without requiring a symlink.
Do not place backups or database exports in htdocs.

SMTP is required for email OTP. InfinityFree network policy and the selected
SMTP provider may prevent outbound SMTP; verify delivery before opening signup.
Google login also remains unavailable until real OAuth client values and the
exact production callback URI are configured.

The package includes vendor dependencies installed with --no-dev and the built
Vite assets. No SSH, Composer, npm, Artisan shell, migration import, or symlink
is required on the hosting account. The included tools/ directory is blocked
from web requests; copy a tool to htdocs root only while using it, then delete it.
