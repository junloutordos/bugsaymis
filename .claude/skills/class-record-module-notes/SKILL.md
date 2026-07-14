---
name: class-record-module-notes
description: Gotchas and conventions for the Class Record module (class-records.*) — grade computation, school-year locking, CSV import. Use when working on class records, running grades, or grade PDFs.
---

- `GradeComputationService` — pure PHP, no DB calls; JS mirror in `resources/js/Utils/ClassRecord/gradeUtils.js`
- Running grade: Q2–Q4 uses `floor((current × 2/3) + (previous × 1/3))` — floor, not round
- School year lock: records from past `SchoolYear` are fully read-only; guard on all editing endpoints
- CSV import: parsed client-side via FileReader → JSON POST → `students/import` endpoint; avoids Cloudflare WAF
- `school_year_id` FK on `class_records` → `school_years.id`; backfilled by matching `school_years.name`
- At-risk row highlights (red/orange/amber) in `ScoreGrid.vue` based on running grade
- Final annual grades tab: `ClassRecordFinalGradeController` — per-student Q1–Q4 GEs + annual average
- Copy assessments from previous quarter: `ClassRecordAssessmentController` copy endpoint
- PDF export: `ClassRecordPdfService` — A3 landscape via mPDF, stanine legend footer
- Teacher notified (bell + email via `ClassRecordCheckedMail`) when admin marks record checked
