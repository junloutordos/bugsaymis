# Issuances Show Page UI/UX Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the title-squeeze and Resend-All-overflow bugs on the Issuances Show page, and raise the page to the same "premium" visual bar as other wide admin pages in the app, by widening and restructuring `resources/js/Pages/Issuances/Show.vue`.

**Architecture:** Pure Vue template/Tailwind markup changes in a single existing file. No new components, no props, no backend calls change. The header card and the Acknowledgments card both move from a single packed flex row (title/heading + action buttons sharing space) to a stacked layout (title/heading on its own row, actions in a `flex-wrap` row below) — this is what makes both bugs structurally impossible to reintroduce, since the title/heading's width no longer depends on how many action buttons are visible for a given role/status.

**Tech Stack:** Vue 3 `<script setup>`, Tailwind CSS 3, Inertia.js 2 (existing patterns only — no new dependencies).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-11-issuances-show-ui-polish-design.md`
- Scope is `resources/js/Pages/Issuances/Show.vue` only — no other Issuances page, no backend/controller/service files, no shared component files (`AppCard.vue`, `AppButton.vue`, `AppBadge.vue`) may be modified.
- No new colors. Stays indigo-dominant; status colors (green/amber/red) stay reserved for actual status values only.
- No automated test suite applies (pure markup change) — every task's verification step is a manual check in the browser, per the spec's Testing section.

---

## Before You Start

Dev stack must be running:

```bash
cd /Users/junlou/bugsaymis-docker && docker compose up -d
```

Vite dev server (run from this repo, in a separate terminal, left running for the whole plan):

```bash
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run dev
```

App is at `http://localhost:8080`. To find an issuance to view: log in as an admin/Administrator account, go to `http://localhost:8080/issuances`, and open any **released** issuance that has at least one recipient (needed to see the Acknowledgments card in Tasks 3–5). If none exists, open any issuance regardless — Tasks 1, 2, and 4's letterhead change are visible on any issuance with content.

---

### Task 1: Widen page container, rebalance grid columns, sticky sidebar

**Files:**
- Modify: `resources/js/Pages/Issuances/Show.vue:284` (page wrapper), `:363` (grid wrapper), `:365` (body column), `:436` (sidebar column)

**Interfaces:** None — layout-only change, no new script logic.

- [ ] **Step 1: Widen the page wrapper**

In `resources/js/Pages/Issuances/Show.vue`, change line 284:

```html
<!-- before -->
    <div class="max-w-4xl space-y-5">

<!-- after -->
    <div class="max-w-7xl mx-auto space-y-6">
```

- [ ] **Step 2: Rebalance the grid from 2:1 to 3:2**

Change line 363:

```html
<!-- before -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

<!-- after -->
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
```

- [ ] **Step 3: Give the body column an explicit 3-column span**

Change line 365:

```html
<!-- before -->
        <div class="lg:col-span-2 space-y-5">

<!-- after -->
        <div class="lg:col-span-3 space-y-6">
```

- [ ] **Step 4: Give the sidebar column an explicit 2-column span and make it sticky**

Change line 436:

```html
<!-- before -->
        <div class="space-y-4">

<!-- after -->
        <div class="lg:col-span-2 space-y-5 lg:sticky lg:top-6 lg:self-start">
```

(`lg:col-span-2` is required, not cosmetic — in a 5-column grid, an auto-placed second item without an explicit span only takes 1 column, not the remaining 2.)

- [ ] **Step 5: Verify in browser**

Open any issuance at `http://localhost:8080/issuances/{id}`. Confirm:
- Page content is visibly wider than before (extends further toward the browser edges on a standard 1440px+ display).
- On a wide viewport, scrolling the page keeps the right sidebar (QR/Hash/Acknowledgments cards) pinned near the top instead of scrolling away with the document body.
- No layout is broken (no cards squished to near-zero width, no horizontal scrollbar appears).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Issuances/Show.vue
git commit -m "style(issuances): widen show page and rebalance grid to 3:2"
```

---

### Task 2: Document header — full-width title, actions in a toolbar row below

**Files:**
- Modify: `resources/js/Pages/Issuances/Show.vue:298-359` (inside the Document Header card, after Task 1's edits)

**Interfaces:** None — same `v-if` conditions, same `@click` handlers, same component usages as today; only their DOM position/wrapping changes.

- [ ] **Step 1: Replace the two-column header layout with a stacked layout**

Replace the whole block from `<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">` through its matching closing `</div>` (originally lines 298–359) with:

```html
          <div>
            <div class="flex flex-wrap items-center gap-2 mb-2">
              <span class="font-mono font-bold text-slate-800 text-base">{{ issuance.display_number }}</span>
              <AppBadge :color="typeColor(issuance.type)">{{ issuance.is_supplement ? issuance.document_kind_label : issuance.type_label }}</AppBadge>
              <AppBadge :color="statusColor(issuance.status)" class="capitalize">{{ issuance.status }}</AppBadge>
              <AppBadge v-if="issuance.archived_at" color="slate">Archived</AppBadge>
            </div>
            <h1 class="text-xl font-semibold text-slate-800">{{ issuance.title }}</h1>
            <p class="text-xs text-slate-500 mt-1">
              Issued by <strong>{{ issuance.creator?.name }}</strong>
              <span v-if="issuance.released_at"> · Released {{ fmtDt(issuance.released_at) }}</span>
            </p>
            <Link v-if="issuance.parent_issuance" :href="route('issuances.show', issuance.parent_issuance.id)"
              class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-800">
              Related to {{ issuance.parent_issuance.control_number }} — {{ issuance.parent_issuance.title }}
            </Link>

            <!-- Actions -->
            <div class="mt-5 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-2">
              <!-- Acknowledge (staff) -->
              <AppButton v-if="!isAdmin && issuance.status === 'released' && !ackDone"
                @click="acknowledge" :disabled="ackForm.processing">
                <CheckCircleIcon class="h-4 w-4" /> Acknowledge Receipt
              </AppButton>
              <div v-else-if="!isAdmin && ackDone"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-success-600 bg-success-50 border border-success-100 rounded-lg">
                <CheckCircleIcon class="h-4 w-4" /> Acknowledged
              </div>

              <!-- Release (admin, draft) -->
              <AppButton v-if="isAdmin && issuance.status === 'draft'" @click="openRelease">
                Sign & Release
              </AppButton>

              <AppButton v-if="isAdmin && !issuance.is_supplement && issuance.status === 'released' && !issuance.archived_at"
                as="link" :href="route('issuances.supplements.create', issuance.id)" variant="secondary">
                <PlusIcon class="h-4 w-4" /> Add Related Document
              </AppButton>

              <!-- Download PDF -->
              <AppButton v-if="issuance.status === 'released'"
                as="a" :href="route('issuances.pdf', issuance.id)" target="_blank" variant="secondary">
                <DocumentArrowDownIcon class="h-4 w-4" /> PDF
              </AppButton>

              <!-- View scan -->
              <AppButton v-if="issuance.has_attachment" variant="secondary" @click="showScanModal = true">
                <EyeIcon class="h-4 w-4" /> View Scan
              </AppButton>
              <AppButton v-if="isAdmin && !issuance.archived_at" variant="secondary" @click="archiveRecord">
                <ArchiveBoxIcon class="h-4 w-4" /> Archive
              </AppButton>
              <AppButton v-if="isAdmin && issuance.archived_at" variant="secondary" @click="unarchiveRecord">
                <ArrowUturnLeftIcon class="h-4 w-4" /> Restore
              </AppButton>
              <AppButton v-if="isAdmin && issuance.status === 'draft'" variant="danger" @click="deleteDraft">
                <TrashIcon class="h-4 w-4" /> Delete
              </AppButton>
            </div>
          </div>
```

Note what changed vs. the original: the outer `flex flex-col sm:flex-row sm:items-start justify-between gap-4` wrapper is gone (title and actions are no longer siblings in a row); the title block's `flex-1` class is gone (no longer needed — it's the only thing in its row now); the actions `div` drops `shrink-0` and gains `mt-5 pt-5 border-t border-slate-100` so it reads as a distinct toolbar under the title block. Every `v-if`, `@click`, and icon inside is untouched.

- [ ] **Step 2: Verify in browser**

Reload the same issuance page. Confirm:
- The title (`<h1>`) now always spans the full card width, regardless of how many action buttons are present for your role/status.
- Log in (or switch test data) so you're viewing as an **admin** on a **draft** issuance — confirm the title is still full-width even though up to 4+ buttons (Sign & Release, Add Related Document, Archive, Delete, etc.) render in the toolbar row below it.
- Shrink the browser window to a narrow/mobile width — confirm the toolbar row's buttons wrap onto multiple lines instead of overflowing or squishing.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Issuances/Show.vue
git commit -m "style(issuances): stack header title above action toolbar row"
```

---

### Task 3: Acknowledgments card — heading on its own row, actions wrap below

**Files:**
- Modify: `resources/js/Pages/Issuances/Show.vue:460-477` (the `<template #header>` block inside the Acknowledgments `AppCard`, after Tasks 1–2's edits)

**Interfaces:** None — same `v-if` conditions and `@click` handlers (`openAddRecipientModal`, `resendSelected`, `resendAll`) as today.

- [ ] **Step 1: Replace the packed header row with a stacked header**

Replace:

```html
            <template #header>
              <div class="flex items-center justify-between gap-2">
                <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                  <UserGroupIcon class="h-3.5 w-3.5" /> Acknowledgments
                </h3>
                <div class="flex items-center gap-1.5">
                  <AppButton v-if="!issuance.archived_at" size="sm" variant="secondary" @click="openAddRecipientModal">
                    <PlusIcon class="h-3.5 w-3.5" /> Add Recipient
                  </AppButton>
                  <AppButton v-if="selectedRecipientIds.length" size="sm" variant="secondary" @click="resendSelected">
                    <ArrowPathIcon class="h-3.5 w-3.5" /> Resend Selected ({{ selectedRecipientIds.length }})
                  </AppButton>
                  <AppButton v-if="totalCount" size="sm" variant="secondary" @click="resendAll">
                    <ArrowPathIcon class="h-3.5 w-3.5" /> Resend All
                  </AppButton>
                </div>
              </div>
            </template>
```

with:

```html
            <template #header>
              <div class="w-full space-y-2.5">
                <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                  <UserGroupIcon class="h-3.5 w-3.5" /> Acknowledgments
                </h3>
                <div class="flex flex-wrap items-center gap-1.5">
                  <AppButton v-if="!issuance.archived_at" size="sm" variant="secondary" @click="openAddRecipientModal">
                    <PlusIcon class="h-3.5 w-3.5" /> Add Recipient
                  </AppButton>
                  <AppButton v-if="selectedRecipientIds.length" size="sm" variant="secondary" @click="resendSelected">
                    <ArrowPathIcon class="h-3.5 w-3.5" /> Resend Selected ({{ selectedRecipientIds.length }})
                  </AppButton>
                  <AppButton v-if="totalCount" size="sm" variant="secondary" @click="resendAll">
                    <ArrowPathIcon class="h-3.5 w-3.5" /> Resend All
                  </AppButton>
                </div>
              </div>
            </template>
```

The `w-full` on the outer div is required, not cosmetic: `AppCard`'s header row is itself a `flex items-center justify-between` container with an (empty, since this card passes no `title` prop) sibling div ahead of the `#header` slot content. Without `w-full`, this block sizes to its own content width and gets pushed flush against the card's right edge by `justify-between` — which is the actual mechanism behind today's "Resend All" overflow. `flex-wrap` on the button row is the second half of the fix, letting buttons drop to a second line instead of clipping.

- [ ] **Step 2: Verify in browser**

View the Acknowledgments card as an admin on a released issuance with at least one recipient. Confirm:
- "Acknowledgments" heading sits alone on its own line, left-aligned, full card width.
- "Add Recipient" and "Resend All" buttons render on the row below, no clipping or overflow past the card's right edge.
- Select 1+ recipients via checkbox so "Resend Selected (N)" also appears — confirm all 3 buttons together still don't overflow (they should wrap to a second line inside the sidebar's now-wider ~480px column if needed).
- Shrink the browser to a narrow width — confirm the button row wraps instead of overflowing.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Issuances/Show.vue
git commit -m "fix(issuances): stop Resend All button overflowing Acknowledgments card"
```

---

### Task 4: Premium polish — letterhead background, sidebar card grouping

**Files:**
- Modify: `resources/js/Pages/Issuances/Show.vue:371` (letterhead preview box), `:436-568` (sidebar column contents, after Tasks 1–3's edits)

**Interfaces:** None — pure styling/wrapping change.

- [ ] **Step 1: Give the letterhead preview a subtle document background**

Change (originally line 371):

```html
<!-- before -->
              <div class="border border-slate-200 rounded-lg p-5">

<!-- after -->
              <div class="border border-slate-200 rounded-lg bg-slate-50/50 p-6">
```

Everything inside this div (the letterhead header, type/control-number block, and `v-html` content) is unchanged.

- [ ] **Step 2: Group the sidebar "metadata" cards tighter, separated from Acknowledgments**

Inside the sidebar column (the div from Task 1 Step 4), the current direct children in order are: Verification QR card, Archive Record card, Content Hash card, Acknowledgments card, Release Settings card. Wrap the first three (QR, Archive Record, Content Hash) in a single tightly-spaced group:

```html
        <div class="lg:col-span-2 space-y-5 lg:sticky lg:top-6 lg:self-start">

          <div class="space-y-3">
            <!-- QR + Verification -->
            <AppCard v-if="issuance.status === 'released'" title="Verification QR">
              <div class="flex justify-center mb-3" v-html="issuance.qr_svg"></div>
              <AppButton variant="secondary" size="sm" block @click="copyVerifyUrl">
                {{ qrCopied ? '✓ Copied!' : 'Copy verification link' }}
              </AppButton>
              <p class="text-[10px] text-slate-400 text-center mt-2">Scan to verify authenticity</p>
            </AppCard>

            <AppCard v-if="issuance.archived_at" title="Archive Record">
              <p class="text-xs text-slate-500">Archived {{ fmtDt(issuance.archived_at) }}</p>
              <p v-if="issuance.archive_reason" class="mt-2 text-sm text-slate-700">{{ issuance.archive_reason }}</p>
            </AppCard>

            <!-- Hash -->
            <AppCard v-if="issuance.content_hash" title="Content Hash">
              <p class="font-mono text-[10px] text-slate-500 break-all bg-slate-50 rounded p-2">{{ issuance.content_hash }}</p>
              <p class="text-[10px] text-slate-400 mt-1">SHA-256 tamper detection</p>
            </AppCard>
          </div>

          <!-- Acknowledgment progress (admin) -->
          <AppCard v-if="isAdmin && issuance.status === 'released'">
            ... (unchanged — Task 3's header template, then the unchanged body: progress bar, select-all checkbox, recipient list) ...
          </AppCard>

          <!-- Release panel (draft) -->
          <AppCard v-if="isAdmin && issuance.status === 'draft' && showReleasePanel" title="Release Settings">
            ... (unchanged) ...
          </AppCard>
        </div>
```

Concretely: add an opening `<div class="space-y-3">` immediately before the `<!-- QR + Verification -->` comment, and its closing `</div>` immediately after the Content Hash `</AppCard>` and before the `<!-- Acknowledgment progress (admin) -->` comment. Nothing inside the three wrapped cards changes. The outer sidebar's `space-y-5` (from Task 1) now provides a visibly bigger gap between this metadata group and the Acknowledgments card than the `space-y-3` gap within the group.

- [ ] **Step 3: Verify in browser**

Reload an issuance where the QR, Archive Record (if archived), and Content Hash cards are all visible alongside Acknowledgments. Confirm:
- The letterhead/content preview box now has a faint gray background distinguishing it from the plain white card.
- QR / Archive Record / Content Hash cards sit visibly closer together than the gap between that group and the Acknowledgments card below them.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Issuances/Show.vue
git commit -m "style(issuances): letterhead background and sidebar card grouping"
```

---

### Task 5: Full manual QA pass

**Files:** None (verification only).

- [ ] **Step 1: Admin, draft issuance**

Open a draft issuance as an admin. Confirm: title is full-width and readable; toolbar row below shows Sign & Release (+ Delete, since draft); clicking "Sign & Release" still opens the Release Settings panel correctly (Task 2/4 did not touch this panel's internals); no visual regressions.

- [ ] **Step 2: Admin, released issuance with several recipients**

Open a released issuance with recipients as an admin. Confirm: Acknowledgments card header shows heading then buttons on the row below with no overflow; Add Recipient modal still opens and submits correctly; Resend Selected / Resend All still fire their existing confirm dialogs and requests (Task 3 did not touch the `resendBulk`/`resendSelected`/`resendAll` functions, only their button markup); sidebar stays pinned (sticky) while scrolling a long document body; QR/Hash/Archive cards (whichever apply) are visually grouped tighter than the gap before Acknowledgments.

- [ ] **Step 3: Staff, released + unacknowledged issuance**

Log in as a non-admin recipient who hasn't acknowledged yet. Confirm: title is full-width; toolbar row below shows only "Acknowledge Receipt"; clicking it still works and flips to the "Acknowledged" state (Task 2 did not touch `acknowledge()` or `ackDone`, only the button's DOM position).

- [ ] **Step 4: Narrow viewport (mobile width)**

Resize the browser to ~375px wide (or use devtools device emulation) on a released issuance as admin. Confirm: header badges/title/toolbar all wrap and stay readable, no horizontal scrollbar; Acknowledgments header buttons wrap onto multiple lines instead of clipping; grid collapses to a single column (`grid-cols-1`) with sidebar cards below the document body, no longer sticky (sticky only applies at `lg:`).

- [ ] **Step 5: Final commit (only if Step 1–4 surfaced fixes)**

If any issue was found and fixed during QA:

```bash
git add resources/js/Pages/Issuances/Show.vue
git commit -m "fix(issuances): QA fixes for show page UI polish"
```

If no issues were found, no commit is needed for this task — Tasks 1–4's commits are the complete deliverable.
