# Family Hub — notes for AI assistants

Private family web app. Laravel 13 API (`routes/api.php`) + Vue 3 SPA (`resources/js`), Sanctum cookie sessions.
See README.md for the module overview, schema and API.

## Conventions
- Every family-owned model uses `App\Models\Concerns\BelongsToFamily` (global scope on `family_id`).
  Child models without `family_id` (checklist items, links, messages) must be reached through their parent
  and checked with `abort_unless($child->parent_id === $parent->id, 404)`.
- New family routes go inside the `family` middleware group; mutating ones inside `can-write`
  (viewers are read-only); admin-only ones under `family-admin`; documents under `two-factor`.
- Notifications go through `App\Services\Notifier` with a channel; only `logistics` is urgent.
- UI text is Russian. Keep tap targets ≥ 48px (`btn-*`, `field` classes in `resources/css/app.css`) and
  always pair icons with a text label or `aria-label`.
- Models use `#[Fillable]` / `#[Hidden]` attributes (Laravel 13 style).

## Commands
- `php artisan test` — feature tests (SQLite in memory).
- `vendor/bin/pint` — code style.
- `npm run build` / `npm run dev` — frontend.
