<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage.js' // still used for bg.jpg

const props = defineProps({
  student: Object,
  enrollment: Object,
  school_year: String,
  barcode_svg: String,
  ocd: Object,
  emergency: Object,
})

const photoUrl = computed(() => {
  if (!props.student.img) return null
  return route('students.photo', { id: props.student.id })
})

function printCard() {
  window.print()
}
</script>

<template>
  <Head :title="`ID Card — ${student.full_name}`" />

  <div class="page">
    <div class="controls no-print">
      <Link :href="route('students.index')" class="back-link">
        <ArrowLeftIcon class="h-4 w-4" /> Back to Students
      </Link>
      <h1>{{ student.full_name }}</h1>
      <button @click="printCard" class="btn-print">
        <PrinterIcon class="h-4 w-4" /> Print ID Card
      </button>
      <p class="hint">
        Load a blank CR-80 card into the Matica XID8300. This sends both the front and
        back as a single print job — make sure dual-sided / duplex printing is enabled
        in the printer driver so both sides print on one card.
      </p>
    </div>

    <div class="cards">
      <!-- Front -->
      <div class="id-card id-card-front">
        <div class="id-bg" :style="{ backgroundImage: `url(${storageUrl('bg.jpg')})` }"></div>

        <div class="id-band">
          <img src="/images/pshslogo.png" class="id-logo" alt="" onerror="this.style.display='none'" />
          <div class="id-band-text">
            <div class="id-republic">Republic of the Philippines</div>
            <div class="id-dost">Department of Science and Technology</div>
            <div class="id-school">Philippine Science High School</div>
            <div class="id-campus">Caraga Region Campus in Butuan City</div>
          </div>
        </div>

        <div class="id-card-inner">
          <div class="id-photo">
            <img v-if="photoUrl" :src="photoUrl" alt="Photo" />
            <div v-else class="id-photo-empty">No Photo</div>
          </div>

          <div class="id-name">{{ student.full_name }}</div>

          <div v-if="barcode_svg" class="id-barcode" v-html="barcode_svg"></div>
          <div v-if="student.barcode" class="id-barcode-no">{{ student.barcode }}</div>

          <div class="id-lrn">
            <div class="id-lrn-label">Learner Reference Number</div>
            <div class="id-lrn-value">{{ student.lrn || '—' }}</div>
          </div>

          <div v-if="ocd" class="id-sig-block">
            <img v-if="ocd.signature_uri" :src="ocd.signature_uri" class="id-sig-img" alt="" />
            <div class="id-sig-rule"></div>
            <div class="id-sig-name">{{ ocd.name }}</div>
            <div class="id-sig-position">{{ ocd.position }}</div>
          </div>
        </div>

        <div class="id-footer-band">SCHOLAR</div>
      </div>

      <!-- Back -->
      <div class="id-card">
        <div class="id-back-band">
          <div class="id-band-title">In Case of Emergency, Notify</div>
        </div>

        <div class="id-card-inner">
          <div class="id-emergency-field">
            <div class="id-emergency-label">Name of Parent / Guardian</div>
            <div class="id-emergency-value">{{ emergency.guardian_name || '—' }}</div>
          </div>
          <div class="id-emergency-field">
            <div class="id-emergency-label">Contact Number</div>
            <div class="id-emergency-value">{{ emergency.contact_no || '—' }}</div>
          </div>
          <div class="id-emergency-field">
            <div class="id-emergency-label">Address</div>
            <div class="id-emergency-value">{{ emergency.address || '—' }}</div>
          </div>

          <div class="id-divider"></div>

          <div class="id-notice">
            <div class="id-notice-title">Important</div>
            <p>This ID is valid for the period indicated on the validation sticker.</p>
            <p>This ID is non-transferable and should be worn visibly at all times while inside the campus.</p>
            <p>This ID must be surrendered upon graduation.</p>
            <p>Lost ID cards will be replaced only upon presentation of an affidavit of loss to the Office of the Registrar.</p>
          </div>

          <div class="id-sy-label">Valid for School Year</div>
        </div>

        <div class="id-back-footer">
          <div v-if="barcode_svg" class="id-back-barcode" v-html="barcode_svg"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { background: #f1f5f9; }

.page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
  padding: 24px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
}

/* ── On-screen controls ───────────────────────────────────────── */
.controls {
  width: 100%;
  max-width: 480px;
  text-align: center;
}
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #475569;
  text-decoration: none;
  margin-bottom: 8px;
}
.back-link:hover { color: #1447c0; }
.controls h1 { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
.btn-print {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #1447c0;
  color: #fff;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  margin-bottom: 10px;
}
.btn-print:hover { background: #0f3a9e; }
.hint { font-size: 12px; color: #64748b; line-height: 1.5; }

/* ── Card preview layout ──────────────────────────────────────── */
.cards {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 24px;
}

/* CR-80 card = 54mm x 86mm (portrait) */
.id-card {
  width: 54mm;
  height: 86mm;
  background: #fff;
  border-radius: 2.5mm;
  box-shadow: 0 4px 16px rgba(0,0,0,.12);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.id-card-front {
  position: relative;
  isolation: isolate;
}
.id-bg {
  position: absolute;
  inset: 0;
  z-index: -1;
  background-size: cover;
  background-position: center;
  opacity: 0.12;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

/* ── Front header / footer bands ──────────────────────────────── */
.id-band {
  background: linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 1.5mm;
  padding: 1.5mm 2mm;
  text-align: left;
}
.id-logo { width: 9mm; height: 9mm; object-fit: contain; flex-shrink: 0; }
.id-band-text { line-height: 1.25; }
.id-republic { font-size: 4.5px; font-weight: 400; }
.id-dost { font-size: 4.5px; font-weight: 400; }
.id-school { font-size: 6.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
.id-campus { font-size: 5px; font-weight: 700; text-transform: uppercase; letter-spacing: .2px; }

.id-footer-band {
  background: linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%);
  color: #fff;
  font-size: 6px;
  font-weight: 700;
  letter-spacing: 2px;
  text-align: center;
  padding: 1.5mm 0;
}

/* ── Back header / footer bands — no gradient ─────────────────── */
.id-back-band {
  text-align: center;
  color: #1447c0;
  padding: 1.5mm 2mm;
  border-bottom: 1.5px solid #1447c0;
}
.id-band-title { font-size: 7px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
.id-back-footer {
  border-top: 1.5px solid #1447c0;
  padding: 1.5mm 3mm;
  height: 11mm;
  display: flex;
  align-items: center;
  justify-content: center;
}
.id-back-barcode { width: 100%; height: 100%; }
.id-back-barcode svg { width: 100%; height: 100%; }

/* ── Card body ─────────────────────────────────────────────────── */
.id-card-inner {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2.5mm 3mm;
  text-align: center;
  overflow: hidden;
}

/* ── Front face ────────────────────────────────────────────────── */
.id-photo {
  width: 30mm;
  height: 30mm;
  margin-top: 1mm;
  border: 1px solid #e2e8f0;
  border-radius: 1.5mm;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
}
.id-photo img { width: 100%; height: 100%; object-fit: cover; }
.id-photo-empty { font-size: 6px; color: #94a3b8; }

.id-name {
  font-size: 8px;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.3;
  margin-top: 1.5mm;
}

.id-barcode { width: 100%; height: 7mm; margin-top: 1mm; }
.id-barcode svg { width: 100%; height: 100%; }
.id-barcode-no {
  font-size: 5px;
  font-weight: 600;
  color: #475569;
  letter-spacing: 1px;
  margin-top: 0.5mm;
}

.id-lrn {
  margin-top: 1mm;
  width: 100%;
  border-top: 1px solid #f1f5f9;
  padding-top: 1mm;
}
.id-lrn-label {
  font-size: 4.5px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.id-lrn-value {
  font-size: 7px;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: 1px;
  margin-top: 0.5mm;
}

.id-sig-block {
  margin-top: auto;
  width: 100%;
  padding-top: 1.5mm;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.id-sig-img {
  height: 5mm;
  max-width: 80%;
  object-fit: contain;
  margin-bottom: 0.5mm;
}
.id-sig-rule {
  width: 80%;
  border-top: 1px solid #94a3b8;
  margin-bottom: 0.8mm;
}
.id-sig-name {
  font-size: 5.5px;
  font-weight: 700;
  color: #1e293b;
  text-transform: uppercase;
}
.id-sig-position {
  font-size: 4.5px;
  color: #94a3b8;
  letter-spacing: .5px;
}

/* ── Back face ─────────────────────────────────────────────────── */
.id-emergency-field { width: 100%; margin-top: 2mm; }
.id-emergency-field:first-child { margin-top: 1mm; }
.id-emergency-label {
  font-size: 5px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.id-emergency-value {
  font-size: 7.5px;
  font-weight: 600;
  color: #1e293b;
  margin-top: 0.5mm;
}

.id-divider { width: 100%; border-top: 1px solid #f1f5f9; margin: 2mm 0; }

.id-notice {
  font-size: 6px;
  color: #475569;
  line-height: 1.6;
  text-align: justify;
  width: 100%;
}
.id-notice-title {
  font-size: 6.5px;
  font-weight: 700;
  color: #1447c0;
  text-transform: uppercase;
  letter-spacing: .5px;
  text-align: center;
  margin-bottom: 1mm;
}
.id-notice p { margin-bottom: 1mm; }
.id-notice p:last-child { margin-bottom: 0; }

.id-sy-label {
  margin-top: 3mm;
  width: 100%;
  text-align: center;
  font-size: 5.5px;
  font-weight: 700;
  color: #1447c0;
  text-transform: uppercase;
  letter-spacing: .5px;
  border-top: 1px solid #f1f5f9;
  padding-top: 1.5mm;
}

/* ── Print ─────────────────────────────────────────────────────── */
@media print {
  @page { size: 54mm 86mm; margin: 0; }
  html, body { margin: 0; padding: 0; background: #fff; }
  .no-print { display: none !important; }
  .page { padding: 0; gap: 0; }
  .cards { display: block; }
  .id-card {
    box-shadow: none;
    border-radius: 0;
    width: 54mm;
    height: 86mm;
    page-break-after: always;
  }
  .id-card:last-child { page-break-after: auto; }
}
</style>
