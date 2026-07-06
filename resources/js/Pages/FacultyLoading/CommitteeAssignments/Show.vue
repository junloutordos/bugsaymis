<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
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
    chairperson: 'amber',
    co_chair:    'amber',
    secretary:   'blue',
    member:      'slate',
  }[role] ?? 'slate'
}

function roleLabel(role) {
  return { chairperson: 'Chairperson', co_chair: 'Co-Chair', secretary: 'Secretary', member: 'Member' }[role] ?? role
}

const selectedTerm = computed(() => props.terms.find(t => t.id === props.selectedTermId))
</script>

<template>
  <Head :title="`${committee.name} — Committee`" />
  <AdminLayout :title="committee.name">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex items-center gap-3">
        <Link :href="route('faculty-loading.committee-assignments.index')"
          class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back to Assignments
        </Link>
      </div>

      <!-- Committee Info Card -->
      <AppCard>
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
      </AppCard>

      <!-- Term selector -->
      <div class="flex items-center gap-3">
        <span class="text-sm font-medium text-slate-600">Term:</span>
        <div class="w-64">
          <AppSelect :model-value="selectedTermId" :show-blank="false"
            @update:model-value="v => switchTerm(Number(v))">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
        </div>
      </div>

      <!-- No tagged plans -->
      <AppCard v-if="!planMemberData?.length">
        <EmptyState title="No Work Distribution Plans tagged to this committee yet."
          subtitle="Edit the committee in Data Management to tag plans, or use the Assign form." />
      </AppCard>

      <!-- Per-plan sections -->
      <AppCard v-for="entry in planMemberData" :key="entry.plan.id" :padded="false"
        :title="entry.plan.success_indicator" :subtitle="entry.plan.rated_by ? `Rated by: ${entry.plan.rated_by}` : null">

        <div v-if="!entry.members?.length" class="p-5 text-sm text-slate-400 italic">
          No faculty assigned to this committee for the selected term.
        </div>

        <AppTable v-else :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Member</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Role</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide max-w-xs">Accomplishment / MOV</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-10">Q</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-10">E</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-10">T</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-14">Avg</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Rating</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide w-24">Action</th>
            </tr>
          </template>

          <tr v-for="member in entry.members" :key="member.user_id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800">{{ member.user_name }}</p>
              <p v-if="member.user_position" class="text-xs text-slate-400">{{ member.user_position }}</p>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="roleBadge(member.role)">{{ roleLabel(member.role) }}</AppBadge>
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
              <AppButton v-if="isChairperson || canManage" size="sm" @click="openRateModal(entry.plan, member)">
                Rate
              </AppButton>
              <!-- Own member (not chair): Edit own accomplishment -->
              <AppButton v-else-if="authUser?.id === member.user_id" size="sm" variant="secondary"
                @click="openModal(entry.plan, member)">
                Edit
              </AppButton>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="member in entry.members" :key="member.user_id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                  <p v-if="member.user_position" class="text-xs text-slate-400">{{ member.user_position }}</p>
                </div>
                <AppBadge :color="roleBadge(member.role)">{{ roleLabel(member.role) }}</AppBadge>
              </div>
              <p class="text-xs text-slate-700">{{ member.accomplishment || '—' }}</p>
              <a v-if="member.mov_link" :href="member.mov_link" target="_blank"
                class="text-indigo-600 text-xs hover:underline">MOV Link ↗</a>
              <div class="flex justify-between text-xs text-slate-500">
                <span>Q {{ member.sup_quality ?? '—' }} · E {{ member.sup_efficiency ?? '—' }} · T {{ member.sup_timeliness ?? '—' }}</span>
                <span class="font-semibold text-slate-800">Avg {{ member.sup_average ?? '—' }}</span>
              </div>
              <p class="text-xs text-slate-600">{{ adjectival(member.sup_average) }}</p>
              <div class="pt-1">
                <AppButton v-if="isChairperson || canManage" size="sm" @click="openRateModal(entry.plan, member)">
                  Rate
                </AppButton>
                <AppButton v-else-if="authUser?.id === member.user_id" size="sm" variant="secondary"
                  @click="openModal(entry.plan, member)">
                  Edit
                </AppButton>
              </div>
            </div>
          </template>
        </AppTable>
      </AppCard>
    </div>

    <!-- Edit / Rate Modal -->
    <AppModal :show="showModal"
      :title="(modalEntry?.canRate ? 'Rate Member' : 'Edit Accomplishment') + ' — ' + (modalEntry?.member?.user_name ?? '')"
      :subtitle="committee.name" size="lg" @close="closeModal">
      <form @submit.prevent="submitModal" class="space-y-4">
        <AppTextarea v-model="editForm.accomplishment" label="Accomplishment" :rows="3" />
        <AppInput v-model="editForm.mov_link" type="url" label="MOV Link" placeholder="https://…" />

        <!-- Rating inputs (chairperson / admin only) -->
        <template v-if="modalEntry?.canRate">
          <div class="text-xs text-slate-500 bg-slate-50 rounded-lg p-3">
            <p class="font-semibold text-slate-700 mb-1">Rating Scale:</p>
            <p>5 — Outstanding &nbsp; 4 — Very Satisfactory &nbsp; 3 — Satisfactory &nbsp; 2 — Unsatisfactory &nbsp; 1 — Poor</p>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <AppInput v-model.number="editForm.sup_quality" type="number" min="1" max="5" step="0.01" label="Quality (1–5)" />
            <AppInput v-model.number="editForm.sup_efficiency" type="number" min="1" max="5" step="0.01" label="Efficiency (1–5)" />
            <AppInput v-model.number="editForm.sup_timeliness" type="number" min="1" max="5" step="0.01" label="Timeliness (1–5)" />
          </div>
          <div class="text-sm text-slate-700">
            Live Average: <strong class="text-indigo-700">{{ liveAvg ?? '—' }}</strong>
            <span v-if="liveAvg" class="ml-2 text-slate-500">— {{ adjectival(liveAvg) }}</span>
          </div>
        </template>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ isSubmitting ? 'Saving…' : 'Save' }}</AppButton>
        </div>
      </form>
    </AppModal>
  </AdminLayout>
</template>
