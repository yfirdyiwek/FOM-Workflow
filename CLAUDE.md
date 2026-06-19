# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A plain PHP/MySQL workflow portal for the *From Oblivion to Memory (FOM)* organization, deployed on Reclaim Shared Hosting. There is no build system, no Composer, and no automated test suite — just PHP files served directly by Apache.

## Deployment

The live app lives at `https://obliviontomemory.org/workflow_portal`. Deploying means FTP-uploading files under `workflow_portal/workflow_portal/` to the server. Database changes are applied manually via phpMyAdmin.

- **Schema**: `schema/schema.sql` — import once to create all tables.
- **Migrations**: `schema/migration_*.sql` — apply in order if upgrading an existing install.
- **Seed data**: `schema/fom_seed_data.sql` — test users/assignments; default password `ChangeMe123!`.

## Configuration

All runtime config lives in `includes/config.php` as PHP constants:

| Constant | Purpose |
|---|---|
| `DB_*` | MySQL connection |
| `CSRF_KEY` | CSRF token salt |
| `SESSION_NAME` | Session cookie name |
| `UPLOAD_DIR` | Absolute path to `uploads/` |
| `ALLOWED_EXTENSIONS` | Comma-separated allowed upload types |

## Architecture

Every page follows the same pattern:

```
require_once 'includes/bootstrap.php';   // hardens session cookie, loads auth
require_login();                          // redirects to login.php if not authed
// optional: require_login() + role check
render_header($title, $subtitle, $navKey);
// ... page HTML / POST handling ...
render_footer();
```

**`includes/` layer** (loaded in order by `bootstrap.php → auth.php → db.php → functions.php`):

- `functions.php` — pure helpers: `e()` (HTML-escape), `redirect()`, `flash()`, `csrf_token()` / `verify_csrf()`, `handle_document_upload()`, status/label helpers.
- `db.php` — singleton `db(): PDO` function; PDO is configured with `ERRMODE_EXCEPTION` and `FETCH_ASSOC`.
- `auth.php` — session management, `current_user()` (cached static), role checks, `activity_log()`.
- `layout.php` — `render_header()` / `render_footer()` emit full HTML shell including sidebar, nav, flash messages, and asset tags.

**`partials/`** — sub-files included by `assignment-detail.php` to keep that page manageable:
- `detail-data.php` — fetches the assignment row and related data
- `detail-tasks.php` — subtask list/form
- `detail-docs-history.php` — documents and activity history
- `detail-modals.php` — modal HTML
- `detail-post-handlers.php` — all POST action handlers for the detail page

**`assets/`** — no preprocessor; CSS is split into `tokens.css → base.css → layout.css → components.css → themes.css` and bundled in `styles.css`. JS is vanilla, split between `config.js` (runtime constants) and `app.js`.

## Role hierarchy

Roles are stored in `users.role_level`. Access checks use functions in `auth.php`:

```
superadmin
sc_admin
cc_admin / fc_admin / ardc_admin   ← committee-specific admins
sc_member
committee_member
read_only
```

`user_is_adminish()` → superadmin or sc_admin.  
`user_is_sc_member()` → any of the above four levels, or SC committee membership.  
`user_is_committee_admin_for($id)` → checks role *and* `user_committee_memberships.is_committee_admin`.

## Committees

Four committees with fixed `short_code` values used throughout the codebase: `SC`, `CC`, `ARDC`, `FC`.

## Security conventions

- All output through `e()` (`htmlspecialchars`).
- All DB queries use PDO prepared statements via `db()->prepare()`.
- Every POST form includes `<?= csrf_token() ?>` and the handler calls `verify_csrf()` before doing anything.
- File uploads go through `handle_document_upload()` which validates extension, size, and `UPLOAD_ERR_OK`.
- Session cookies are `Secure`, `HttpOnly`, `SameSite=Strict` (set in `bootstrap.php`).
