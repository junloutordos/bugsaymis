<script setup>
import { ref, computed } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ArrowLeftIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  committee: Object,
  planMemberData: Array,
  authUser: Object,
  isHead: Boolean,
  canManage: Boolean,
})

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
  if (status === 'Submitted for Rating') return 'bg-blue-100 text-blue-700'
  if (status === 'Rated & For PMT Review' || status === 'Submitted to PMT') return 'bg-green-100 text-green-700'
  if (status === 'PMT Returned for Revision') return 'bg-red-100 text-red-700'
  if (status === 'Approved by PMT') return 'bg-purple-100 text-purple-700'
  return 'bg-gray-100 text-gray-600'
}
</script>

<template>
  <Head :title="`Committee — ${committee.name}`" />
  <AdminLayout :title="committee.name">
    <div>
      <!-- Header -->
      <div class="flex items-center gap-3 mb-6">
        <Link :href="route('pm-committees.index')"
          class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back
        </Link>
        <h1 class="text-xl font-semibold text-slate-800">{{ committee.name }}</h1>
      </div>

      <!-- Committee Info -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 mb-6">
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
      </div>

      <!-- No tagged plans -->
      <div v-if="!planMemberData?.length" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center text-slate-400 text-sm">
        No WDP plans tagged to this committee yet.
        <span v-if="canManage" class="text-slate-500"> Edit the committee to tag plans.</span>
      </div>

      <!-- Plan sections -->
      <div v-for="entry in planMemberData" :key="entry.plan.id" class="bg-white rounded-xl border border-slate-100 shadow-sm mb-6">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-base font-semibold text-slate-800">{{ entry.plan.success_indicator }}</h2>
          <p class="text-xs text-slate-400 mt-0.5">Rated by: {{ entry.plan.rated_by || "Division Chief" }}</p>
        </div>

        <div v-if="!entry.members?.length" class="p-5 text-sm text-slate-400 italic">
          No members in this committee.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
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
            </thead>
            <tbody class="divide-y divide-slate-100">
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
                      <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', statusColor(period.ipcr_status)]">
                        {{ period.ipcr_status ?? "—" }}
                      </span>
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
                      <button
                        v-if="authUser?.id == member.user_id && !(isHead || canManage)"
                        @click="openEditModal(entry, member, period)"
                        class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-2 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                        Edit
                      </button>
                      <template v-else-if="isHead || canManage">
                        <button
                          v-if="period.can_rate"
                          @click="openEditModal(entry, member, period)"
                          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                          Rate
                        </button>
                        <span v-else class="text-xs text-amber-600 italic" title="IPCR is no longer open for rating">
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
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Edit / Rate Modal -->
    <Teleport to="body">
    <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-800">
              {{ (modalEntry?.isOwn && !modalEntry?.canRate) ? 'Edit Accomplishment' : 'Rate Member' }}
              — {{ modalEntry?.member?.user_name }}
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Period: {{ modalEntry?.period?.rating_period }}</p>
          </div>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors" @click="closeModal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>

        <form @submit.prevent="submitEdit">
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Accomplishment</label>
              <textarea v-model="editForm.accomplishment" rows="3"
                :readonly="!modalEntry?.isOwn && !modalEntry?.canRate"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">MOV Link</label>
              <input v-model="editForm.mov_link" type="url" placeholder="https://..."
                :readonly="!modalEntry?.isOwn && !modalEntry?.canRate"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>

            <template v-if="modalEntry?.canRate">
              <div class="text-xs text-slate-500 bg-slate-50 rounded-lg p-3">
                <p class="font-semibold text-slate-700 mb-1">Rating Scale:</p>
                <p>5 — Outstanding &nbsp; 4 — Very Satisfactory &nbsp; 3 — Satisfactory &nbsp; 2 — Unsatisfactory &nbsp; 1 — Poor</p>
              </div>
              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Quality (1–5)</label>
                  <input v-model.number="editForm.sup_quality" type="number" min="1" max="5" step="0.01"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Efficiency (1–5)</label>
                  <input v-model.number="editForm.sup_efficiency" type="number" min="1" max="5" step="0.01"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Timeliness (1–5)</label>
                  <input v-model.number="editForm.sup_timeliness" type="number" min="1" max="5" step="0.01"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
              </div>
              <div class="text-sm text-slate-700">
                Live Average: <strong class="text-indigo-700">{{ liveAvg }}</strong>
                <span v-if="liveAvg !== '—'" class="ml-2 text-slate-500">— {{ adjectival(liveAvg) }}</span>
              </div>
            </template>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" @click="closeModal"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" :disabled="isSubmitting"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">{{ isSubmitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>
    </Teleport>
  </AdminLayout>
</template>
