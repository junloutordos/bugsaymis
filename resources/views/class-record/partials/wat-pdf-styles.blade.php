*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 8.5pt;
  color: #0f172a;
  background: #fff;
}

/* mPDF margins are 0 (header/footer images are full-bleed); this pads
   the actual content in from the left/right paper edge. */
.page-body { padding: 1mm 10mm 0; }

.wat-heading    { text-align: center; margin-bottom: 3mm; }
/* font-weight: bold (not a numeric weight like 800) — mPDF's font
   substitution only reliably maps normal/bold, so a numeric weight can
   silently fall back to regular. */
.wat-heading h1 { font-size: 13pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin: 0 0 2mm; }
/* A <table> with per-cell padding, not inline <span>s with margin —
   mPDF silently ignores margin/padding on inline elements (verified by
   rendering: identical output bytes with or without it); table-cell
   padding is the reliable primitive for horizontal spacing in mPDF,
   same as .wat-table/.wat-signatories below. */
.wat-meta       { font-size: 8pt; }
.wat-meta td    { padding-right: 12mm; white-space: nowrap; }
.wat-meta td:last-child { padding-right: 0; }

/* Widths set via width="" on <th> — most reliable in mPDF. */
.wat-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 4mm;
}
.wat-table th {
  background: #f1f5f9;
  border: 1px solid #94a3b8;
  padding: 4px 6px;
  font-size: 7.5pt;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  text-align: left;
}
.wat-table td {
  border: 1px solid #94a3b8;
  padding: 4px 6px;
  font-size: 8.5pt;
  vertical-align: top;
}
/* Day label repeats on the first row of each rowspan chunk (see
   WatPdfPaginator — mPDF can never split a rowspan cell across a page
   break, so chunk boundaries are decided in PHP before render, and a day
   that spans two pages becomes two chunks, the second labelled "(cont'd)").
   .wat-chunk-start marks the first row of each DAY's first chunk (not
   continuation chunks) with a heavier top border as a grouping cue between
   distinct days. */
.wat-chunk-start td { border-top: 2px solid #475569; }
.wat-day    { font-weight: 600; white-space: nowrap; }
.wat-empty  { color: #94a3b8; font-style: italic; }
.wat-center { text-align: center; white-space: nowrap; }
.wat-title  { font-weight: 700; }

/* Signatories — 3-column table, matches the .wat-signatories layout
   previously used in the browser-print version. Extra margin-top is the
   deliberate breathing room/break between the assessment table and the
   signature block. */
.wat-signatories { width: 100%; margin-top: 16mm; }
.wat-signatory   { width: 33.33%; text-align: left; vertical-align: top; padding: 0 6mm; }
.wat-signatory-caption {
  font-size: 7.5pt; color: #334155; margin-bottom: 3mm;
}
/* Blank space for the wet signature, with the sign-line as its
   border-bottom — sits ABOVE the printed name, not under it. */
.wat-signature-line { height: 11mm; border-bottom: 1px solid #0f172a; }
/* No text-transform: uppercase here — PersonNameFormatter::assemble()
   already uppercases the base name in PHP; forcing it in CSS would also
   uppercase any pre-/post-nominal title, which must keep its original
   casing (e.g. "Dr." not "DR."). */
.wat-signatory-name {
  font-size: 8.5pt; font-weight: bold; letter-spacing: 0.3px;
  margin-top: 2px;
}
.wat-signatory-position { font-size: 7pt; color: #475569; margin-top: 3px; }

.wat-reviewed { margin-top: 4mm; font-size: 7.5pt; color: #475569; font-style: italic; }
