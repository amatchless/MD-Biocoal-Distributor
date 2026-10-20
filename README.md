# MD Biocoals · Dealer & Distributor Onboarding

A mobile-first Laravel app for MD Biocoals field staff to onboard dealers and distributors on their phone or tablet, with GPS capture at the premises.

## What it does

**Roles** (Spatie Laravel-Permission): `super-admin` → `manager` → `employee`.

| Role | Can |
|---|---|
| Employee | Onboard dealers/distributors; see and edit only their own (while *Submitted* or *Needs info*) |
| Manager | Everything above for their own onboardings, plus see every onboarding by employees reporting to them; review (Under review / Needs info / Approve / Reject); team performance |
| Super Admin | See everything; create users, set roles, assign employees to managers, deactivate accounts, reset passwords |

**Onboarding form (7 steps)**
1. Business: dealer or distributor, legal and trade name, entity type, year, incorporation date, products, turnover, storage, premises
2. Contact: owner/contact, mobile, alternate, email, date of birth (for greetings)
3. Address & GPS: full address with state list and PIN, plus **live GPS capture** (high-accuracy, waits up to 25 s for a ±25 m fix, shows accuracy and a map)
4. GST, PAN & licences: GSTIN (validated, and checked against the PAN), PAN, **last 4 digits of Aadhaar only**, registration no., pesticide and fertilizer licence numbers with expiry, optional bank details (account number encrypted at rest)
5. References: minimum 2 customer + 2 supplier
6. Photos & documents: on-site camera photos (shop front is required) are resized and **stamped with GPS coordinates, time and employee name**; each photo's own GPS is stored too. Document copies (GST, PAN, masked Aadhaar, licences, address proof, cheque…) as photo or PDF
7. Visit notes + DPDP consent checkbox

Other details: duplicate GSTIN/PAN warning across all employees, local draft autosave on the phone (survives signal drops; photos excepted), upload progress bar, reference numbers like `MDB-DLR-2026-00012`, full history per record, CSV export (respects filters and role), onboarding map, and licence renewal alerts (expired or due within 60 days).

## Run it locally

Needs PHP 8.3+, Composer and Node 20+. On a Mac the easiest route is [Laravel Herd](https://herd.laravel.com) (includes PHP and Composer).

```bash
composer run setup          # installs, creates .env + SQLite DB, migrates, seeds roles + Super Admin, builds assets
php artisan serve           # http://localhost:8000
```

Log in as `admin@mdbiocoals.com` / `ChangeMe@123` (set `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` before seeding to change it), then add managers and employees under **Users**.

To try it with a demo team and sample dealers:

```bash
php artisan db:seed --class=DemoSeeder
```

Demo logins (password `Password@123`): `manager.east@mdbiocoals.com`, `manager.north@mdbiocoals.com`, `sourav@mdbiocoals.com`, `priya@mdbiocoals.com`, `vikas@mdbiocoals.com`.

Run the tests with `php artisan test`.

## Using it on phones (important)

Browsers only share GPS with sites served over **HTTPS** (or `localhost`). Opening `http://192.168.x.x:8000` from a phone will block location. Options:
- Herd: `herd secure` gives the site an https://…test address on your Mac, or use Herd's / ngrok's share tunnel for a public https URL.
- Deploy to a server with SSL (Laravel Forge, Ploi, or any VPS with Let's Encrypt).

The app is installable on the home screen (Add to Home Screen) and works as a regular web app; it is not an offline app, but drafts are kept on the device until submitted.

## MySQL instead of SQLite

In `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=md_onboarding
DB_USERNAME=...
DB_PASSWORD=...
```
then `php artisan migrate --seed`.

## Where things are

- `app/Models/Dealer.php`: fields, statuses, lists (states, entity types, products), visibility scope
- `app/Http/Requests/DealerRequest.php`: all validation rules (GSTIN/PAN/IFSC/mobile/PIN, 2+2 references, GPS, consent)
- `app/Policies/DealerPolicy.php`: who can view / edit / review
- `app/Http/Controllers/DealerController.php`, `DashboardController.php`, `UserController.php`
- `resources/views/dealers/form.blade.php` + `resources/js/onboarding.js`: the mobile form, GPS and photo stamping
- Uploaded files are stored privately in `storage/app/private/dealers/{id}` and only served to users allowed to see that dealer.

## Compliance notes

- Aadhaar: only the last 4 digits are stored, and staff are told to upload masked copies only (UIDAI rules).
- Consent: onboarding cannot be submitted without the DPDP consent checkbox; consent time, IP and device are recorded.
- Retention periods, hosting location and backups still need deciding (see the compliance requirements notes).
