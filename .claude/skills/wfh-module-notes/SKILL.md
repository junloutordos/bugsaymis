---
name: wfh-module-notes
description: Gotchas and conventions for the WFH Attendance module (hr.wfh.*) — S3 photo proxy, base64 upload pattern, file ID format. Use when working on WFH time-in/out or accomplishment photo features.
---

- Time-in/out photos: captured as base64 data URI from camera → sent as JSON → decoded and stored in S3
- Accomplishment photos: same base64 pattern (Cloudflare blocks multipart)
- Photo proxy: `GET /hr/wfh/photo/{fileId}` — authenticates to S3 via SDK, serves privately
- File ID format: `s3.<base64url-encoded-s3-key>` — distinguished from legacy Google Drive IDs
- Route regex: `[a-zA-Z0-9_.=-]+` — the dot is required for the `s3.` prefix
