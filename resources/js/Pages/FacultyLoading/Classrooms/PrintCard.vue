<script setup>
import { Head } from '@inertiajs/vue3'
import { PrinterIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cards: Array,
  skippedCount: { type: Number, default: 0 },
  skippedNames: { type: Array, default: () => [] },
})

function printNow() {
  window.print()
}
</script>

<template>
  <Head title="Print NFC Classroom Cards" />

  <!-- ══════════════════════════════════════════════════════════════════
       Screen view — summary + print trigger, screen only
  ═══════════════════════════════════════════════════════════════════ -->
  <div class="print:hidden min-h-screen bg-slate-50 flex flex-col items-center px-4 py-10">
    <div class="w-full max-w-md text-center">
      <p class="text-xs font-semibold text-indigo-600 uppercase tracking-widest mb-2">PSHS-CRC · Teacher Attendance</p>
      <h1 class="text-xl font-bold text-slate-800 mb-1">
        {{ cards.length }} classroom card{{ cards.length === 1 ? '' : 's' }} ready to print
      </h1>
      <p class="text-sm text-slate-500 mb-6">
        Load blank CR-80 NFC cards into the Matica XID8300 and print single-sided — the back gets glued to the table.
      </p>

      <div v-if="skippedCount > 0" class="bg-amber-50 border border-amber-100 text-amber-700 rounded-lg px-4 py-3 text-sm text-left mb-6">
        <div class="flex items-start gap-2">
          <ExclamationTriangleIcon class="h-4 w-4 shrink-0 mt-0.5" />
          <div>
            <p class="font-medium">{{ skippedCount }} room{{ skippedCount === 1 ? '' : 's' }} skipped — no NFC UUID assigned yet</p>
            <p class="text-xs mt-1 text-amber-600">{{ skippedNames.join(', ') }}</p>
          </div>
        </div>
      </div>

      <button
        v-if="cards.length"
        @click="printNow"
        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium"
      >
        <PrinterIcon class="h-4 w-4" /> Print
      </button>
      <p v-else class="text-sm text-slate-400">No printable cards — assign an NFC UUID first.</p>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════════════
       Print view — single-sided CR-80 (54mm × 86mm), one card per room.
       Never rendered on screen, print output only.
  ═══════════════════════════════════════════════════════════════════ -->
  <div class="print-page">
    <div
      v-for="card in cards"
      :key="card.code"
      class="classroom-card"
    >
      <div class="card-band">
        <img src="/images/pshslogo.png" class="card-logo" alt="" onerror="this.style.display='none'" />
        <div class="card-band-text">
          <div class="card-school">PSHS-CRC</div>
          <div class="card-tagline">Teacher Attendance</div>
        </div>
      </div>

      <div class="card-body">
        <div class="card-room-name">{{ card.name }}</div>
        <div class="card-room-code">{{ card.code }}</div>
        <div v-if="card.building" class="card-room-meta">
          {{ card.building }}<template v-if="card.floor">, Floor {{ card.floor }}</template>
        </div>

        <div class="tap-zone">
          <div class="tap-rings">
            <div class="tap-ring r1"></div>
            <div class="tap-ring r2"></div>
            <div class="tap-ring r3"></div>
            <img src="/images/atlas-mark.png" class="tap-icon" alt="" />
          </div>
          <div class="tap-caption">Tap Phone Here</div>
          <div class="tap-sub">to record attendance</div>
        </div>

        <div class="card-divider"></div>

        <div class="card-qr-block">
          <div class="card-qr" v-html="card.qrSvg"></div>
          <div class="card-qr-caption">or scan if tap fails</div>
        </div>
      </div>

      <div class="card-footer-band">Classroom Attendance</div>
    </div>
  </div>
</template>

<style>
* { box-sizing: border-box; }

.print-page { display: none; }

/* CR-80 card = 54mm x 86mm (portrait), single-sided */
.classroom-card {
  width: 54mm;
  height: 86mm;
  background: #fff;
  border-radius: 2.5mm;
  box-shadow: 0 4px 16px rgba(0,0,0,.12);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.card-band {
  background: linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%);
  color: #fff;
  display: flex;
  align-items: center;
  gap: 1.5mm;
  padding: 1.5mm 2mm;
}
.card-logo { width: 9mm; height: 9mm; object-fit: contain; flex-shrink: 0; }
.card-band-text { line-height: 1.25; }
.card-school { font-size: 7px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
.card-tagline { font-size: 5.5px; text-transform: uppercase; letter-spacing: .5px; color: rgba(255,255,255,.8); }

.card-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 3mm 3mm 1.5mm;
  text-align: center;
  overflow: hidden;
}

.card-room-name { font-size: 11px; font-weight: 800; color: #1e293b; line-height: 1.1; margin-top: 1mm; }
.card-room-code {
  font-family: "SF Mono", ui-monospace, Menlo, monospace;
  font-size: 6px; font-weight: 700; letter-spacing: .5px;
  color: #1447c0; background: #eef3ff; padding: 0.6mm 2mm; border-radius: 3mm; margin-top: 1mm;
}
.card-room-meta { font-size: 5.5px; color: #475569; margin-top: 0.8mm; }

.tap-zone { display: flex; flex-direction: column; align-items: center; margin-top: 2.5mm; }
.tap-rings { position: relative; width: 24mm; height: 24mm; display: flex; align-items: center; justify-content: center; }
.tap-ring { position: absolute; border-radius: 50%; border: 0.3mm solid #1447c0; }
.tap-ring.r1 { width: 24mm; height: 24mm; opacity: .16; }
.tap-ring.r2 { width: 17mm; height: 17mm; opacity: .30; }
.tap-ring.r3 { width: 11mm; height: 11mm; opacity: .55; }
.tap-icon { position: relative; z-index: 2; height: 8mm; width: auto; }
.tap-caption { font-size: 6px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; color: #1447c0; margin-top: 1mm; }
.tap-sub { font-size: 5px; color: #94a3b8; }

.card-divider { width: 60%; height: 0.2mm; background: #e6eaf2; margin: 2mm 0 1.5mm; }

.card-qr-block { display: flex; flex-direction: column; align-items: center; margin-top: auto; }
.card-qr { width: 11mm; height: 11mm; }
.card-qr svg { width: 100%; height: 100%; }
.card-qr-caption { font-size: 5px; color: #94a3b8; text-transform: uppercase; letter-spacing: .3px; margin-top: 0.6mm; }

.card-footer-band {
  background: linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%);
  color: #fff;
  font-size: 6.5px;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  text-align: center;
  padding: 1.2mm 0;
}

@media print {
  @page { size: 54mm 86mm; margin: 0; }
  html, body { margin: 0; padding: 0; background: #fff; }
  .print-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 100vh;
    padding: 0;
  }
  .classroom-card {
    box-shadow: none;
    border-radius: 0;
    width: 54mm;
    height: 86mm;
    page-break-after: always;
  }
  .classroom-card:last-child { page-break-after: auto; }
}
</style>
