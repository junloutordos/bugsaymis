<template>
  <Head :title="documentTitle" />

  <SchedulePrintSheet
    :schedule-type="scheduleType"
    :owner="owner"
    :term="term"
    :schedules="schedules"
    :day-configs="dayConfigs"
    :signatories="signatories"
    :load-summary="loadSummary"
    :official-times="officialTimes"
  />
</template>

<script setup>
import { computed, nextTick, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import SchedulePrintSheet from '@/Components/FacultyLoading/SchedulePrintSheet.vue'

const props = defineProps({
  scheduleType: { type: String, required: true },
  owner: { type: Object, required: true },
  term: { type: Object, required: true },
  schedules: { type: Array, default: () => [] },
  dayConfigs: { type: Object, default: () => ({}) },
  signatories: { type: Object, default: null },
  loadSummary: { type: String, default: null },
  officialTimes: { type: Object, default: () => ({}) },
})

const documentTitle = computed(() => {
  if (props.scheduleType === 'section') {
    return `GRADE ${props.owner.grade_level} ${props.owner.name?.toUpperCase() ?? ''} CLASS SCHEDULE`
  }
  return 'INDIVIDUAL FACULTY SCHEDULE'
})

onMounted(async () => {
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

@page {
  size: A4 landscape;
  margin: 0;
}

@media print {
  html,
  body {
    width: 297mm;
    height: 210mm;
    margin: 0;
    padding: 0;
    overflow: hidden;
    background: #fff;
  }

  .schedule-print-sheet {
    break-after: avoid;
    break-inside: avoid;
    page-break-after: avoid;
    page-break-inside: avoid;
  }
}
</style>
