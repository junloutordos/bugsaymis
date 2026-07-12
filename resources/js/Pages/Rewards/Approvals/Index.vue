<template>
  <AdminLayout title="Approvals">
    <div class="space-y-6">
      <AppPageHeader title="Approval Panel">
        <template #actions>
          <AppButton :variant="level === 'committee' ? 'primary' : 'secondary'" @click="setLevel('committee')">Committee</AppButton>
          <AppButton :variant="level === 'head_of_office' ? 'primary' : 'secondary'" @click="setLevel('head_of_office')">Head of Office</AppButton>
        </template>
      </AppPageHeader>

      <div v-if="nominations.length" class="space-y-4">
        <div v-for="n in nominations" :key="n.id"
          class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-5">
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
            <AppButton v-else size="sm" class="mt-2" @click="openDecide(n, false)">Make Decision</AppButton>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <EmptyState title="No nominations pending approval at this level" />
      </div>
    </div>

    <!-- Decision Modal -->
    <AppModal :show="decideModal" :title="`${level === 'committee' ? 'Committee' : 'Head of Office'} Decision`" :subtitle="decideTarget?.nominee?.name" size="md" @close="decideModal = false">
      <form @submit.prevent="submitDecide" id="decide-form">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Decision <span class="text-danger-500">*</span></label>
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
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="decideModal = false">Cancel</AppButton>
        <AppButton type="submit" form="decide-form" :loading="decideForm.processing">Submit</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'

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
    approved: 'text-success-700',
    rejected: 'text-danger-600',
    deferred: 'text-warning-700',
  }[d] ?? 'text-slate-600'
}
</script>
