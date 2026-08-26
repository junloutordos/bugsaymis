<script setup>
import { computed, onMounted, nextTick, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage.js'

const props = defineProps({
  employee:   Object,
  emergency:  Object,
  qr_svg:     String,
  verify_url: String,
  back_route: String,
  ocd:        Object,
})

const photoUrl = computed(() => storageUrl(props.employee.profile_picture))

// Stored name is in filing order ("Lastname, Firstname M.I."). The card
// reads more naturally in reading order — reverse only for display here.
const displayName = computed(() => {
  const raw = props.employee.name || ''
  const commaIndex = raw.indexOf(',')
  if (commaIndex === -1) return raw
  const lastName = raw.slice(0, commaIndex).trim()
  const rest = raw.slice(commaIndex + 1).trim()
  return `${rest} ${lastName}`.trim()
})

function printCard() {
  window.print()
}

const NAME_BASE_FONT_SIZE = 10
const NAME_MIN_FONT_SIZE = 6
const nameEl = ref(null)
const nameFontSize = ref(NAME_BASE_FONT_SIZE)

function fitNameToOneLine() {
  const el = nameEl.value
  if (!el) return
  const availableWidth = el.clientWidth
  if (!availableWidth) return

  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  ctx.font = `700 ${NAME_BASE_FONT_SIZE}px -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif`
  const textWidth = ctx.measureText(displayName.value).width

  if (textWidth > availableWidth) {
    const scaled = NAME_BASE_FONT_SIZE * (availableWidth / textWidth) * 0.98
    nameFontSize.value = Math.max(NAME_MIN_FONT_SIZE, scaled)
  }
}

onMounted(() => {
  nextTick(fitNameToOneLine)
})
</script>

<template>
  <Head :title="`Employee ID — ${displayName}`" />

  <div class="page">
    <div class="no-print w-full max-w-[480px] text-center">
      <Link :href="back_route" class="mb-2 inline-flex items-center gap-1.5 text-[13px] text-slate-600 no-underline hover:text-indigo-600">
        <ArrowLeftIcon class="h-4 w-4" /> Back
      </Link>
      <h1 class="mb-3 text-base font-bold text-slate-800">{{ displayName }}</h1>
      <button @click="printCard" class="mb-2.5 inline-flex items-center gap-1.5 rounded-lg border-none bg-indigo-600 px-[18px] py-2.5 text-[13px] font-semibold text-white cursor-pointer hover:bg-indigo-700">
        <PrinterIcon class="h-4 w-4" /> Print ID Card
      </button>
      <p class="text-xs leading-relaxed text-slate-500">
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

          <div ref="nameEl" class="id-name" :style="{ fontSize: nameFontSize + 'px' }">{{ displayName }}</div>
          <div class="id-position">{{ employee.position || '—' }}</div>
          <div v-if="employee.office || employee.division" class="id-unit">
            {{ employee.office || employee.division }}
          </div>

          <div v-if="employee.employee_no" class="id-empno">
            <div class="id-empno-label">Employee ID Number</div>
            <div class="id-empno-value">{{ employee.employee_no }}</div>
          </div>

          <div class="id-sig-block">
            <img v-if="ocd.signature_uri" :src="ocd.signature_uri" class="id-sig-img" alt="" />
            <div class="id-sig-name">{{ ocd.name }}</div>
            <div class="id-sig-position">{{ ocd.position }}</div>
          </div>
        </div>

        <div class="id-footer-band">EMPLOYEE</div>
      </div>

      <!-- Back -->
      <div class="id-card">
        <div class="id-back-band">
          <div class="id-band-title">Employee Information</div>
        </div>

        <div class="id-card-inner">
          <div class="id-info-field">
            <div class="id-info-label">Date of Birth</div>
            <div class="id-info-value">{{ employee.date_of_birth || '—' }}</div>
          </div>
          <div v-if="employee.residential_address" class="id-info-field">
            <div class="id-info-label">Residential Address</div>
            <div class="id-info-value id-info-value-wrap">{{ employee.residential_address }}</div>
          </div>

          <div class="id-divider"></div>

          <div class="id-subheader">In Case of Emergency, Notify</div>
          <div class="id-info-field">
            <div class="id-info-label">Contact Person</div>
            <div class="id-info-value">{{ emergency.contact_name || '—' }}</div>
          </div>
          <div class="id-info-field">
            <div class="id-info-label">Mobile No.</div>
            <div class="id-info-value">{{ emergency.contact_phone || '—' }}</div>
          </div>
          <div class="id-info-field">
            <div class="id-info-label">Address</div>
            <div class="id-info-value">{{ emergency.contact_address || '—' }}</div>
          </div>

          <div class="id-divider"></div>

          <div class="id-notice">
            <div class="id-notice-title">Important</div>
            <p>This card identifies the bearer as an employee of the Philippine Science High School – Caraga Region Campus.</p>
            <p>This ID is non-transferable and must be surrendered upon separation from the service.</p>
            <p>If found, please return to PSHS-CRC, Brgy. Ampayon, Butuan City.</p>
          </div>
        </div>

        <div class="id-back-footer">
          <div v-if="qr_svg" class="id-back-qr" v-html="qr_svg"></div>
          <div class="id-back-qr-caption">Scan to verify authenticity and employment status</div>
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
  opacity: 0.2;
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
.id-republic { font-size: 5.5px; font-weight: 400; }
.id-dost { font-size: 5.5px; font-weight: 400; }
.id-school { font-size: 6.5px; font-weight: 700; text-transform: uppercase; }
.id-campus { font-size: 5.5px; font-weight: 700; text-transform: uppercase; }

.id-footer-band {
  background: linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%);
  color: #fff;
  font-size: 8px;
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
.id-band-title { font-size: 9px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
.id-back-footer {
  border-top: 1.5px solid #1447c0;
  padding: 1.5mm 3mm;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5mm;
}
.id-back-qr { width: 13mm; height: 13mm; }
.id-back-qr svg { width: 100%; height: 100%; }
.id-back-qr-caption {
  font-size: 5px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .3px;
  text-align: center;
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
.id-photo {
  width: 30mm;
  height: 30mm;
  flex-shrink: 0;
  margin-top: 1mm;
  border: 1px solid #e2e8f0;
  border-radius: 1.5mm;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
}
.id-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center 20%; }
.id-photo-empty { font-size: 6px; color: #94a3b8; }

.id-name {
  font-weight: 700;
  color: #1e293b;
  line-height: 1.3;
  margin-top: 1.5mm;
  width: 100%;
  white-space: nowrap;
}
.id-position {
  font-size: 7px;
  font-weight: 600;
  color: #1447c0;
  margin-top: 0.5mm;
}
.id-unit {
  font-size: 6px;
  color: #475569;
  margin-top: 0.2mm;
}

.id-empno { margin-top: 1mm; width: 100%; border-top: 1px solid #f1f5f9; padding-top: 1mm; }
.id-empno-label {
  font-size: 5.5px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.id-empno-value {
  font-size: 8px;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: 1px;
}

.id-sig-block {
  margin-top: auto;
  width: 100%;
  padding-top: 1mm;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.id-sig-img {
  height: 5mm;
  max-width: 80%;
  object-fit: contain;
  margin-bottom: -1.5mm;
}
.id-sig-name { font-size: 7px; font-weight: 700; color: #1e293b; }
.id-sig-position { font-size: 6px; color: #1e293b; letter-spacing: .5px; }

/* ── Back face ─────────────────────────────────────────────────── */
.id-subheader {
  width: 100%;
  font-size: 6.5px;
  font-weight: 700;
  color: #1447c0;
  text-transform: uppercase;
  letter-spacing: .5px;
  text-align: center;
  margin-bottom: 0.8mm;
}
.id-info-field { width: 100%; margin-top: 1.2mm; }
.id-info-field:first-child { margin-top: 0.5mm; }
.id-info-label {
  font-size: 6px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.id-info-value {
  font-size: 8px;
  font-weight: 600;
  color: #1e293b;
  margin-top: 0.3mm;
}
.id-info-value-wrap {
  font-size: 6.5px;
  line-height: 1.3;
  white-space: normal;
}

.id-divider { width: 100%; border-top: 1px solid #f1f5f9; margin: 1.5mm 0 1mm; }

.id-notice {
  font-size: 6px;
  color: #475569;
  line-height: 1.4;
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
