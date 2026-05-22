<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const taskStatusColors = {
  pending:     'bg-slate-100 text-slate-600',
  in_progress: 'bg-amber-50 text-amber-700',
  completed:   'bg-emerald-50 text-emerald-700',
  skipped:     'bg-slate-50 text-slate-400',
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
      <div v-if="page.props.flash?.success" class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <!-- Back -->
      <Link :href="route('recruitment.placements.index')"
            class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        &larr; Back to Placements
      </Link>

      <!-- Header Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
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
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize"
                  :class="{
                    'bg-amber-50 text-amber-700':     placement.status === 'pending',
                    'bg-emerald-50 text-emerald-700': placement.status === 'active',
                    'bg-blue-50 text-blue-700':       placement.status === 'completed',
                    'bg-red-50 text-red-600':         placement.status === 'terminated',
                  }">
              {{ placement.status }}
            </span>
            <Link :href="route('recruitment.applications.show', placement.application?.id)"
                  class="text-xs text-indigo-600 hover:underline">
              View Application &rarr;
            </Link>
          </div>
        </div>
      </div>

      <!-- Progress Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-slate-700">Onboarding Progress</h3>
          <span class="text-sm font-bold text-slate-700">{{ completedTasks }} / {{ totalTasks }} tasks</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2.5">
          <div class="bg-emerald-500 h-2.5 rounded-full transition-all duration-500"
               :style="{ width: progressPct + '%' }"></div>
        </div>
        <div class="text-right text-xs text-slate-400 mt-1">{{ progressPct }}% complete</div>
      </div>

      <!-- Tasks Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Onboarding Tasks</h3>

        <div v-if="tasks.length" class="space-y-2">
          <div v-for="task in tasks" :key="task.id"
               class="flex items-start gap-3 p-3 rounded-lg border"
               :class="{
                 'border-emerald-200 bg-emerald-50':  task.status === 'completed',
                 'border-slate-100 bg-slate-50':      task.status === 'skipped',
                 'border-amber-200 bg-amber-50':      task.status === 'in_progress',
                 'border-slate-200':                  task.status === 'pending',
               }">
            <!-- Status Icon -->
            <div class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs"
                 :class="{
                   'bg-emerald-500 text-white':  task.status === 'completed',
                   'bg-slate-300 text-white':    task.status === 'skipped',
                   'bg-amber-400 text-white':    task.status === 'in_progress',
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
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap flex-shrink-0" :class="taskStatusColors[task.status]">
                  {{ task.status.replace('_', ' ') }}
                </span>
              </div>

              <!-- Action buttons for pending/in_progress tasks -->
              <div v-if="['pending', 'in_progress'].includes(task.status)" class="mt-2 flex gap-2">
                <button @click="completeTask(task)"
                        class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                  Complete
                </button>
                <button @click="openAssign(task)"
                        class="inline-flex items-center gap-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-2.5 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                  Assign
                </button>
                <button @click="skipTask(task)"
                        class="inline-flex items-center gap-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-2.5 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                  Skip
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="py-16 text-center text-slate-400 text-sm">
          No onboarding tasks generated. Ensure the recruitment type has onboarding requirements configured.
        </div>
      </div>
    </div>

    <!-- ── Assign Modal ──────────────────────────────────────────────────────── -->
    <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl relative">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-800">Assign Task</h2>
            <p class="text-sm text-slate-500">{{ assignTarget?.task_name }}</p>
          </div>
          <button @click="showAssignModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>

        <form @submit.prevent="submitAssign" class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Assign To (User ID) *</label>
            <input v-model="assignForm.assigned_to" type="number" required
                   placeholder="Enter user ID"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Due Date</label>
            <input v-model="assignForm.due_date" type="date"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
          <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <button type="button" @click="showAssignModal = false"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" :disabled="assignLoading"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ assignLoading ? 'Saving…' : 'Assign' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
