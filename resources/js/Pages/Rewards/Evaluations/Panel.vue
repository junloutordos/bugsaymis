<template>
  <AdminLayout title="Evaluation Panel">
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Evaluation Panel</h1>
        <div class="flex gap-2">
          <button @click="setStage('screening')"
            :class="stage === 'screening'
              ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm'
              : 'bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm'"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Screening
          </button>
          <button @click="setStage('final')"
            :class="stage === 'final'
              ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm'
              : 'bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm'"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Final
          </button>
        </div>
      </div>

      <div v-if="nominations.length" class="space-y-4">
        <div v-for="n in nominations" :key="n.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <p class="font-semibold text-slate-800">{{ n.nominee?.name }}</p>
              <p class="text-sm text-slate-500">{{ n.reward_type?.name }} · {{ n.period ?? 'No period' }}</p>
              <p class="mt-1 text-xs text-slate-400 line-clamp-2">{{ n.justification }}</p>
            </div>
            <div class="flex items-center gap-4 text-sm text-slate-500">
              <div class="text-center">
                <p class="text-lg font-bold text-indigo-600">{{ n.average_score?.toFixed(1) ?? '—' }}</p>
                <p class="text-xs text-slate-500">Avg Score</p>
              </div>
              <div class="text-center">
                <p class="text-lg font-bold text-blue-600">{{ n.evaluator_count }}</p>
                <p class="text-xs text-slate-500">Evaluators</p>
              </div>
            </div>
          </div>

          <!-- My Evaluation -->
          <div class="mt-4">
            <div v-if="n.my_evaluation" class="rounded-lg bg-emerald-50 border border-emerald-100 p-3 text-sm">
              <p class="font-medium text-emerald-700">Your evaluation: {{ n.my_evaluation.score }}/100</p>
              <p v-if="n.my_evaluation.remarks" class="text-emerald-600">{{ n.my_evaluation.remarks }}</p>
              <button @click="openEdit(n)" class="mt-2 text-xs text-indigo-600 underline">Edit</button>
            </div>
            <div v-else>
              <button @click="openEval(n)"
                class="mt-2 inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Submit Evaluation
              </button>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <p class="py-16 text-center text-slate-400 text-sm">No nominations pending evaluation for this stage.</p>
      </div>
    </div>

    <!-- Eval Modal -->
    <Teleport to="body">
      <div v-if="evalModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Submit Evaluation</h2>
              <p class="text-sm text-slate-500">{{ evalTarget?.nominee?.name }} — {{ evalTarget?.reward_type?.name }}</p>
            </div>
            <button type="button" @click="evalModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <form @submit.prevent="submitEval">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Score (0–100) <span class="text-red-500">*</span></label>
                <input type="number" v-model="evalForm.score" min="0" max="100" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                <textarea v-model="evalForm.remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="3" />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <button type="button" @click="evalModal = false"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit" :disabled="evalForm.processing"
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
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  nominations: Array,
  stage: String,
})

function setStage(s) {
  router.get(route('rewards.evaluations.panel'), { stage: s }, { preserveState: false })
}

const evalModal = ref(false)
const evalTarget = ref(null)
const editingEval = ref(null)

const evalForm = useForm({
  score: '',
  remarks: '',
  evaluation_stage: props.stage,
})

function openEval(n) {
  evalTarget.value = n
  editingEval.value = null
  evalForm.reset()
  evalForm.evaluation_stage = props.stage
  evalModal.value = true
}

function openEdit(n) {
  evalTarget.value = n
  editingEval.value = n.my_evaluation
  evalForm.score = n.my_evaluation.score
  evalForm.remarks = n.my_evaluation.remarks ?? ''
  evalForm.evaluation_stage = props.stage
  evalModal.value = true
}

function submitEval() {
  if (editingEval.value) {
    evalForm.patch(route('rewards.evaluations.update', editingEval.value.id), {
      onSuccess: () => { evalModal.value = false },
    })
  } else {
    evalForm.post(route('rewards.evaluations.store', evalTarget.value.id), {
      onSuccess: () => { evalModal.value = false },
    })
  }
}
</script>
