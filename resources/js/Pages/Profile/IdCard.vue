<script setup>
import { computed, onMounted, nextTick, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, ArrowPathIcon, IdentificationIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage.js'

const props = defineProps({
  employee:   Object,
  ids:        Object,
  qr_svg:     String,
  verify_url: String,
  ocd:        Object,
})

const photoUrl = computed(() => storageUrl(props.employee.profile_picture))

const initials = computed(() => {
  const parts = (props.employee.name || '').split(',').map(s => s.trim()).filter(Boolean)
  if (parts.length >= 2) return (parts[1][0] || '') + (parts[0][0] || '')
  const words = (parts[0] || '').split(/\s+/).filter(Boolean)
  return words.length >= 2 ? words[0][0] + words[1][0] : (words[0]?.[0] || '')
})

const idRows = computed(() => [
  { label: 'Employee No.',  value: props.employee.employee_no },
  { label: 'TIN',           value: props.ids.tin },
  { label: 'PhilHealth',    value: props.ids.philhealth },
  { label: 'Pag-IBIG',      value: props.ids.pagibig },
  { label: 'PhilSys',       value: props.ids.philsys },
  { label: 'Blood Type',    value: props.ids.blood_type },
].filter(r => r.value))

function printCard() {
  window.print()
}

// ── Digital (screen) view: tap-to-flip card ────────────────────────────────
const isFlipped = ref(false)

// ── Print (CR-80) view: fit the name to one line on the fixed-width card ───
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
  const textWidth = ctx.measureText(props.employee.name).width

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
  <Head title="Employee Digital ID" />

  <!-- ══════════════════════════════════════════════════════════════════
       Digital view — mobile wallet-style card, screen only
  ═══════════════════════════════════════════════════════════════════ -->
  <div class="print:hidden min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 px-4 py-8">
    <div class="mx-auto mb-6 flex w-full max-w-sm items-center justify-between">
      <Link :href="route('profile.edit')" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-300 hover:text-white">
        <ArrowLeftIcon class="h-4 w-4" /> Back to Profile
      </Link>
      <span class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Digital ID</span>
    </div>

    <!-- Flip card -->
    <div class="mx-auto w-full max-w-sm" style="perspective: 1600px">
      <div
        class="relative w-full cursor-pointer transition-transform duration-700 ease-out"
        style="transform-style: preserve-3d"
        :style="{ transform: isFlipped ? 'rotateY(180deg)' : 'rotateY(0deg)' }"
        @click="isFlipped = !isFlipped"
      >
        <!-- Front face -->
        <div class="w-full [backface-visibility:hidden]">
          <div class="relative overflow-hidden rounded-t-3xl bg-gradient-to-r from-indigo-600 to-blue-500 px-6 pb-16 pt-6">
            <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-4 -left-8 h-32 w-32 rounded-full bg-white/5"></div>

            <div class="relative z-10 flex items-center justify-between">
              <div class="flex items-center gap-1.5">
                <img src="/images/pshslogo.png" class="h-6 w-6 object-contain" alt="" onerror="this.style.display='none'" />
                <span class="text-[11px] font-semibold uppercase tracking-widest text-indigo-100">PSHS&ndash;CRC</span>
              </div>
              <span
                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide backdrop-blur"
                :class="employee.is_active ? 'border-white/30 bg-white/15 text-white' : 'border-white/20 bg-black/20 text-white/70'"
              >
                <span class="h-1.5 w-1.5 rounded-full" :class="employee.is_active ? 'bg-emerald-300' : 'bg-slate-300'"></span>
                {{ employee.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>

            <div class="relative z-10 mt-5 flex justify-center">
              <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-4 border-white/30 bg-white/20 shadow-2xl backdrop-blur">
                <img v-if="photoUrl" :src="photoUrl" class="h-full w-full object-cover" style="object-position: center 20%" alt="" />
                <span v-else class="select-none text-2xl font-bold text-white">{{ initials }}</span>
              </div>
            </div>
          </div>

          <div class="-mt-8 rounded-b-3xl bg-white px-6 pb-6 pt-10 shadow-2xl">
            <div class="mb-5 text-center">
              <h1 class="break-words text-xl font-bold leading-snug tracking-tight text-slate-800">{{ employee.name }}</h1>
              <p class="mt-1 text-sm font-semibold text-indigo-600">{{ employee.position || '—' }}</p>
              <p v-if="employee.office || employee.division" class="mt-1 text-xs text-slate-500">
                {{ employee.office || employee.division }}
              </p>
            </div>

            <div v-if="employee.employee_no" class="mb-5 rounded-xl bg-slate-50 px-4 py-2.5 text-center">
              <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Employee Number</p>
              <p class="mt-0.5 text-sm font-bold tracking-wider text-slate-800">{{ employee.employee_no }}</p>
            </div>

            <div class="flex flex-col items-center">
              <div class="rounded-2xl border border-slate-100 bg-white p-3 [&>svg]:h-32 [&>svg]:w-32" v-html="qr_svg"></div>
              <p class="mt-2 text-[11px] font-medium uppercase tracking-wide text-slate-400">Scan to verify</p>
            </div>

            <button type="button" class="mt-5 flex w-full items-center justify-center gap-1.5 text-xs font-medium text-indigo-600" @click.stop="isFlipped = true">
              <ArrowPathIcon class="h-3.5 w-3.5" /> Tap to view ID details
            </button>
          </div>
        </div>

        <!-- Back face -->
        <div class="absolute inset-0 flex h-full w-full flex-col [backface-visibility:hidden]" style="transform: rotateY(180deg)">
          <div class="relative overflow-hidden rounded-t-3xl bg-gradient-to-r from-indigo-600 to-blue-500 px-6 py-4 text-center">
            <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/5"></div>
            <span class="relative z-10 text-xs font-semibold uppercase tracking-widest text-white">Government ID Numbers</span>
          </div>

          <div class="flex flex-1 flex-col rounded-b-3xl bg-white px-6 py-5 shadow-2xl">
            <div class="space-y-3">
              <div v-for="row in idRows" :key="row.label" class="flex items-center gap-3">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-50">
                  <IdentificationIcon class="h-4 w-4 text-indigo-600" />
                </div>
                <div class="min-w-0">
                  <p class="mb-0.5 text-xs leading-none text-slate-400">{{ row.label }}</p>
                  <p class="truncate text-sm font-medium text-slate-700">{{ row.value }}</p>
                </div>
              </div>
              <p v-if="!idRows.length" class="text-xs text-slate-400">
                Government ID numbers appear here once encoded in your PDS.
              </p>
            </div>

            <div class="my-4 border-t border-slate-100"></div>

            <p class="text-center text-[11px] leading-relaxed text-slate-500">
              This card identifies the bearer as an employee of the Philippine Science High School – Caraga
              Region Campus. Non-transferable; must be surrendered upon separation from the service.
            </p>

            <button type="button" class="mt-auto flex w-full items-center justify-center gap-1.5 pt-4 text-xs font-medium text-indigo-600" @click.stop="isFlipped = false">
              <ArrowPathIcon class="h-3.5 w-3.5" /> Tap to view front
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="mx-auto mt-6 flex w-full max-w-sm flex-col items-center gap-3 text-center">
      <button
        @click="printCard"
        class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/15"
      >
        <PrinterIcon class="h-4 w-4" /> Print ID Card
      </button>
      <p class="max-w-xs text-[11px] leading-relaxed text-slate-400">
        Load a blank CR-80 card into the Matica XID8300 and enable dual-sided / duplex printing in the printer
        driver — this sends both the front and back as a single print job onto one physical card. The QR code
        links to a live verification page showing your current employment status.
      </p>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════════════
       Print view — exact CR-80 (54mm × 86mm) layout for the Matica
       XID8300, unchanged. Never rendered on screen, print output only.
  ═══════════════════════════════════════════════════════════════════ -->
  <div class="print-page">
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

          <div ref="nameEl" class="id-name" :style="{ fontSize: nameFontSize + 'px' }">{{ employee.name }}</div>
          <div class="id-position">{{ employee.position || '—' }}</div>
          <div v-if="employee.office || employee.division" class="id-unit">
            {{ employee.office || employee.division }}
          </div>

          <div v-if="employee.employee_no" class="id-empno">
            <div class="id-empno-label">Employee Number</div>
            <div class="id-empno-value">{{ employee.employee_no }}</div>
          </div>

          <div class="id-qr id-qr-front" v-html="qr_svg"></div>
          <div class="id-qr-caption">Scan to verify</div>

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
          <div v-for="row in idRows" :key="row.label" class="id-info-field">
            <div class="id-info-label">{{ row.label }}</div>
            <div class="id-info-value">{{ row.value }}</div>
          </div>
          <p v-if="!idRows.length" class="id-info-empty">
            Government ID numbers appear here once encoded in your PDS.
          </p>

          <div class="id-divider"></div>

          <div class="id-notice">
            <div class="id-notice-title">Important</div>
            <p>This card identifies the bearer as an employee of the Philippine Science High School – Caraga Region Campus.</p>
            <p>This ID is non-transferable and must be surrendered upon separation from the service.</p>
            <p>If found, please return to PSHS-CRC, Brgy. Ampayon, Butuan City.</p>
          </div>

          <div class="id-verify-block">
            <div class="id-qr id-qr-back" v-html="qr_svg"></div>
            <div class="id-verify-caption">Scan to verify authenticity and employment status</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { background: #0f172a; }

/* ── Print-only wrapper: hidden on screen, shown only in @media print ─── */
.print-page { display: none; }

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

/* ── Back header band ─────────────────────────────────────────── */
.id-back-band {
  text-align: center;
  color: #1447c0;
  padding: 1.5mm 2mm;
  border-bottom: 1.5px solid #1447c0;
}
.id-band-title { font-size: 9px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }

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
  width: 24mm;
  height: 24mm;
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

.id-empno { margin-top: 1mm; width: 100%; }
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

.id-qr { display: flex; align-items: center; justify-content: center; }
.id-qr svg { width: 100%; height: 100%; }
.id-qr-front { width: 13mm; height: 13mm; margin-top: 1mm; }
.id-qr-caption {
  font-size: 5.5px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
  margin-top: 0.4mm;
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
.id-info-empty { font-size: 6px; color: #94a3b8; margin-top: 1mm; }

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

.id-verify-block {
  margin-top: auto;
  width: 100%;
  padding-top: 1.5mm;
  border-top: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.id-qr-back { width: 16mm; height: 16mm; }
.id-verify-caption {
  font-size: 5.5px;
  color: #94a3b8;
  margin-top: 0.6mm;
  text-transform: uppercase;
  letter-spacing: .3px;
}

/* ── Print ─────────────────────────────────────────────────────── */
@media print {
  @page { size: 54mm 86mm; margin: 0; }
  html, body { margin: 0; padding: 0; background: #fff; }
  .print-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 100vh;
    padding: 0;
    gap: 0;
  }
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
