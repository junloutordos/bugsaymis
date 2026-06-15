<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage.js'

const props = defineProps({
  student: Object,
  enrollment: Object,
  school_year: String,
  qr_svg: String,
})

const photoUrl = computed(() => {
  if (!props.student.img) return null
  return storageUrl(`students_profile_picture/${encodeURIComponent(props.student.img)}`)
})

const gradeSection = computed(() => {
  if (!props.enrollment) return null
  const parts = [`Grade ${props.enrollment.grade_level}`]
  if (props.enrollment.section) parts.push(props.enrollment.section)
  return parts.join(' - ')
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

          <div v-if="gradeSection" class="id-pill">{{ gradeSection }}</div>

          <div class="id-info">
            <div v-if="student.lrn" class="id-info-row">
              <span class="id-info-label">LRN</span>
              <span class="id-info-value">{{ student.lrn }}</span>
            </div>
            <div v-if="school_year" class="id-info-row">
              <span class="id-info-label">S.Y.</span>
              <span class="id-info-value">{{ school_year }}</span>
            </div>
          </div>
        </div>

        <div class="id-footer-band">STUDENT</div>
      </div>

      <!-- Back -->
      <div class="id-card">
        <div class="id-band id-band-thin">
          <div class="id-band-text">
            <div class="id-id-no">ID No. {{ student.barcode ?? '—' }}</div>
          </div>
        </div>

        <div class="id-card-inner">
          <div class="id-qr" v-if="qr_svg" v-html="qr_svg"></div>
          <div v-else class="id-qr id-qr-empty">No QR</div>
          <div class="id-qr-caption">Scan to verify enrollment</div>

          <div class="id-divider"></div>

          <div class="id-return">
            <strong>If found, please return to:</strong><br />
            Philippine Science High School<br />
            Caraga Region Campus<br />
            Ampayon, Butuan City, 8600<br />
            Tel: (085) 817-0987
          </div>

          <div class="id-sig-line">
            <div class="id-sig-rule"></div>
            <div class="id-sig-label">Cardholder's Signature</div>
          </div>
        </div>

        <div class="id-footer-band id-footer-band-thin">
          Property of PSHS-CRC · Non-transferable
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
.id-id-no { font-size: 6px; font-weight: 700; letter-spacing: .5px; }

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
  width: 26mm;
  height: 30mm;
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
.id-pill {
  margin-top: 1.5mm;
  background: #eff6ff;
  color: #1447c0;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  font-size: 5.5px;
  font-weight: 700;
  padding: 0.8mm 3mm;
}
.id-info {
  margin-top: auto;
  width: 100%;
  padding-top: 2mm;
}
.id-info-row {
  display: flex;
  justify-content: space-between;
  border-top: 1px solid #f1f5f9;
  padding: 1mm 0;
  font-size: 5.5px;
}
.id-info-label { color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.id-info-value { color: #1e293b; font-weight: 600; }

/* ── Back face ─────────────────────────────────────────────────── */
.id-qr {
  width: 26mm;
  height: 26mm;
  margin-top: 2mm;
  display: flex;
  align-items: center;
  justify-content: center;
}
.id-qr svg { width: 100%; height: 100%; }
.id-qr-empty {
  font-size: 6px;
  color: #94a3b8;
  border: 1px dashed #e2e8f0;
}
.id-qr-caption {
  font-size: 5px;
  color: #64748b;
  margin-top: 1mm;
}
.id-divider {
  width: 100%;
  border-top: 1px solid #f1f5f9;
  margin: 2mm 0;
}
.id-return {
  font-size: 4.5px;
  color: #475569;
  line-height: 1.6;
}
.id-sig-line {
  margin-top: auto;
  width: 100%;
  padding-bottom: 1mm;
}
.id-sig-rule {
  border-top: 1px solid #94a3b8;
  margin: 0 4mm 0.8mm;
}
.id-sig-label {
  font-size: 4.5px;
  color: #94a3b8;
  letter-spacing: .5px;
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
