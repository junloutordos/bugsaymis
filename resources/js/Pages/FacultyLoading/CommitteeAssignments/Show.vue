<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import { ipcrAdjectivalRating } from '@/Composables/ipcrAdjectivalRating'
import { useSubmit } from '@/Composables/useSubmit'
import Swal from 'sweetalert2'

const props = defineProps({
  committee:      Object,
  planMemberData: Array,
  terms:          Array,
  selectedTermId: Number,
  authUser:       Object,
  isChairperson:  Boolean,
  canManage:      Boolean,
})

const { isSubmitting, submit } = useSubmit()

// ── Term switching ─────────────────────────────────────────────────────────
function switchTerm(termId) {
  router.get(
    route('faculty-loading.committee-assignments.show', props.committee.id),
    { term_id: termId },
    { preserveState: false }
  )
}

// ── Edit / Rate modal ──────────────────────────────────────────────────────
const showModal   = ref(false)
const modalEntry  = ref(null) // { planId, member, isOwn, canRate }

const editForm = ref({
  work_distribution_plan_id: null,
  accomplishment: '',
  mov_link: '',
  sup_quality:    null,
  sup_efficiency: null,
  sup_timeliness: null,
})

function openModal(plan, member) {
  const isOwn   = props.authUser?.id === member.user_id
  const canRate = (props.isChairperson || props.canManage) && !isOwn

  if (!isOwn && !canRate) return

  modalEntry.value = { planId: plan.id, member, isOwn, canRate }
  editForm.value = {
    work_distribution_plan_id: plan.id,
    accomplishment: member.accomplishment ?? '',
    mov_link:       member.mov_link ?? '',
    sup_quality:    member.sup_quality ?? null,
    sup_efficiency: member.sup_efficiency ?? null,
    sup_timeliness: member.sup_timeliness ?? null,
  }
  showModal.value = true
}

// Chairperson or admin can open the rate modal for any member (including own)
function openRateModal(plan, member) {
  modalEntry.value = { planId: plan.id, member, isOwn: props.authUser?.id === member.user_id, canRate: true }
  editForm.value = {
    work_distribution_plan_id: plan.id,
    accomplishment: member.accomplishment ?? '',
    mov_link:       member.mov_link ?? '',
    sup_quality:    member.sup_quality ?? null,
    sup_efficiency: member.sup_efficiency ?? null,
    sup_timeliness: member.sup_timeliness ?? null,
  }
  showModal.value = true
}

function closeModal() { showModal.value = false; modalEntry.value = null }

function submitModal() {
  const entry = modalEntry.value
  if (!entry) return

  const onSuccess = () => {
    closeModal()
    Swal.fire({ icon: 'success', title: 'Saved', timer: 1200, showConfirmButton: false })
  }
  const onError = (err) => {
    Swal.fire('Error', Object.values(err).flat().join('\n') || 'Failed to save.', 'error')
  }

  if (entry.canRate) {
    submit.post(
      route('faculty-loading.committee-assignments.rate', entry.member.assignment_id),
      { ...editForm.value },
      { onSuccess, onError }
    )
  } else {
    submit.post(
      route('faculty-loading.committee-assignments.accomplishment', entry.member.assignment_id),
      {
        work_distribution_plan_id: editForm.value.work_distribution_plan_id,
        accomplishment:            editForm.value.accomplishment,
        mov_link:                  editForm.value.mov_link,
      },
      { onSuccess, onError }
    )
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────
const adjectival = ipcrAdjectivalRating

const liveAvg = computed(() => {
  const vals = [editForm.value.sup_quality, editForm.value.sup_efficiency, editForm.value.sup_timeliness]
    .filter(v => v !== null && v !== '' && !isNaN(v))
    .map(Number)
  if (!vals.length) return null
  return (vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(2)
})

function roleBadge(role) {
  return {
    chairperson: 'bg-amber-50 text-amber-700',
    co_chair:    'bg-amber-50 text-amber-600',
    secretary:   'bg-blue-50 text-blue-700',
    member:      'bg-slate-100 text-slate-600',
  }[role] ?? 'bg-slate-100 text-slate-600'
}

function roleLabel(role) {
  return { chairperson: 'Chairperson', co_chair: 'Co-Chair', secretary: 'Secretary', member: 'Member' }[role] ?? role
}

const selectedTerm = computed(() => props.terms.find(t => t.id === props.selectedTermId))
</script>

<template>
  <Head :title="`${committee.name} — Committee`" />
  <AdminLayout :title="committee.name">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-5">
      <Link :href="route('faculty-loading.committee-assignments.index')"
        class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800 transition-colors">
        <ArrowLeftIcon class="w-4 h-4" /> Back to Assignments
      </Link>
    </div>

    <!-- Committee Info Card -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 mb-5">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
          <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Committee</p>
          <p class="font-semibold text-slate-800 mt-0.5">{{ committee.name }}</p>
          <span v-if="committee.code" class="font-mono text-xs text-indigo-600">{{ committee.code }}</span>
        </div>
        <div>
          <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Chairperson</p>
          <p class="font-semibold text-slate-800 mt-0.5">{{ committee.head?.name ?? '—' }}</p>
          <p v-if="committee.head?.position" class="text-xs text-slate-400">{{ committee.head.position }}</p>
        </div>
        <div>
          <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Load Rates</p>
          <p class="text-slate-700 mt-0.5">Chair: <strong>{{ committee.chairperson_load_units }}</strong> units</p>
          <p class="text-slate-700">Member: <strong>{{ committee.member_load_units }}</strong> units</p>
        </div>
        <div v-if="committee.description">
          <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Description</p>
          <p class="text-slate-700 mt-0.5 text-xs leading-relaxed">{{ committee.description }}</p>
        </div>
      </div>
    </div>

    <!-- Term selector -->
    <div class="flex items-center gap-3 mb-5">
      <span class="text-sm font-medium text-slate-600">Term:</span>
      <select :value="selectedTermId" @change="switchTerm(Number($event.target.value))"
        class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        <option v-for="t in terms" :key="t.id" :value="t.id">
          {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
        </option>
      </select>
    </div>

    <!-- No tagged plans -->
    <div v-if="!planMemberData?.length"
      class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center text-slate-400 text-sm">
      No Work Distribution Plans tagged to this committee yet.
      <span class="text-slate-500"> Edit the committee in Data Management to tag plans, or use the Assign form.</span>
    </div>

    <!-- Per-plan sections -->
    <div v-for="entry in planMemberData" :key="entry.plan.id"
      class="bg-white rounded-xl border border-slate-100 shadow-sm mb-5">

      <!-- Plan header -->
      <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="text-base font-semibold text-slate-800">{{ entry.plan.success_indicator }}</h2>
        <p v-if="entry.plan.rated_by" class="text-xs text-slate-400 mt-0.5">Rated by: {{ entry.plan.rated_by }}</p>
      </div>

      <!-- No members in term -->
      <div v-if="!entry.members?.length" class="p-5 text-sm text-slate-400 italic">
        No faculty assigned to this committee for the selected term.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Member</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Role</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide max-w-xs">Accomplishment / MOV</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-10">Q</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-10">E</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-10">T</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-14">Avg</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Rating</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-24">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="member in entry.members" :key="member.user_id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3">
                <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                <p v-if="member.user_position" class="text-xs text-slate-400">{{ member.user_position }}</p>
              </td>
              <td class="px-4 py-3">
                <span :class="roleBadge(member.role)"
                  class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ roleLabel(member.role) }}
                </span>
              </td>
              <td class="px-4 py-3 max-w-xs">
                <p class="text-slate-700 text-xs truncate">{{ member.accomplishment || '—' }}</p>
                <a v-if="member.mov_link" :href="member.mov_link" target="_blank"
                  class="text-indigo-600 text-xs hover:underline">MOV Link ↗</a>
              </td>
              <td class="px-3 py-3 text-center text-sm text-slate-700">{{ member.sup_quality ?? '—' }}</td>
              <td class="px-3 py-3 text-center text-sm text-slate-700">{{ member.sup_efficiency ?? '—' }}</td>
              <td class="px-3 py-3 text-center text-sm text-slate-700">{{ member.sup_timeliness ?? '—' }}</td>
              <td class="px-3 py-3 text-center text-sm font-semibold text-slate-800">{{ member.sup_average ?? '—' }}</td>
              <td class="px-3 py-3 text-xs text-slate-600">{{ adjectival(member.sup_average) }}</td>
              <td class="px-3 py-3 text-center">
                <!-- Chairperson / Admin: Rate button -->
                <button v-if="isChairperson || canManage"
                  @click="openRateModal(entry.plan, member)"
                  class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                  Rate
                </button>
                <!-- Own member (not chair): Edit own accomplishment -->
                <button v-else-if="authUser?.id === member.user_id"
                  @click="openModal(entry.plan, member)"
                  class="inline-flex items-center bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-2 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                  Edit
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit / Rate Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-800">
                {{ modalEntry?.canRate ? 'Rate Member' : 'Edit Accomplishment' }}
                — {{ modalEntry?.member?.user_name }}
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">{{ committee.name }}</p>
            </div>
            <button @click="closeModal"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form @submit.prevent="submitModal">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Accomplishment</label>
                <textarea v-model="editForm.accomplishment" rows="3"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">MOV Link</label>
                <input v-model="editForm.mov_link" type="url" placeholder="https://…"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>

              <!-- Rating inputs (chairperson / admin only) -->
              <template v-if="modalEntry?.canRate">
                <div class="text-xs text-slate-500 bg-slate-50 rounded-lg p-3">
                  <p class="font-semibold text-slate-700 mb-1">Rating Scale:</p>
                  <p>5 — Outstanding &nbsp; 4 — Very Satisfactory &nbsp; 3 — Satisfactory &nbsp; 2 — Unsatisfactory &nbsp; 1 — Poor</p>
                </div>
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Quality (1–5)</label>
                    <input v-model.number="editForm.sup_quality" type="number" min="1" max="5" step="0.01"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Efficiency (1–5)</label>
                    <input v-model.number="editForm.sup_efficiency" type="number" min="1" max="5" step="0.01"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Timeliness (1–5)</label>
                    <input v-model.number="editForm.sup_timeliness" type="number" min="1" max="5" step="0.01"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                </div>
                <div class="text-sm text-slate-700">
                  Live Average: <strong class="text-indigo-700">{{ liveAvg ?? '—' }}</strong>
                  <span v-if="liveAvg" class="ml-2 text-slate-500">— {{ adjectival(liveAvg) }}</span>
                </div>
              </template>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <button type="button" @click="closeModal"
                class="px-4 py-2 border border-slate-200 rounded-lg text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
              <button type="submit" :disabled="isSubmitting"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-50">
                {{ isSubmitting ? 'Saving…' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
