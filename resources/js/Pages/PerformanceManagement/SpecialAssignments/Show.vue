<script setup>
import { ref, computed } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ArrowLeftIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

const props = defineProps({
  assignment: Object,
  planMemberData: Array,
  authUser: Object,
  isCoordinator: Boolean,
  canManage: Boolean,
})

const showModal = ref(false)
const modalEntry = ref(null)

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
  const canRate = (props.isCoordinator || props.canManage) && period.can_rate

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
    router.post(
      route("pm-special-assignments.member-accomplishment", [props.assignment.id, entry.member.user_id]),
      {
        ipcr_id:        editForm.value.ipcr_id,
        plan_id:        entry.planId,
        accomplishment: editForm.value.accomplishment,
        mov_link:       editForm.value.mov_link,
      },
      { onSuccess, onError }
    )
  } else {
    router.post(
      route("pm-special-assignments.rate-member", [props.assignment.id, entry.member.user_id]),
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
  <Head :title="`Special Assignment — ${assignment.name}`" />
  <AdminLayout :title="assignment.name">
    <div>
      <!-- Header -->
      <div class="flex items-center gap-3 mb-4">
        <Link :href="route('pm-special-assignments.index')"
          class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800">
          <ArrowLeftIcon class="w-4 h-4" /> Back
        </Link>
        <h1 class="text-xl md:text-2xl font-bold text-gray-800">{{ assignment.name }}</h1>
      </div>

      <!-- Assignment Info -->
      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          <div>
            <span class="text-gray-500 font-medium">Coordinator:</span>
            <p class="font-semibold">{{ assignment.coordinator?.name ?? "—" }}</p>
          </div>
          <div>
            <span class="text-gray-500 font-medium">Members:</span>
            <p class="font-semibold">{{ assignment.members?.length ?? 0 }}</p>
          </div>
          <div>
            <span class="text-gray-500 font-medium">Description:</span>
            <p>{{ assignment.description ?? "—" }}</p>
          </div>
        </div>
      </div>

      <!-- No tagged plans -->
      <div v-if="!planMemberData?.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
        No WDP plans tagged to this special assignment yet.
        <span v-if="canManage"> Edit the assignment to tag plans.</span>
      </div>

      <!-- Plan sections -->
      <div v-for="entry in planMemberData" :key="entry.plan.id" class="bg-white rounded-xl shadow p-4 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-1">{{ entry.plan.success_indicator }}</h2>
        <p class="text-xs text-gray-400 mb-4">Rated by: {{ entry.plan.rated_by || "Division Chief" }}</p>

        <div v-if="!entry.members?.length" class="text-sm text-gray-400 italic py-2">
          No members in this assignment.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full border border-gray-200 text-sm">
            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
              <tr>
                <th class="px-3 py-2 text-left">Member</th>
                <th class="px-3 py-2 text-left">Task / Role</th>
                <th class="px-3 py-2 text-left">Rating Period</th>
                <th class="px-3 py-2 text-left">IPCR Status</th>
                <th class="px-3 py-2 text-left">Accomplishment</th>
                <th class="px-3 py-2 text-center">Q</th>
                <th class="px-3 py-2 text-center">E</th>
                <th class="px-3 py-2 text-center">T</th>
                <th class="px-3 py-2 text-center">Avg</th>
                <th class="px-3 py-2 text-center">Rating</th>
                <th class="px-3 py-2 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <template v-for="member in entry.members" :key="member.user_id">
                <!-- Member has IPCR periods -->
                <template v-if="member.periods.length">
                  <tr
                    v-for="(period, pIdx) in member.periods"
                    :key="`${member.user_id}-${period.ipcr_id}`"
                    :class="pIdx === 0 ? 'border-t-2 border-gray-300 hover:bg-gray-50' : 'hover:bg-gray-50'"
                  >
                    <!-- Member name/task only on first period row (rowspan) -->
                    <td v-if="pIdx === 0" :rowspan="member.periods.length" class="px-3 py-2 align-top border-r border-gray-200">
                      <p class="font-medium">{{ member.user_name }}</p>
                      <p class="text-xs text-gray-400">{{ member.user_position }}</p>
                    </td>
                    <td v-if="pIdx === 0" :rowspan="member.periods.length" class="px-3 py-2 align-top text-gray-600 border-r border-gray-200">
                      {{ member.task || "—" }}
                    </td>
                    <!-- Period-specific columns -->
                    <td class="px-3 py-2 text-indigo-700 font-medium whitespace-nowrap">{{ period.rating_period }}</td>
                    <td class="px-3 py-2">
                      <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusColor(period.ipcr_status)]">
                        {{ period.ipcr_status ?? "—" }}
                      </span>
                    </td>
                    <td class="px-3 py-2 max-w-xs">
                      <p class="truncate">{{ period.accomplishment || "—" }}</p>
                      <a v-if="period.mov_link" :href="period.mov_link" target="_blank"
                        class="text-blue-500 text-xs hover:underline">MOV Link</a>
                    </td>
                    <td class="px-3 py-2 text-center">{{ period.sup_quality ?? "—" }}</td>
                    <td class="px-3 py-2 text-center">{{ period.sup_efficiency ?? "—" }}</td>
                    <td class="px-3 py-2 text-center">{{ period.sup_timeliness ?? "—" }}</td>
                    <td class="px-3 py-2 text-center font-semibold">{{ period.sup_average ?? "—" }}</td>
                    <td class="px-3 py-2 text-center text-xs">{{ adjectival(period.sup_average) }}</td>
                    <td class="px-3 py-2 text-center">
                      <!-- Own: always show Edit for their accomplishment -->
                      <button
                        v-if="authUser?.id == member.user_id && !(isCoordinator || canManage)"
                        @click="openEditModal(entry, member, period)"
                        class="px-2 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">
                        Edit
                      </button>
                      <!-- Coordinator/Manager: Rate if open, Locked if closed -->
                      <template v-else-if="isCoordinator || canManage">
                        <button
                          v-if="period.can_rate"
                          @click="openEditModal(entry, member, period)"
                          class="px-2 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                          Rate
                        </button>
                        <span v-else class="text-xs text-amber-600 italic" title="IPCR is no longer open for rating">
                          Locked
                        </span>
                      </template>
                    </td>
                  </tr>
                </template>

                <!-- Member has no IPCR linked to this plan -->
                <tr v-else :key="`${member.user_id}-no-ipcr`" class="border-t-2 border-gray-300 hover:bg-gray-50">
                  <td class="px-3 py-2">
                    <p class="font-medium">{{ member.user_name }}</p>
                    <p class="text-xs text-gray-400">{{ member.user_position }}</p>
                  </td>
                  <td class="px-3 py-2 text-gray-600">{{ member.task || "—" }}</td>
                  <td colspan="9" class="px-3 py-2 text-center text-xs text-gray-400">No IPCR linked to this plan</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Edit / Rate Modal -->
    <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-xl" @click="closeModal">✕</button>
        <h2 class="text-lg font-semibold mb-1">
          {{ (modalEntry?.isOwn && !modalEntry?.canRate) ? 'Edit Accomplishment' : 'Rate Member' }}
          — {{ modalEntry?.member?.user_name }}
        </h2>
        <p class="text-xs text-gray-400 mb-4">Period: {{ modalEntry?.period?.rating_period }}</p>

        <form @submit.prevent="submitEdit" class="space-y-4">
          <div>
            <label class="font-medium text-sm">Accomplishment</label>
            <textarea v-model="editForm.accomplishment" rows="3"
              class="w-full mt-1 rounded-lg border-gray-300 text-sm"></textarea>
          </div>
          <div>
            <label class="font-medium text-sm">MOV Link</label>
            <input v-model="editForm.mov_link" type="url" placeholder="https://..."
              class="w-full mt-1 rounded-lg border-gray-300 text-sm" />
          </div>

          <template v-if="modalEntry?.canRate">
            <div class="text-xs text-gray-500 bg-gray-50 rounded p-2 space-y-0.5">
              <p class="font-semibold text-gray-700 mb-1">Rating Scale:</p>
              <p>5 — Outstanding &nbsp; 4 — Very Satisfactory &nbsp; 3 — Satisfactory &nbsp; 2 — Unsatisfactory &nbsp; 1 — Poor</p>
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="text-sm font-medium">Quality (1–5)</label>
                <input v-model.number="editForm.sup_quality" type="number" min="1" max="5" step="0.01"
                  class="w-full mt-1 rounded-lg border-gray-300 text-sm" />
              </div>
              <div>
                <label class="text-sm font-medium">Efficiency (1–5)</label>
                <input v-model.number="editForm.sup_efficiency" type="number" min="1" max="5" step="0.01"
                  class="w-full mt-1 rounded-lg border-gray-300 text-sm" />
              </div>
              <div>
                <label class="text-sm font-medium">Timeliness (1–5)</label>
                <input v-model.number="editForm.sup_timeliness" type="number" min="1" max="5" step="0.01"
                  class="w-full mt-1 rounded-lg border-gray-300 text-sm" />
              </div>
            </div>
            <div class="text-sm text-gray-700">
              Live Average: <strong>{{ liveAvg }}</strong>
              <span v-if="liveAvg !== '—'" class="ml-2 text-gray-500">— {{ adjectival(liveAvg) }}</span>
            </div>
          </template>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded text-sm">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">Save</button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
