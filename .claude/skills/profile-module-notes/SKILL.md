---
name: profile-module-notes
description: Gotchas for the Profile module (GET/PATCH /profile) — Division FK vs legacy column, S3 temporaryUrl failure in prod. Use when editing the user profile page or ProfileEditModal.
---

- Route: `GET /profile` → `profile.edit`, `PATCH /profile` → `profile.update`
- Editable fields: `name`, `specialization`, `profile_photo_base64` / `profile_photo_mime`
- Photo stored at S3 `profile_pictures/{user_id}_{time}.{ext}`; old photo deleted on update
- Email/password not user-editable (email = HR-managed; password = Google OAuth)
- Profile panel: slide-in `ProfileEditModal.vue` triggered by avatar click
- **Gotcha:** `$user->division` returns a legacy string column — use `Division::find($user->division_id)` explicitly, NOT `$user->load(['division'])` (FK vs legacy column conflict)
- **Gotcha:** `divisions` table uses `division_name` column, NOT `name`
- **Gotcha:** `Storage::disk('s3')->temporaryUrl()` fails in production — use `storageUrl()` composable (serves via `/media/` proxy)
