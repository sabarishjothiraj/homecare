# Operations

## Queue worker (required)

Contract PDFs are rendered off the request by a queued job. A cold mPDF render
of the Tamil agreement takes roughly six seconds, and it used to run inside the
database transaction that creates the contract — holding that row's lock for
the whole render.

`QUEUE_CONNECTION=database`, so a worker must be running:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Keep it alive with supervisor (Linux) or Task Scheduler / NSSM (Windows).
Example supervisor program:

```ini
[program:homecare-worker]
command=php /path/to/homecare/artisan queue:work --tries=3 --timeout=120 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/homecare-worker.log
stopwaitsecs=150
```

`stopwaitsecs` must exceed the job timeout (120s) so a render is never killed
half-written.

### If no worker is running

Nothing breaks. `ContractService::ensurePdf()` renders on demand the first time
a PDF is downloaded, viewed or printed, so the queue is a warm-up rather than a
precondition. You lose the speed, not the feature.

### After deploying

```bash
php artisan queue:restart
```

Workers hold code in memory; without this they keep running the previous
release.

### Failure handling

- 3 attempts, backing off 10s / 30s / 60s.
- A contract deleted between dispatch and processing discards the job rather
  than failing it (`$deleteWhenMissingModels`).
- Exhausted retries log an error naming the contract. The document is still
  recoverable — the next view renders it.
- `queue:prune-failed --hours=168` runs daily from the scheduler.

Inspect failures with `php artisan queue:failed`, retry with
`php artisan queue:retry all`.

## Scheduler

```
* * * * * cd /path/to/homecare && php artisan schedule:run >> /dev/null 2>&1
```

Drives daily contract status updates, expiry notifications, and failed-job
pruning. Schedule times follow the app timezone (IST), so `dailyAt('00:00')`
is midnight in India.

## Timezone (one-time switch from UTC to IST)

The app now runs on `Asia/Kolkata` (`APP_TIMEZONE`, default in
`config/app.php`). Before this it ran on UTC, so every stored date-time was a
UTC wall-clock value. The migration
`2026_09_23_000100_shift_stored_timestamps_to_ist` converts them once:

- Every `TIMESTAMP` and `DATETIME` column that the app stamps itself moves
  forward 5h30m. This covers `created_at`/`updated_at`/`deleted_at` and the
  `now()` stamps (paid, closed, responded, reviewed, read, OTP and token
  times). A payment captured at 01:30 IST on 1 Oct was stored as
  `2026-09-30 20:00:00` and now reads `2026-10-01 01:30:00`, so it lands in
  October's report.
- Times an admin types in are already IST and are not shifted. Today that is
  only `blogs.published_at`, listed in the migration's `USER_ENTERED_COLUMNS`.
  Add a column there if it is filled from a date-and-time form field.
- `DATE` columns (contract and assignment dates, attendance days, payroll
  periods, salary effective dates) are calendar days and are not touched.
- `migrations` and `jobs` are skipped (`jobs` stores unix epochs). NULLs stay
  NULL.
- It runs in a single transaction: either every table is converted or none is.

### Deploy order

Run these steps in order. New code writing IST values into unconverted data, or the
migration running against a cached UTC config, leaves the data wrong by 5h30m.

1. Back up the database.
2. `php artisan down`. This stops web traffic. Stop the queue worker too
   (`supervisorctl stop homecare-worker`).
3. Deploy the code. If you use `php artisan config:cache`, rebuild it now. A
   config cached by the old release still says UTC, and the migration only
   converts when the configured timezone is IST.
4. `php artisan migrate --force`
5. `php artisan queue:restart`, start the worker, then `php artisan up`.

### Rules

- Do not set a `timezone` on the database connection in `config/database.php`,
  and do not change the MySQL server's time zone. MySQL converts `TIMESTAMP`
  values through the session time zone, so either change shifts every stored
  value a second time.
- Do not change `APP_TIMEZONE` later without converting the data again.
- If `APP_TIMEZONE` was set to something other than IST when the migration
  ran, it recorded itself as run but changed nothing. To convert later, set
  `APP_TIMEZONE=Asia/Kolkata`, run
  `DELETE FROM migrations WHERE migration = '2026_09_23_000100_shift_stored_timestamps_to_ist';`
  and run `php artisan migrate --force` again, using the deploy order above.
- To undo the conversion, roll that migration back *while `APP_TIMEZONE` is
  still IST*, then switch the timezone back.

### Known side effects

- **Blog publish times.** A time typed into the post form is kept as typed,
  so a post scheduled for 10:00 now goes live at 10:00 IST. Before the switch
  it went live 5h30m late. A post saved with the publish time left blank was
  stamped with the UTC time instead. It is left alone too, so it shows 5h30m
  earlier than it really went out. The date can be a day early for posts
  published before 05:30 IST. This affects display and ordering only, because
  those posts are already live.
- **Date-only fields** (payment and payout "paid on") were stored at 00:00 and
  move to 05:30 on the same day. The day, which is what reports filter on,
  does not change.

### If `migrate` stops on duplicate payslips

`2026_09_23_000501_add_unique_period_to_employee_payouts_table` allows only one
live (not cancelled) payslip per employee and period. If older data already
has two, the migration stops before changing anything and its error lists the
payout ids in each group. Choosing which one to keep is a business decision,
so the migration does not choose:

1. In each group, keep the correct payslip (the one actually paid out, if any)
   and set `status = 'cancelled'` on the others.
2. Run `php artisan migrate --force` again.

Keep the site in maintenance mode until the migration goes through. The
timestamp conversion runs earlier in the same `migrate` and is recorded when it
finishes, so running `migrate` again does not shift timestamps a second time.

## Employee files: one-time move to the private disk

Employee photos and documents used to live on the public disk, where anyone
with the URL could fetch them. New uploads go to the private disk. Move the
legacy files once, after deploying:

```bash
php artisan employees:move-files-private --dry-run   # list what would move
php artisan employees:move-files-private
```

It is safe to run while the site is up, and safe to re-run: files already on
the private disk are skipped. Until it has run, the app falls back to the
public copy, so nothing breaks in the meantime.

## Contract PDFs: private storage

Contract agreements contain patient and employee details, so they are now
written to the private disk (`storage/app/contracts`) and served only through
the authenticated download / view / print routes. Earlier releases wrote them
to `storage/app/public/contracts`, which is reachable by URL.

After deploying, move the old files once:

```bash
php artisan contracts:secure-pdfs
```

The scheduler also runs this nightly as a safety net, and any old file is moved
the first time its contract's PDF is opened. File names now include a keyed
hash, so they cannot be guessed from the contract number.

## Login OTP delivery

Every staff login, on the admin panel (`/admin/login`) and the API
(`/api/login`), sends a code to one owner-controlled destination, configured in
**Admin → Settings → Login OTP**. The login completes only after that code is
entered. Super admins are the owner and sign in with their password alone. If
the approver address is blank, staff logins are refused.

Changing the approver address needs the `otp.config.update` permission;
`settings.manage` alone shows it read-only.

Password guessing is capped per account at 20 attempts an hour across all
IPs, on both login routes. Someone who knows an email can use this to lock that
account out for up to an hour; that is the price of stopping spread-out
guessing. Clear it early with `php artisan cache:clear`.

API tokens expire after `SANCTUM_EXPIRATION` minutes (default 1440, one day).
Tokens are revoked when an account is deactivated, deleted, has its role
changed or its password reset. Resetting a password also signs the account out
of its other panel sessions.

Delivery is two layers:

| Layer | Setting | Values |
|---|---|---|
| Channel | `OTP_CHANNEL` | `mail` (default), `sms`, `log` |
| Transport | `SMS_DRIVER` | `http`, `log` — only used when the channel is `sms` |

`log` appears at both layers and means different things: `OTP_CHANNEL=log`
skips delivery entirely, while `OTP_CHANNEL=sms` with `SMS_DRIVER=log`
exercises the SMS channel without a provider account.

For the `http` transport, provider field names are configuration rather than
code — see `config/sms.php`.

**Before going live:** set real SMTP credentials and a `MAIL_FROM_ADDRESS` on a
domain you control, and set the approver address in Settings. With
`MAIL_MAILER=log` no OTP is actually sent and nobody can complete a login.
