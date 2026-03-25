<template>
  <Head :title="`CS Report — ${report.cs_no}`" />

  <div class="print-root">

    <!-- Letterhead -->
    <div class="letterhead">
      <div class="lh-left">
        <img src="/images/pshs-logo.png" alt="PSHS Logo" class="lh-logo" onerror="this.style.display='none'" />
      </div>
      <div class="lh-center">
        <div class="lh-republic">Republic of the Philippines</div>
        <div class="lh-school">PHILIPPINE SCIENCE HIGH SCHOOL</div>
        <div class="lh-campus">CARAGA REGION CAMPUS IN BUTUAN CITY</div>
        <div class="lh-dept">DEPARTMENT OF SCIENCE AND TECHNOLOGY</div>
        <div class="lh-tagline">OneDOST4U: Solutions and Opportunities for All</div>
      </div>
      <div class="lh-right">
        <img src="/images/bagong-pilipinas.png" alt="Bagong Pilipinas" class="lh-logo" onerror="this.style.display='none'" />
      </div>
    </div>

    <hr class="header-hr" />

    <!-- Document title -->
    <div class="doc-title">COUNSELING SESSION REPORT</div>

    <!-- CS metadata (top-right block) -->
    <div class="meta-block">
      <div class="meta-row"><span class="meta-label">CS No.:</span><span class="meta-value">{{ report.cs_no }}</span></div>
      <div class="meta-row"><span class="meta-label">Date Filed:</span><span class="meta-value">{{ fmtDate(report.date_filed) }}</span></div>
      <div class="meta-row"><span class="meta-label">School Year:</span><span class="meta-value">{{ report.school_year }}</span></div>
    </div>

    <!-- I. Client Profile -->
    <div class="section-title">I.&nbsp;&nbsp;Client Profile</div>

    <div class="client-row">
      <div class="client-name-block">
        <span class="field-label">Name:</span>
        <span class="field-value underline">{{ report.client_name }}</span>
      </div>
      <div class="client-age-block">
        <span class="field-label">Age:</span>
        <span class="field-value underline">{{ report.client_age ?? '' }}</span>
      </div>
      <div class="client-sex-block">
        <span class="field-label">Sex:</span>
        <span class="checkbox-item">
          <span class="checkbox-box">{{ report.client_sex === 'Male' ? '✓' : '' }}</span> Male
        </span>
        <span class="checkbox-item">
          <span class="checkbox-box">{{ report.client_sex === 'Female' ? '✓' : '' }}</span> Female
        </span>
      </div>
    </div>

    <div class="client-row" style="margin-top:6px;">
      <div class="client-name-block">
        <span class="field-label">Grade &amp; Section:</span>
        <span class="field-value underline">{{ report.grade_section ?? '' }}</span>
      </div>
      <div class="client-age-block">
        <span class="field-label">Batch:</span>
        <span class="field-value underline">{{ report.batch ?? '' }}</span>
      </div>
    </div>

    <!-- II. Referral Details/Strategies -->
    <div class="section-title" style="margin-top:10px;">II.&nbsp;&nbsp;Referral Details/Strategies</div>

    <div class="strategy-row">
      <span v-for="s in strategyOptions" :key="s" class="checkbox-item">
        <span class="checkbox-box">{{ (report.referral_strategies ?? []).includes(s) ? '✓' : '' }}</span>
        {{ s }}
      </span>
    </div>

    <!-- Concern -->
    <div class="field-block">
      <div class="field-label-standalone">Concern:</div>
      <div class="field-line">{{ report.concern ?? '' }}</div>
    </div>

    <!-- Difficulties Presented -->
    <div class="field-block">
      <div class="field-label-standalone">Difficulties Presented:</div>
      <div class="field-multiline">{{ report.difficulties_presented ?? '' }}</div>
    </div>

    <!-- Counselor's Notes -->
    <div class="field-block">
      <div class="field-label-standalone">Counselor's Notes:</div>
      <div class="field-multiline large">{{ report.counselor_notes ?? '' }}</div>
    </div>

    <!-- Accomplished by -->
    <div class="sig-section">
      <div class="sig-label-text">Accomplished by:</div>
      <div class="sig-box">
        <div class="sig-name">{{ report.accomplished_by_user?.name ?? '' }}</div>
        <div class="sig-position">{{ report.accomplished_by_user?.position ?? 'GSA I/ Life Coach' }}</div>
      </div>
    </div>

    <!-- Footer -->
    <div class="footer-bar">
      <div class="footer-left">
        <span>📍</span> Brgy. Ampayon, Butuan City, 8600<br />
        📘 Philippine Science High School – Caraga Region Campus in Butuan City
      </div>
      <div class="footer-center">
        📞 (085) 817-0987<br />
        🌐 https://crc.pshs.edu.ph<br />
        ✉ ocd@crc.pshs.edu.ph
      </div>
      <div class="footer-right">
        <!-- SOCOTEC / ISO logos placeholder -->
        <div class="footer-cert">SOCOTEC<br/>ISO 9001</div>
      </div>
    </div>
    <div class="footer-tagline">Premier science high school education begins at DOST-PSHS.</div>

    <!-- Printed at meta -->
    <div class="print-meta">Printed: {{ printedAt }}</div>

  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  report:  Object,
  printer: Object,
})

const strategyOptions = ['Referral', 'Called-In', 'Walk-In', 'Case Conference', 'Homevisit']

const printedAt = computed(() => {
  const n = new Date(), p = v => String(v).padStart(2, '0')
  return `${n.getFullYear()}-${p(n.getMonth()+1)}-${p(n.getDate())} ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`
})

function fmtDate(d) {
  if (!d) return '—'
  const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, day).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })
}

onMounted(() => setTimeout(() => window.print(), 400))
</script>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { background: #fff; }

.print-root {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9.5px;
  color: #000;
  padding: 10mm 12mm;
  max-width: 210mm;
  margin: 0 auto;
}

/* ── Letterhead ─────────────────────────────────────────────── */
.letterhead {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.lh-logo    { height: 52px; width: auto; }
.lh-center  { flex: 1; text-align: center; line-height: 1.3; }
.lh-republic { font-size: 8px; color: #444; }
.lh-school   { font-size: 12px; font-weight: 900; letter-spacing: .3px; }
.lh-campus   { font-size: 8.5px; font-weight: 700; }
.lh-dept     { font-size: 8.5px; font-weight: 700; }
.lh-tagline  { font-size: 7.5px; color: #555; }
.header-hr   { border: none; border-top: 1.5px solid #000; margin: 4px 0 8px; }

/* ── Document title ─────────────────────────────────────────── */
.doc-title {
  text-align: center;
  font-size: 13px;
  font-weight: 900;
  text-decoration: underline;
  letter-spacing: .5px;
  margin-bottom: 10px;
}

/* ── CS metadata block (top-right) ─────────────────────────── */
.meta-block {
  float: right;
  border: 1px solid #999;
  padding: 4px 8px;
  margin: -6px 0 6px 12px;
  min-width: 160px;
}
.meta-row { display: flex; gap: 4px; margin-bottom: 2px; font-size: 9px; }
.meta-label { font-weight: 700; white-space: nowrap; }
.meta-value {
  flex: 1;
  border-bottom: 1px solid #000;
  min-width: 90px;
  padding-bottom: 1px;
}

/* ── Section titles ─────────────────────────────────────────── */
.section-title {
  font-weight: 700;
  font-size: 10px;
  margin: 8px 0 5px;
  clear: both;
}

/* ── Client profile ─────────────────────────────────────────── */
.client-row {
  display: flex;
  align-items: baseline;
  gap: 12px;
  flex-wrap: wrap;
}
.client-name-block { flex: 2; display: flex; align-items: baseline; gap: 4px; }
.client-age-block  { flex: 0 0 70px; display: flex; align-items: baseline; gap: 4px; }
.client-sex-block  { flex: 0 0 auto; display: flex; align-items: center; gap: 6px; }

.field-label { font-weight: 700; white-space: nowrap; font-size: 9.5px; }
.field-value { flex: 1; border-bottom: 1px solid #000; min-width: 60px; padding-bottom: 1px; font-size: 9.5px; }
.field-value.underline { border-bottom: 1px solid #000; }

/* ── Checkboxes ─────────────────────────────────────────────── */
.checkbox-item { display: inline-flex; align-items: center; gap: 3px; font-size: 9px; margin-right: 4px; }
.checkbox-box  {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 10px;
  height: 10px;
  border: 1px solid #000;
  font-size: 9px;
  line-height: 1;
}

/* ── Strategy row ───────────────────────────────────────────── */
.strategy-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; }

/* ── Field blocks ───────────────────────────────────────────── */
.field-block { margin-top: 6px; }
.field-label-standalone { font-weight: 700; font-size: 9.5px; margin-bottom: 2px; }
.field-line {
  border-bottom: 1px solid #000;
  min-height: 14px;
  padding-bottom: 2px;
  font-size: 9.5px;
}
.field-multiline {
  border: 1px solid #ccc;
  min-height: 40px;
  padding: 4px 6px;
  font-size: 9.5px;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}
.field-multiline.large { min-height: 80px; }

/* ── Signature section ──────────────────────────────────────── */
.sig-section { margin-top: 14px; }
.sig-label-text { font-size: 9.5px; margin-bottom: 4px; }
.sig-box {
  display: inline-block;
  width: 50%;
  text-align: center;
}
.sig-name {
  border-bottom: 1px solid #000;
  font-weight: 700;
  font-size: 9.5px;
  text-decoration: underline;
  text-transform: uppercase;
  min-height: 20px;
  padding-bottom: 2px;
}
.sig-position { font-size: 8.5px; margin-top: 2px; }

/* ── Footer ─────────────────────────────────────────────────── */
.footer-bar {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 16px;
  border-top: 1.5px solid #000;
  padding-top: 4px;
  font-size: 7.5px;
  line-height: 1.6;
}
.footer-left   { flex: 2; }
.footer-center { flex: 1; }
.footer-right  { flex: 0 0 60px; text-align: center; }
.footer-cert   { border: 1px solid #ccc; font-size: 7px; padding: 2px 4px; line-height: 1.3; }
.footer-tagline {
  text-align: center;
  font-size: 8px;
  font-style: italic;
  margin-top: 3px;
  font-weight: 600;
}

.print-meta { font-size: 7px; color: #aaa; text-align: right; margin-top: 4px; }

/* ── Print media ────────────────────────────────────────────── */
@media print {
  @page { size: A4 portrait; margin: 10mm; }
  html, body { margin: 0; padding: 0; }
  .print-root { padding: 0; }
}
</style>
