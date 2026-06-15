<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage.js'

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
  return storageUrl(`students_profile_picture/${encodeURIComponent(props.student.img)}`)
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
      <div class="id-card">
        <div class="id-band">
          <img src="/images/pshslogo.png" class="id-logo" alt="" onerror="this.style.display='none'" />
          <div class="id-band-text">
            <div class="id-school">Philippine Science High School</div>
            <div class="id-campus">Caraga Region Campus</div>
          </div>
        </div>

        <div class="id-card-inner">
          <div class="id-doc-label">Student Identification Card</div>

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

        <div class="id-footer-band">STUDENT</div>
      </div>

      <!-- Back -->
      <div class="id-card">
        <div class="id-band id-band-thin">
          <div class="id-band-text">
            <div class="id-band-title">In Case of Emergency, Notify</div>
          </div>
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
            <strong>IMPORTANT:</strong> This ID is the property of PSHS-CRC and must be
            presented upon request by school authorities. It is non-transferable and
            valid only for the school year indicated below. Loss must be reported
            immediately to the OCD/Records Office. Tampering or alteration renders this
            ID invalid.
          </div>
        </div>

        <div class="id-footer-band id-footer-band-thin">
          Valid for School Year: {{ school_year || '—' }}
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

/* ── Header / footer bands ────────────────────────────────────── */
.id-band {
  background: linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1.5mm;
  padding: 2mm 2mm;
  text-align: center;
}
.id-band-thin { padding: 1.5mm 2mm; justify-content: center; }
.id-logo { width: 7mm; height: 7mm; object-fit: contain; flex-shrink: 0; }
.id-band-text { line-height: 1.25; }
.id-school { font-size: 5.5px; font-weight: 700; }
.id-campus { font-size: 4.5px; text-transform: uppercase; letter-spacing: .5px; opacity: .85; }
.id-band-title { font-size: 6px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }

.id-footer-band {
  background: linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%);
  color: #fff;
  font-size: 6px;
  font-weight: 700;
  letter-spacing: 2px;
  text-align: center;
  padding: 1.5mm 0;
}
.id-footer-band-thin {
  font-size: 4.5px;
  font-weight: 600;
  letter-spacing: .5px;
  text-transform: none;
}

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
.id-doc-label {
  font-size: 4.5px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin: 1mm 0 1.5mm;
}
.id-photo {
  width: 20mm;
  height: 24mm;
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
  margin-top: 2mm;
}

.id-barcode {
  width: 100%;
  height: 9mm;
  margin-top: 1.5mm;
}
.id-barcode svg { width: 100%; height: 100%; }
.id-barcode-no {
  font-size: 5px;
  font-weight: 600;
  color: #475569;
  letter-spacing: 1px;
  margin-top: 0.5mm;
}

.id-lrn {
  margin-top: 1.5mm;
  width: 100%;
  border-top: 1px solid #f1f5f9;
  padding-top: 1.5mm;
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
  padding-top: 2mm;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.id-sig-img {
  height: 7mm;
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
}
.id-sig-position {
  font-size: 4.5px;
  color: #94a3b8;
  letter-spacing: .5px;
}

/* ── Back face ─────────────────────────────────────────────────── */
.id-emergency-field {
  width: 100%;
  margin-top: 2mm;
}
.id-emergency-field:first-child { margin-top: 1mm; }
.id-emergency-label {
  font-size: 4.5px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.id-emergency-value {
  font-size: 6px;
  font-weight: 600;
  color: #1e293b;
  margin-top: 0.5mm;
}

.id-divider {
  width: 100%;
  border-top: 1px solid #f1f5f9;
  margin: 2mm 0;
}

.id-notice {
  font-size: 4.2px;
  color: #475569;
  line-height: 1.6;
  text-align: left;
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
