# Session Handoff

Last update: 2026-11-19

## Branch
`work/clean-app-2026-10-04`

## Completed today
- Admin post-login landing is the profile; operator landing remains the contacts list.
- Fixed the admin gym selector: no “All activities” choice, valid gym selection retained (first gym selected if needed), and contacts/appointments stay scoped to the selected gym.
- Admin contact creation now sends the selected `gym_id`; the API rejects a missing/invalid gym instead of silently assigning gym 1.
- Appointment listing and booking use the selected gym. Fixed the untranslated individual-appointment category label.
- Kept gym managers separate from the admin identity; registration sends first and last names separately to prevent duplicate surnames.
- Updated local development data after DB backups; no tax codes were fabricated. Backups are outside the repository in `~/backups-db`.
- Production Angular build, PHP lint, PHPUnit (17 tests / 44 assertions), Problems and `git diff --check` passed. Frontend responds on port 4200. Authenticated admin/operator smoke tests remain to be done.

## Commit
Code and resume notes were committed locally on this branch. No push was requested or performed.

## Next steps
1. Check `git status` and confirm the latest commit.
2. Verify admin login/profile, the single-gym selector, and contact/appointment filtering; verify operator access remains limited to its gym.
3. Resume deferred business decisions (final prices and PayPal sandbox) only after user approval. Do not enable live payments or change production credentials without approval.

## Quick restart checklist
1. `cd /home/santo/progetti/mio-primo-devops`
2. Check containers/API on port 8083; start the Angular app on port 4200 if it is not running.
3. `cd app-mobile && npm run build`
