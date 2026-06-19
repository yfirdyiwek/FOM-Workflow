# FOM Seed Data

This folder now includes `fom_seed_data.sql`, which adds:

- the named FOM seed users you provided
- committee memberships, including users who belong to more than one committee
- sample official assignments
- a few supporting-member links for testing
- simple activity-log entries

## Import order

1. Import `schema.sql` first.
2. Run `setup-admin.php` and create your first real admin account.
3. Import `fom_seed_data.sql` in phpMyAdmin.

## Important notes

- The seed file is designed to be **safe after setup**. If a user with the same `display_name` already exists (for example, `Yitna Firdyiwek` as your SuperAdmin), the file reuses that user instead of inserting a duplicate.
- Newly inserted seed users use this default password:

`ChangeMe123!`

Change or deactivate them later as needed.

## Included overlap cases

This seed set includes users who belong to more than one committee, including support-member overlaps such as:

- Gabriella Ghermandi — CC and ARDC
- Gohalem Assefa — CC and ARDC
- Wassy Tesfa — SC, CC, and FC
- Yadwa Yawand-Wossen — SC and CC
- Elfy Getachew — SC and ARDC
- Meqdes Mesfin — SC and ARDC
- Yitna Firdyiwek — SC and FC

## Seed assignments included

- Newsletter Special Edition - Yekatit 12
- Newsletter Special Edition - Adwa
- 2025 Taxes
- 2026 Fundraising
- Resolution of term "Prisoners"
- FOM Archive Guidelines
