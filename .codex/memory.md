# Codex Memory

## 2026-06-30 - UI/UX Refactor Deployment

- Completed shared UI/UX refactor for BugSayMis/Atlas.
- Added `AppFilterBar` and `AppIconButton`.
- Expanded `AppButton` with link rendering, block layout, and success/warning variants.
- Expanded `AppModal` with subtitle, backdrop behavior, body/panel class hooks, and Heroicons close button.
- Fixed `AppSelect` label class typo.
- Extracted `AdminLayout` UI responsibilities into `AdminTopbar`, `VersionHistoryModal`, `ReportDateRangeModal`, and `SessionExpiredOverlay`.
- Refactored `Users/Index.vue`, `ITJobRequests/Index.vue`, and `DocumentTracking/Index.vue` to use shared page header, filter, button, modal, and table primitives where practical.
- Verification passed: local `npm run build`, Docker image build frontend step, and local `/login` HTTP response.
- Browser screenshot QA was not completed because browser control was unavailable in the session.
- Commit: `b5a83ab refactor: standardize core UI components`.
- Deployed to `main` via merge commit `d049279`.
- Returned to `junlou` branch after deployment and preserved unrelated local dirty files.
