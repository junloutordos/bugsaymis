<template>
  <AdminLayout title="Evaluation Panel">
    <div class="space-y-6">
      <AppPageHeader title="Evaluation Panel">
        <template #actions>
          <AppButton :variant="stage === 'screening' ? 'primary' : 'secondary'" @click="setStage('screening')">Screening</AppButton>
          <AppButton :variant="stage === 'final' ? 'primary' : 'secondary'" @click="setStage('final')">Final</AppButton>
        </template>
      </AppPageHeader>

      <div v-if="nominations.length" class="space-y-4">
        <div v-for="n in nominations" :key="n.id"
          class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-5">
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
            <div v-if="n.my_evaluation" class="rounded-lg bg-success-50 border border-success-100 p-3 text-sm">
              <p class="font-medium text-success-700">Your evaluation: {{ n.my_evaluation.score }}/100</p>
              <p v-if="n.my_evaluation.remarks" class="text-success-600">{{ n.my_evaluation.remarks }}</p>
              <button @click="openEdit(n)" class="mt-2 text-xs text-indigo-600 underline">Edit</button>
            </div>
            <div v-else>
              <AppButton class="mt-2" @click="openEval(n)">Submit Evaluation</AppButton>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <EmptyState title="No nominations pending evaluation for this stage" />
      </div>
    </div>

    <!-- Eval Modal -->
    <AppModal :show="evalModal" title="Submit Evaluation" :subtitle="evalTarget ? `${evalTarget.nominee?.name} — ${evalTarget.reward_type?.name}` : ''" size="lg" @close="evalModal = false">
      <form @submit.prevent="submitEval" id="eval-form">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Score (0–100) <span class="text-danger-500">*</span></label>
            <input type="number" v-model="evalForm.score" min="0" max="100" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="evalForm.remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="3" />
          </div>
        </div>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="evalModal = false">Cancel</AppButton>
        <AppButton type="submit" form="eval-form" :loading="evalForm.processing">Submit</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'

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
