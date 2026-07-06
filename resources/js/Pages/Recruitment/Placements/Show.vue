<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  placement: { type: Object, required: true },
})

const page = usePage()

// ── Helpers ────────────────────────────────────────────────────────────────────
const formatDate = (iso) => iso
  ? new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

// ── Task progress ──────────────────────────────────────────────────────────────
const tasks = computed(() => props.placement.onboarding_tasks ?? [])

const totalTasks     = computed(() => tasks.value.length)
const completedTasks = computed(() => tasks.value.filter(t => ['completed', 'skipped'].includes(t.status)).length)
const progressPct    = computed(() => totalTasks.value ? Math.round((completedTasks.value / totalTasks.value) * 100) : 0)

function taskStatusColor(status) {
  const map = { pending: 'slate', in_progress: 'amber', completed: 'green', skipped: 'slate' }
  return map[status] ?? 'slate'
}

function placementStatusColor(status) {
  const map = { pending: 'amber', active: 'green', completed: 'blue', terminated: 'red' }
  return map[status] ?? 'slate'
}

// ── Complete task ──────────────────────────────────────────────────────────────
const completeTask = async (task) => {
  const { value: notes, isConfirmed } = await Swal.fire({
    title: `Complete: ${task.task_name}`,
    input: 'textarea',
    inputLabel: 'Completion Notes (optional)',
    inputPlaceholder: 'e.g. Document received and filed',
    showCancelButton: true,
    confirmButtonText: 'Mark Complete',
    confirmButtonColor: '#16a34a',
    reverseButtons: true,
  })
  if (!isConfirmed) return

  router.patch(route('recruitment.placements.tasks.complete', [props.placement.id, task.id]), {
    notes: notes ?? '',
  }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Task completed!', timer: 1200, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

// ── Skip task ──────────────────────────────────────────────────────────────────
const skipTask = async (task) => {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: `Skip: ${task.task_name}?`,
    input: 'textarea',
    inputLabel: 'Reason (optional)',
    showCancelButton: true,
    confirmButtonText: 'Skip',
    reverseButtons: true,
  })
  if (!isConfirmed) return

  router.patch(route('recruitment.placements.tasks.skip', [props.placement.id, task.id]), {
    reason: reason ?? '',
  }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Task skipped.', timer: 1200, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

// ── Assign task ────────────────────────────────────────────────────────────────
const showAssignModal = ref(false)
const assignTarget    = ref(null)
const assignForm      = ref({ assigned_to: '', due_date: '' })
const assignLoading   = ref(false)

const openAssign = (task) => {
  assignTarget.value = task
  assignForm.value   = {
    assigned_to: task.assigned_to ?? '',
    due_date:    task.due_date?.slice(0, 10) ?? '',
  }
  showAssignModal.value = true
}

const submitAssign = () => {
  assignLoading.value = true
  router.patch(route('recruitment.placements.tasks.assign', [props.placement.id, assignTarget.value.id]), assignForm.value, {
    onSuccess: () => {
      showAssignModal.value = false
      Swal.fire({ icon: 'success', title: 'Task assigned!', timer: 1200, showConfirmButton: false })
    },
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
    onFinish: () => { assignLoading.value = false },
  })
}

const applicant = computed(() => props.placement.application?.applicant)
const position  = computed(() => props.placement.application?.job_vacancy?.job_item)
</script>

<template>
  <Head :title="`Placement #${placement.id} — Onboarding`" />
  <AdminLayout :title="`Onboarding: ${applicant?.last_name}, ${applicant?.first_name}`">
    <div class="max-w-4xl mx-auto space-y-4">

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <!-- Back -->
      <Link :href="route('recruitment.placements.index')"
            class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        &larr; Back to Placements
      </Link>

      <!-- Header Card -->
      <AppCard>
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="text-xl font-semibold text-slate-800">
              {{ applicant?.last_name }}, {{ applicant?.first_name }}
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ applicant?.email }}</p>
            <p class="text-sm text-slate-700 font-medium mt-1">{{ position?.position_title ?? '—' }}</p>
            <p class="text-xs text-slate-400">
              {{ placement.office?.name ?? '—' }}
              · Start: {{ formatDate(placement.start_date) }}
              <span v-if="placement.end_date"> · End: {{ formatDate(placement.end_date) }}</span>
            </p>
          </div>
          <div class="flex flex-col items-end gap-2">
            <AppBadge :color="placementStatusColor(placement.status)"><span class="capitalize">{{ placement.status }}</span></AppBadge>
            <Link :href="route('recruitment.applications.show', placement.application?.id)"
                  class="text-xs text-indigo-600 hover:underline">
              View Application &rarr;
            </Link>
          </div>
        </div>
      </AppCard>

      <!-- Progress Card -->
      <AppCard>
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-slate-700">Onboarding Progress</h3>
          <span class="text-sm font-bold text-slate-700">{{ completedTasks }} / {{ totalTasks }} tasks</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2.5">
          <div class="bg-success-500 h-2.5 rounded-full transition-all duration-500"
               :style="{ width: progressPct + '%' }"></div>
        </div>
        <div class="text-right text-xs text-slate-400 mt-1">{{ progressPct }}% complete</div>
      </AppCard>

      <!-- Tasks Card -->
      <AppCard title="Onboarding Tasks">
        <div v-if="tasks.length" class="space-y-2">
          <div v-for="task in tasks" :key="task.id"
               class="flex items-start gap-3 p-3 rounded-lg border"
               :class="{
                 'border-success-100 bg-success-50':  task.status === 'completed',
                 'border-slate-100 bg-slate-50':      task.status === 'skipped',
                 'border-warning-100 bg-warning-50':  task.status === 'in_progress',
                 'border-slate-200':                  task.status === 'pending',
               }">
            <!-- Status Icon -->
            <div class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs"
                 :class="{
                   'bg-success-500 text-white':  task.status === 'completed',
                   'bg-slate-300 text-white':    task.status === 'skipped',
                   'bg-warning-500 text-white':  task.status === 'in_progress',
                   'bg-slate-200 text-slate-500':task.status === 'pending',
                 }">
              <span v-if="task.status === 'completed'">✓</span>
              <span v-else-if="task.status === 'skipped'">—</span>
              <span v-else>·</span>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-medium text-slate-800" :class="{ 'line-through text-slate-400': task.status === 'skipped' }">
                    {{ task.task_name }}
                  </p>
                  <div class="text-xs text-slate-400 mt-0.5 space-x-2">
                    <span v-if="task.assignee">Assigned: {{ task.assignee.name }}</span>
                    <span v-if="task.due_date">· Due: {{ formatDate(task.due_date) }}</span>
                    <span v-if="task.completion_notes">· {{ task.completion_notes }}</span>
                  </div>
                </div>
                <AppBadge :color="taskStatusColor(task.status)">
                  <span class="whitespace-nowrap capitalize">{{ task.status.replace('_', ' ') }}</span>
                </AppBadge>
              </div>

              <!-- Action buttons for pending/in_progress tasks -->
              <div v-if="['pending', 'in_progress'].includes(task.status)" class="mt-2 flex gap-2">
                <AppButton size="sm" @click="completeTask(task)">Complete</AppButton>
                <AppButton size="sm" variant="secondary" @click="openAssign(task)">Assign</AppButton>
                <AppButton size="sm" variant="secondary" @click="skipTask(task)">Skip</AppButton>
              </div>
            </div>
          </div>
        </div>
        <EmptyState v-else title="No onboarding tasks generated." subtitle="Ensure the recruitment type has onboarding requirements configured." />
      </AppCard>
    </div>

    <!-- ── Assign Modal ──────────────────────────────────────────────────────── -->
    <AppModal :show="showAssignModal" title="Assign Task" :subtitle="assignTarget?.task_name" size="sm" @close="showAssignModal = false">
      <form @submit.prevent="submitAssign" class="space-y-4">
        <AppInput v-model="assignForm.assigned_to" type="number" required label="Assign To (User ID)" placeholder="Enter user ID" />
        <AppInput v-model="assignForm.due_date" type="date" label="Due Date" />
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="showAssignModal = false">Cancel</AppButton>
        <AppButton :loading="assignLoading" :disabled="assignLoading" @click="submitAssign">
          {{ assignLoading ? 'Saving…' : 'Assign' }}
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
