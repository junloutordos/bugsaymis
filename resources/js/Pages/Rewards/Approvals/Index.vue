<template>
  <AdminLayout title="Approvals">
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Approval Panel</h1>
        <div class="flex gap-2">
          <button @click="setLevel('committee')"
            :class="level === 'committee'
              ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm'
              : 'bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm'"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Committee
          </button>
          <button @click="setLevel('head_of_office')"
            :class="level === 'head_of_office'
              ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm'
              : 'bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm'"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Head of Office
          </button>
        </div>
      </div>

      <div v-if="nominations.length" class="space-y-4">
        <div v-for="n in nominations" :key="n.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <p class="font-semibold text-slate-800">{{ n.nominee?.name }}</p>
              <p class="text-sm text-slate-500">
                {{ n.reward_type?.name }}
                <span v-if="n.period"> · {{ n.period }}</span>
              </p>
              <p class="mt-1 text-xs text-slate-400 line-clamp-3">{{ n.justification }}</p>
            </div>
            <Link :href="route('rewards.nominations.show', n.id)"
              class="text-xs text-indigo-600 hover:text-indigo-700 font-medium whitespace-nowrap">
              Full Details →
            </Link>
          </div>

          <!-- My decision status -->
          <div class="mt-4">
            <div v-if="n.my_approval" class="rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm">
              <p class="font-medium capitalize" :class="decisionColor(n.my_approval.decision)">
                Your decision: {{ n.my_approval.decision ?? 'Pending' }}
              </p>
              <p v-if="n.my_approval.remarks" class="text-slate-600">{{ n.my_approval.remarks }}</p>
              <button @click="openDecide(n, true)"
                class="mt-2 text-xs text-indigo-600 hover:text-indigo-700 underline">Change Decision</button>
            </div>
            <button v-else @click="openDecide(n, false)"
              class="mt-2 inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Make Decision
            </button>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <p class="py-16 text-center text-slate-400 text-sm">No nominations pending approval at this level.</p>
      </div>
    </div>

    <!-- Decision Modal -->
    <Teleport to="body">
      <div v-if="decideModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-800">
                {{ level === 'committee' ? 'Committee' : 'Head of Office' }} Decision
              </h2>
              <p class="text-sm text-slate-500">{{ decideTarget?.nominee?.name }}</p>
            </div>
            <button type="button" @click="decideModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <form @submit.prevent="submitDecide">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Decision <span class="text-red-500">*</span></label>
                <select v-model="decideForm.decision" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                  <option value="deferred">Deferred</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                <textarea v-model="decideForm.remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="3" />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <button type="button" @click="decideModal = false"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit" :disabled="decideForm.processing"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
                Submit
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  nominations: Array,
  level: String,
})

function setLevel(l) {
  router.get(route('rewards.approvals.index'), { level: l }, { preserveState: false })
}

const decideModal = ref(false)
const decideTarget = ref(null)

const decideForm = useForm({
  level: props.level,
  decision: 'approved',
  remarks: '',
})

function openDecide(n) {
  decideTarget.value = n
  decideForm.level = props.level
  decideForm.decision = n.my_approval?.decision ?? 'approved'
  decideForm.remarks = n.my_approval?.remarks ?? ''
  decideModal.value = true
}

function submitDecide() {
  decideForm.post(route('rewards.approvals.decide', decideTarget.value.id), {
    onSuccess: () => { decideModal.value = false },
  })
}

function decisionColor(d) {
  return {
    approved: 'text-emerald-700',
    rejected: 'text-red-600',
    deferred: 'text-amber-700',
  }[d] ?? 'text-slate-600'
}
</script>
