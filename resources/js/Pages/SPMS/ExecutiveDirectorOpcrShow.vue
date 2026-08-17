<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useSpmsExecutiveDirectorOpcr } from '@/Composables/useSpmsExecutiveDirectorOpcr'

const props = defineProps({ opcr: Object })
const opcr = computed(() => props.opcr)
const { approve, returnToSender } = useSpmsExecutiveDirectorOpcr(opcr)

const returnReason = ref('')
</script>

<template>
  <Head title="Approve OPCR (SPMS)" />
  <AdminLayout title="Approve OPCR (SPMS)">
    <p class="mb-4 text-sm text-slate-500">
      {{ opcr.ratee?.name }} — {{ opcr.fiscal_period?.label }} — {{ opcr.status }}
    </p>

    <p v-if="opcr.rolled_up_rating" class="mb-2 text-sm">Rolled-up rating (computed): <strong>{{ opcr.rolled_up_rating }}</strong></p>

    <div v-if="opcr.override_rating" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
      <p class="font-semibold text-amber-800">Campus Director set an override rating: {{ opcr.override_rating }}</p>
      <p class="mt-1 text-amber-700">Reason: {{ opcr.override_reason }}</p>
      <p class="mt-1 text-amber-700">This value — not the computed rollup — will become the final rating if approved as-is. Review the reason before approving.</p>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
      <button v-if="opcr.status === 'Submitted to Executive Director'" @click="approve"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Approve (Executive Director)
      </button>
    </div>

    <div v-if="opcr.status === 'Submitted to Executive Director'" class="flex gap-2">
      <input v-model="returnReason" placeholder="Return reason" class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-full" />
      <button @click="returnToSender(returnReason)"
        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium hover:bg-slate-50 whitespace-nowrap">
        Return to Campus Director
      </button>
    </div>

    <div class="mt-4 space-y-3">
      <div v-for="target in opcr.targets" :key="target.id" class="rounded-lg border border-slate-200 bg-white p-4">
        <p>{{ target.performance_indicator?.description }}</p>
        <p class="text-sm text-slate-500">Q1: {{ target.q1_actual ?? '—' }} · Q2: {{ target.q2_actual ?? '—' }} · Q3: {{ target.q3_actual ?? '—' }} · Q4: {{ target.q4_actual ?? '—' }}</p>
      </div>
    </div>
  </AdminLayout>
</template>
