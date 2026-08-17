<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useSpmsReviewerDpcr } from '@/Composables/useSpmsReviewerDpcr'

const props = defineProps({ dpcr: Object })
const dpcr = computed(() => props.dpcr)
const { review, submitToApprover, approve, returnToSender } = useSpmsReviewerDpcr(dpcr)

const returnReason = ref('')
</script>

<template>
  <Head title="Review DPCR (SPMS)" />
  <AdminLayout title="Review DPCR (SPMS)">
    <p class="mb-4 text-sm text-slate-500">
      {{ dpcr.division?.division_name }} — {{ dpcr.ratee?.name }} — {{ dpcr.fiscal_period?.label }} — {{ dpcr.status }}
    </p>

    <p v-if="dpcr.rolled_up_rating" class="mb-2 text-sm">Rolled-up rating (computed): <strong>{{ dpcr.rolled_up_rating }}</strong></p>

    <div v-if="dpcr.override_rating" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
      <p class="font-semibold text-amber-800">Division Chief set an override rating: {{ dpcr.override_rating }}</p>
      <p class="mt-1 text-amber-700">Reason: {{ dpcr.override_reason }}</p>
      <p class="mt-1 text-amber-700">This value — not the computed rollup — will become the final rating if approved as-is. Review the reason before approving.</p>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
      <button v-if="dpcr.status === 'Submitted to Reviewer'" @click="review"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Review (Compute Rollup)
      </button>
      <button v-if="dpcr.status === 'Reviewed'" @click="submitToApprover"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Submit to Approver
      </button>
      <button v-if="dpcr.status === 'Submitted to Approver'" @click="approve"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Approve (Head of Office)
      </button>
    </div>

    <div v-if="['Submitted to Reviewer', 'Submitted to Approver'].includes(dpcr.status)" class="flex gap-2">
      <input v-model="returnReason" placeholder="Return reason" class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-full" />
      <button @click="returnToSender(returnReason)"
        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium hover:bg-slate-50 whitespace-nowrap">
        Return to Division Chief
      </button>
    </div>

    <div class="mt-4 space-y-3">
      <div v-for="target in dpcr.targets" :key="target.id" class="rounded-lg border border-slate-200 bg-white p-4">
        <p>{{ target.performance_indicator?.description }}</p>
        <p class="text-sm text-slate-500">Q1: {{ target.q1_actual ?? '—' }} · Q2: {{ target.q2_actual ?? '—' }} · Q3: {{ target.q3_actual ?? '—' }} · Q4: {{ target.q4_actual ?? '—' }}</p>
      </div>
    </div>
  </AdminLayout>
</template>
