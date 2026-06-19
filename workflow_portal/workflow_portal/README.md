# Workflow Portal PHP Starter (First Working Slice)

This package turns the design work into a real PHP/MySQL starter suitable for Reclaim Shared Hosting.

## What works now

This first slice is intentionally small and focused:

- log in
- create an official assignment
- assign it to a committee
- assign a lead person from that committee
- show the assignment on the dashboard
- open the assignment detail page
- record basic activity log entries

That gives you a real working core without trying to build the full workflow all at once.

## Included files

- `index.php` — redirects to login, setup, or dashboard
- `login.php` — working login page
- `logout.php` — logout action
- `setup-admin.php` — first-time admin creation page
- `dashboard.php` — organization-wide dashboard
- `sc-dashboard.php` — SC-only dashboard
- `committee-dashboard.php?code=CC|ARDC|FC` — committee-specific dashboard
- `assignments.php` — list of assignments
- `assignment-create.php` — working assignment creation form
- `assignment-detail.php?id=...` — working assignment detail page
- `documents.php`, `reports.php`, `users.php` — placeholders for next steps
- `activity-log.php` — recent activity log
- `includes/` — config, DB connection, auth/session, layout helpers
- `schema/schema.sql` — database schema to import in phpMyAdmin
- `assets/` — styles, JS, theme support, FOM icon

## Reclaim setup steps

### 1. Create the MySQL database
In cPanel:
- open **MySQL Database Wizard**
- create a database
- create a database user
- grant that user **ALL PRIVILEGES** on the new database

### 2. Edit `includes/config.php`
Fill in:
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `CSRF_KEY` (set this to a long random string)

Usually on Reclaim Shared Hosting, `DB_HOST` stays `localhost`.

### 3. Import the schema
In **phpMyAdmin**:
- select the new database
- import `schema/schema.sql`

This creates committees, users, assignments, activity log, and future-ready tables for subtasks, handoffs, and documents.

### 4. Upload the app
Upload all files to the subdomain or directory where the app should run.

### 5. Create the first admin
Visit:
- `setup-admin.php`

This creates the first **SuperAdmin** account and adds it to all four committees.

### 6. Log in
Visit:
- `login.php`

### 7. Test the first slice
After logging in:
- create an assignment
- assign it to a committee
- choose a lead from that committee
- save it
- confirm it appears on the dashboard
- open the detail page

## Notes about this phase

This build does **not** yet include:
- supporting-member management in the UI
- subtask UI
- handoff UI
- return-for-revision UI
- protected file upload/download
- full user-management UI

Those are the next layers to add once the core assignment loop is working on your hosting.

## Recommended next step after this works

Add the next working slice:

- supporting members
- subtasks under a parent assignment
- then handoffs and return-for-revision

That keeps the build incremental and manageable.


## Optional FOM seed data

After you finish `setup-admin.php`, you can import `schema/fom_seed_data.sql` in phpMyAdmin to add the named seed users, committee memberships, and sample assignments used for testing the portal.

The default password for newly inserted seed users is `ChangeMe123!`.
See `schema/fom_seed_data_README.md` for details.


## Supporting members (v3)

This version adds assignment-level supporting members. Committee admins for the assignment’s committee, plus SuperAdmin and SC Admin, can:
- add supporting members on `assignment-detail.php`
- remove supporting members from the same page
- see support counts on dashboards and assignment lists

No schema migration is required if you already imported `schema.sql`, because the `assignment_supporting_members` table was already present in earlier versions.


## What changed in this build
- assignment support language is clearer: "Add assignment support person" and "Choose eligible person from this committee"
- subtasks can now be created from Assignment Detail
- subtask statuses can be updated on Assignment Detail
- dashboard and assignments list show subtask counts
- setup-admin now redirects cleanly if you are already logged in and revisit it

## Upgrade note
No database migration is required for this step if you imported the existing `schema/schema.sql` from the earlier starter, because the `assignment_subtasks` table already exists there.
