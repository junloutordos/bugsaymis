<template>
  <Head :title="documentTitle" />

  <div v-if="!sheets.length" class="schedule-batch-empty">
    No schedules to print for this term.
  </div>

  <SchedulePrintSheet
    v-for="sheet in mergedSheets"
    :key="sheet.key"
    v-bind="sheet.props"
  />
</template>

<script setup>
import { computed, nextTick, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import SchedulePrintSheet from '@/Components/FacultyLoading/SchedulePrintSheet.vue'

const props = defineProps({
  scheduleType: { type: String, required: true },
  term: { type: Object, required: true },
  /** [{ owner, schedules, dayConfigs, loadSummary?, officialTimes?, conforme? }, ...] */
  sheets: { type: Array, default: () => [] },
  /** { batch_status, prepared, approved } — identical for every sheet, sent once. */
  sharedSignatories: { type: Object, default: null },
})

const documentTitle = computed(() =>
  `${props.scheduleType === 'section' ? 'CLASS' : 'FACULTY'} SCHEDULES — ${props.term.label ?? ''}`)

const mergedSheets = computed(() => props.sheets.map((sheet, index) => ({
  key: `${props.scheduleType}-${sheet.owner?.id ?? index}`,
  props: {
    scheduleType: props.scheduleType,
    owner: sheet.owner,
    term: props.term,
    schedules: sheet.schedules ?? [],
    dayConfigs: sheet.dayConfigs ?? {},
    loadSummary: sheet.loadSummary ?? null,
    officialTimes: sheet.officialTimes ?? {},
    signatories: { ...(props.sharedSignatories ?? {}), conforme: sheet.conforme ?? null },
  },
})))

onMounted(async () => {
  if (!props.sheets.length) return
  await nextTick()
  const images = [...document.querySelectorAll('.print-asset')]
  await Promise.all(images.map(image => image.complete ? Promise.resolve() : new Promise(resolve => {
    image.addEventListener('load', resolve, { once: true })
    image.addEventListener('error', resolve, { once: true })
  })))
  await document.fonts?.ready
  setTimeout(() => window.print(), 100)
})
</script>

<style>
* {
  box-sizing: border-box;
}

html,
body {
  margin: 0;
  padding: 0;
  background: #d1d5db;
}

.schedule-batch-empty {
  padding: 24mm 0;
  font-family: 'Helvetica Neue', Arial, Helvetica, sans-serif;
  font-size: 11pt;
  color: #475569;
  text-align: center;
}

/* Screen preview: stack the sheets with a visible gap between pages. */
@media screen {
  .schedule-print-sheet {
    margin: 4mm auto;
    box-shadow: 0 1mm 3mm rgba(15, 23, 42, 0.25);
  }
}

@page {
  size: A4 landscape;
  margin: 0;
}

@media print {
  html,
  body {
    width: 297mm;
    margin: 0;
    padding: 0;
    background: #fff;
  }

  /* One sheet per page. */
  .schedule-print-sheet {
    break-inside: avoid;
    page-break-inside: avoid;
    break-after: page;
    page-break-after: always;
  }

  .schedule-print-sheet:last-of-type {
    break-after: avoid;
    page-break-after: avoid;
  }
}
</style>
