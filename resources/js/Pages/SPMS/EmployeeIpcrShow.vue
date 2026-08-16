<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useSpmsEmployeeIpcr } from '@/Composables/useSpmsEmployeeIpcr'

const props = defineProps({ ipcr: Object })
const ipcr = computed(() => props.ipcr)
const { generateTargets, submitTarget, submitForRating } = useSpmsEmployeeIpcr(ipcr)
</script>

<template>
  <Head title="IPCR Detail (SPMS)" />
  <AdminLayout title="IPCR Detail (SPMS)">
    <div class="mb-4 flex items-center justify-between">
      <div>
        <p class="text-sm text-slate-500">{{ ipcr.fiscal_period?.label }}</p>
        <p class="text-lg font-semibold">{{ ipcr.status }}</p>
      </div>
      <div class="flex gap-2">
        <button v-if="ipcr.status === 'Draft Target'" @click="generateTargets"
          class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium hover:bg-slate-50">
          Generate Targets from Load
        </button>
        <button v-if="ipcr.status === 'Draft Target'" @click="submitTarget"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Submit Target
        </button>
        <button v-if="ipcr.status === 'Target Approved'" @click="submitForRating"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Submit for Rating
        </button>
      </div>
    </div>

    <div class="space-y-3">
      <div v-for="target in ipcr.targets" :key="target.id" class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ target.function_type }}</p>
        <p class="mt-1">{{ target.success_indicator }}</p>
        <p class="mt-1 text-sm text-slate-500">Weight: {{ target.weight_pct }}%</p>
      </div>
    </div>
  </AdminLayout>
</template>
