<script setup>
import { ref, computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppButton from "@/Components/AppButton.vue"
import AppCard from "@/Components/AppCard.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppInput from "@/Components/AppInput.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppModal from "@/Components/AppModal.vue"
import AppTabs from "@/Components/AppTabs.vue"
import EmptyState from "@/Components/EmptyState.vue"
import TaskBoard from "@/Components/Committee/TaskBoard.vue"
import { ArrowLeftIcon, ClipboardDocumentListIcon, StarIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  committee: Object,
  planMemberData: Array,
  authUser: Object,
  isHead: Boolean,
  canManage: Boolean,
  tasks: { type: Array, default: () => [] },
  boardMembers: { type: Array, default: () => [] },
  ratingPeriods: { type: Array, default: () => [] },
  selectedPeriodId: { type: Number, default: null },
  canManageBoard: { type: Boolean, default: false },
})

const activeTab = ref("board")
const boardTabs = [
  { key: "board",   label: "Task Board",                icon: ClipboardDocumentListIcon },
  { key: "ratings", label: "Accomplishments & Ratings", icon: StarIcon },
]
const boardPlans = computed(() => (props.planMemberData ?? []).map(e => ({ id: e.plan.id, success_indicator: e.plan.success_indicator })))

const { isSubmitting, submit } = useSubmit()

const showModal = ref(false)
const modalEntry = ref(null) // { planId, member, period, isOwn, canRate }

const editForm = ref({
  ipcr_id:        null,
  plan_id:        null,
  accomplishment: "",
  mov_link:       "",
  sup_quality:    null,
  sup_efficiency: null,
  sup_timeliness: null,
})

const openEditModal = (entry, member, period) => {
  const isOwn   = props.authUser?.id == member.user_id
  const canRate = (props.isHead || props.canManage) && period.can_rate

  if (!isOwn && !canRate) return

  modalEntry.value = { planId: entry.plan.id, member, period, isOwn, canRate }
  editForm.value = {
    ipcr_id:        period.ipcr_id,
    plan_id:        entry.plan.id,
    accomplishment: period.accomplishment ?? "",
    mov_link:       period.mov_link ?? "",
    sup_quality:    period.sup_quality ?? null,
    sup_efficiency: period.sup_efficiency ?? null,
    sup_timeliness: period.sup_timeliness ?? null,
  }
  showModal.value = true
}

const closeModal = () => { showModal.value = false; modalEntry.value = null }

const submitEdit = () => {
  const entry = modalEntry.value
  if (!entry) return

  const onSuccess = () => {
    closeModal()
    Swal.fire({ icon: "success", title: "Saved", timer: 1200, showConfirmButton: false })
  }
  const onError = (err) => {
    Swal.fire("Error", Object.values(err).flat().join("\n") || "Failed to save.", "error")
  }

  if (entry.isOwn && !entry.canRate) {
    submit.post(
      route("pm-committees.member-accomplishment", [props.committee.id, entry.member.user_id]),
      {
        ipcr_id:        editForm.value.ipcr_id,
        plan_id:        entry.planId,
        accomplishment: editForm.value.accomplishment,
        mov_link:       editForm.value.mov_link,
      },
      { onSuccess, onError }
    )
  } else {
    submit.post(
      route("pm-committees.rate-member", [props.committee.id, entry.member.user_id]),
      { ...editForm.value },
      { onSuccess, onError }
    )
  }
}

const adjectival = ipcrAdjectivalRating

// monday-style rollup: pull the member's Done board tasks into the
// accomplishment text as evidence lines.
const doneTasksFor = (userId) => props.tasks.filter(t =>
  t.status === "done" && (t.assignees ?? []).some(a => a.id === userId)
)
const compileDoneTasks = () => {
  const member = modalEntry.value?.member
  if (!member) return
  const lines = doneTasksFor(member.user_id).map(t => `• ${t.title}`)
  if (!lines.length) return
  const existing = editForm.value.accomplishment?.trim()
  editForm.value.accomplishment = (existing ? existing + "\n" : "") + lines.join("\n")
}

const computeAvg = (q, e, t) => {
  const vals = [q, e, t].filter(v => v !== null && v !== "" && !isNaN(v)).map(Number)
  if (!vals.length) return null
  return vals.reduce((a, b) => a + b, 0) / vals.length
}

const liveAvg = computed(() => {
  const v = computeAvg(editForm.value.sup_quality, editForm.value.sup_efficiency, editForm.value.sup_timeliness)
  return v !== null ? v.toFixed(2) : "—"
})

const statusColor = (status) => {
  if (status === 'Submitted for Rating') return 'blue'
  if (status === 'Rated & For PMT Review' || status === 'Submitted to PMT') return 'green'
  if (status === 'PMT Returned for Revision') return 'red'
  if (status === 'Approved by PMT') return 'purple'
  return 'slate'
}
</script>

<template>
  <Head :title="`Committee — ${committee.name}`" />
  <AdminLayout :title="committee.name">
    <div class="space-y-5">
      <!-- Header -->
      <div class="flex items-center gap-3">
        <Link :href="route('pm-committees.index')"
          class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back
        </Link>
        <h1 class="font-heading text-xl font-semibold text-slate-800">{{ committee.name }}</h1>
      </div>

      <!-- Committee Info -->
      <AppCard>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          <div>
            <span class="text-slate-500 font-medium">Committee Head:</span>
            <p class="font-semibold text-slate-800 mt-0.5">{{ committee.head?.name ?? "—" }}</p>
          </div>
          <div>
            <span class="text-slate-500 font-medium">Members:</span>
            <p class="font-semibold text-slate-800 mt-0.5">{{ committee.members?.length ?? 0 }}</p>
          </div>
          <div>
            <span class="text-slate-500 font-medium">Description:</span>
            <p class="text-slate-700 mt-0.5">{{ committee.description ?? "—" }}</p>
          </div>
        </div>
      </AppCard>

      <AppTabs v-model="activeTab" :tabs="boardTabs">
        <template #tab-board>
          <TaskBoard
            :committee="committee"
            :tasks="tasks"
            :board-members="boardMembers"
            :rating-periods="ratingPeriods"
            :selected-period-id="selectedPeriodId"
            :can-manage-board="canManageBoard"
            :current-user-id="authUser.id"
            :plans="boardPlans"
          />
        </template>

        <template #tab-ratings>
          <div class="space-y-5">

      <!-- No tagged plans -->
      <AppCard v-if="!planMemberData?.length">
        <EmptyState title="No WDP plans tagged to this committee yet." :subtitle="canManage ? 'Edit the committee to tag plans.' : null" />
      </AppCard>

      <!-- Plan sections -->
      <AppCard v-for="entry in planMemberData" :key="entry.plan.id" :padded="false"
        :title="entry.plan.success_indicator" :subtitle="`Rated by: ${entry.plan.rated_by || 'Division Chief'}`">
        <div v-if="!entry.members?.length" class="p-5 text-sm text-slate-400 italic">
          No members in this committee.
        </div>

        <AppTable v-else :card="false">
          <template #head>
            <tr>
              <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Member</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Task / Role</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating Period</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">IPCR Status</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Accomplishment</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Q</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">E</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">T</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Avg</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
            </tr>
          </template>

          <template v-for="member in entry.members" :key="member.user_id">
            <template v-if="member.periods.length">
              <tr
                    v-for="(period, pIdx) in member.periods"
                    :key="`${member.user_id}-${period.ipcr_id}`"
                    :class="pIdx === 0 ? 'border-t-2 border-slate-200 hover:bg-slate-50/60' : 'hover:bg-slate-50/60'"
                  >
                    <td v-if="pIdx === 0" :rowspan="member.periods.length" class="px-3 py-2 align-top border-r border-slate-100">
                      <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                      <p class="text-xs text-slate-400">{{ member.user_position }}</p>
                    </td>
                    <td v-if="pIdx === 0" :rowspan="member.periods.length" class="px-3 py-2 align-top text-slate-600 border-r border-slate-100 text-sm">
                      {{ member.task || "—" }}
                    </td>
                    <td class="px-3 py-2 text-sm text-indigo-700 font-medium whitespace-nowrap">{{ period.rating_period }}</td>
                    <td class="px-3 py-2">
                      <AppBadge :color="statusColor(period.ipcr_status)">{{ period.ipcr_status ?? "—" }}</AppBadge>
                    </td>
                    <td class="px-3 py-2 max-w-xs text-sm text-slate-700">
                      <p class="truncate">{{ period.accomplishment || "—" }}</p>
                      <a v-if="period.mov_link" :href="period.mov_link" target="_blank"
                        class="text-indigo-600 text-xs hover:underline">MOV Link</a>
                    </td>
                    <td class="px-3 py-2 text-center text-sm text-slate-700">{{ period.sup_quality ?? "—" }}</td>
                    <td class="px-3 py-2 text-center text-sm text-slate-700">{{ period.sup_efficiency ?? "—" }}</td>
                    <td class="px-3 py-2 text-center text-sm text-slate-700">{{ period.sup_timeliness ?? "—" }}</td>
                    <td class="px-3 py-2 text-center text-sm font-semibold text-slate-800">{{ period.sup_average ?? "—" }}</td>
                    <td class="px-3 py-2 text-center text-xs text-slate-600">{{ adjectival(period.sup_average) }}</td>
                    <td class="px-3 py-2 text-center">
                      <AppButton
                        v-if="authUser?.id == member.user_id && !(isHead || canManage)"
                        size="sm" variant="secondary" @click="openEditModal(entry, member, period)">
                        Edit
                      </AppButton>
                      <template v-else-if="isHead || canManage">
                        <AppButton
                          v-if="period.can_rate"
                          size="sm" @click="openEditModal(entry, member, period)">
                          Rate
                        </AppButton>
                        <span v-else class="text-xs text-warning-600 italic" title="IPCR is no longer open for rating">
                          Locked
                        </span>
                      </template>
                    </td>
                  </tr>
                </template>

                <tr v-else :key="`${member.user_id}-no-ipcr`" class="border-t-2 border-slate-200 hover:bg-slate-50/60">
                  <td class="px-3 py-2 text-sm">
                    <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                    <p class="text-xs text-slate-400">{{ member.user_position }}</p>
                  </td>
                  <td class="px-3 py-2 text-sm text-slate-600">{{ member.task || "—" }}</td>
                  <td colspan="9" class="px-3 py-2 text-center text-xs text-slate-400">No IPCR linked to this plan</td>
                </tr>
              </template>
        </AppTable>
      </AppCard>
          </div>
        </template>
      </AppTabs>
    </div>

    <!-- Edit / Rate Modal -->
    <AppModal :show="showModal" :title="(modalEntry?.isOwn && !modalEntry?.canRate) ? 'Edit Accomplishment' : 'Rate Member'"
      :subtitle="modalEntry ? `${modalEntry.member?.user_name} — Period: ${modalEntry.period?.rating_period}` : null"
      size="lg" @close="closeModal">
      <form @submit.prevent="submitEdit" class="space-y-4">
        <AppTextarea
          v-model="editForm.accomplishment" label="Accomplishment" :rows="3"
          :readonly="!modalEntry?.isOwn && !modalEntry?.canRate" />
        <button v-if="modalEntry && (modalEntry.isOwn || modalEntry.canRate) && doneTasksFor(modalEntry.member.user_id).length" type="button"
          class="text-xs text-indigo-600 hover:underline -mt-2 block"
          @click="compileDoneTasks">
          + Compile {{ doneTasksFor(modalEntry.member.user_id).length }} done task(s) from the board into the accomplishment
        </button>
        <AppInput
          v-model="editForm.mov_link" type="url" label="MOV Link" placeholder="https://..."
          :readonly="!modalEntry?.isOwn && !modalEntry?.canRate" />

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
            Live Average: <strong class="text-indigo-700">{{ liveAvg }}</strong>
            <span v-if="liveAvg !== '—'" class="ml-2 text-slate-500">— {{ adjectival(liveAvg) }}</span>
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
