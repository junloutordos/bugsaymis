<script setup>
import { router } from "@inertiajs/vue3"

const props = defineProps({
  fiscalYears: { type: Array, default: () => [] },   // e.g. [2027, 2026, 2025]
  selected:    { type: [String, Number], default: "" }, // current year or 'all'
  routeName:   { type: String, required: true },
})

const onChange = (event) => {
  router.get(route(props.routeName), { fiscal_year: event.target.value }, { preserveScroll: true })
}
</script>

<template>
  <select
    :value="String(selected)"
    @change="onChange"
    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
  >
    <option v-for="y in fiscalYears" :key="y" :value="String(y)">FY {{ y }}</option>
    <option value="all">All years</option>
  </select>
</template>
