<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useSpmsCampusDirectorOpcr } from '@/Composables/useSpmsCampusDirectorOpcr'

const props = defineProps({ opcr: Object })
const opcr = computed(() => props.opcr)
const { generateTargets, updateTargets, submitToExecutiveDirector } = useSpmsCampusDirectorOpcr(opcr)

const edits = reactive({})

const saveAccomplishments = () => {
  updateTargets(edits)
}
</script>

<template>
  <Head title="OPCR Detail (SPMS)" />
  <AdminLayout title="OPCR Detail (SPMS)">
    <div class="mb-4 flex items-center justify-between">
      <div>
        <p class="text-sm text-slate-500">{{ opcr.fiscal_period?.label }}</p>
        <p class="text-lg font-semibold">{{ opcr.status }}</p>
      </div>
      <div class="flex gap-2">
        <button v-if="opcr.status === 'Draft'" @click="generateTargets"
          class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium hover:bg-slate-50">
          Generate Targets from Performance Indicators
        </button>
        <button v-if="opcr.status === 'Draft'" @click="submitToExecutiveDirector"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Submit to Executive Director
        </button>
      </div>
    </div>

    <div class="space-y-3">
      <div v-for="target in opcr.targets" :key="target.id" class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="mb-2">{{ target.performance_indicator?.description }}</p>
        <div class="grid grid-cols-4 gap-2">
          <input v-for="q in ['q1_actual', 'q2_actual', 'q3_actual', 'q4_actual']" :key="q"
            :placeholder="q.toUpperCase()" type="number" step="0.01"
            class="rounded-lg border border-slate-200 px-2 py-1 text-sm"
            @input="edits[target.id] = { ...edits[target.id], [q]: $event.target.valueAsNumber }" />
        </div>
        <textarea placeholder="Remarks" class="mt-2 w-full rounded-lg border border-slate-200 px-2 py-1 text-sm"
          @input="edits[target.id] = { ...edits[target.id], remarks: $event.target.value }" />
      </div>
      <button v-if="opcr.targets?.length" @click="saveAccomplishments"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Save Accomplishments
      </button>
    </div>
  </AdminLayout>
</template>
