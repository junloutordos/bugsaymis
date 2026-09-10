<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import AppModal from "@/Components/AppModal.vue"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import { TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Swal from "sweetalert2"
import { useSubmit } from "@/Composables/useSubmit"
import { groupConsecutiveByFunction } from "@/Composables/ipcrV2FunctionGrouping.js"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
  canRate: { type: Boolean, default: false },
  ipcrStatus: { type: String, default: null },
})

const { submit } = useSubmit()

const rows = computed(() => groupConsecutiveByFunction(props.items))

const canOpenModal = computed(() => (props.isOwner && props.isMutable) || props.canRate)

// Mirrors EmployeeIpcrV2Controller::EDITABLE_STATUSES — target can only be set/changed before it's approved.
const TARGET_PHASE_STATUSES = ["New Target", "Returned for Revision"]
const isTargetEditable = computed(() => props.isOwner && props.isMutable && TARGET_PHASE_STATUSES.includes(props.ipcrStatus))
// Accomplishment/ratings only exist once targets have been approved — "For Review" is still pre-approval (awaiting the Division Chief's decision).
const PRE_APPROVAL_STATUSES = ["New Target", "For Review", "Returned for Revision"]
const showAccomplishment = computed(() => !PRE_APPROVAL_STATUSES.includes(props.ipcrStatus))

// ---------- Modal state ----------
const isModalOpen = ref(false)
const activeItem = ref(null)

// Committee-sourced items are rated exclusively via the Committee page
// (PerformanceManagement/Committees/Show.vue) — the Division Chief's own
// rating form here is read-only for them, matching the backend guard in
// DivisionChiefIpcrV2Controller::rateSupportItem().
const canRateActiveItem = computed(() => props.canRate && !activeItem.value?.is_committee_sourced)

function openModal(item) {
  if (!canOpenModal.value) return
  activeItem.value = item
  isModalOpen.value = true
}

function closeModal() {
  isModalOpen.value = false
  activeItem.value = null
}

function saveTarget(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    target: item.target,
  }, {
    ...opts,
    preserveScroll: true,
  }), {
    resetOnSuccess: true,
    onSuccess: () => {
      closeModal()
      Swal.fire({ icon: "success", title: "Target saved.", timer: 1200, showConfirmButton: false })
    },
    onError: (errors) => {
      Swal.fire({ icon: "error", title: "Error", text: Object.values(errors)[0] ?? "Failed to save target." })
    },
  })
}

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    actual_accomplishment: item.actual_accomplishment,
    mov_link: item.mov_link,
  }, opts))
}

function rate(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateSupportItem", [props.ipcrId, item.id]), {
    quality_rating: item.quality_rating,
    efficiency_rating: item.efficiency_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}

function rateSelf(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    self_quality_rating: item.self_quality_rating,
    self_efficiency_rating: item.self_efficiency_rating,
    self_timeliness_rating: item.self_timeliness_rating,
  }, opts))
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="11" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Support Function (20%)
      </td>
    </tr>
    <tr v-for="row in rows" :key="row.item.id" :class="canOpenModal ? 'hover:bg-indigo-50/40 cursor-pointer' : ''" @click="openModal(row.item)">
      <td v-if="row.isFirst" :rowspan="row.groupSize" :class="TD" class="border border-slate-200 align-top font-medium">{{ row.item.label }}</td>
      <td v-if="row.isFirst" :rowspan="row.groupSize" colspan="2" class="border border-slate-200 px-4 py-3 text-sm text-slate-500 align-top">{{ row.item.output_outcome ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200 align-top text-slate-500">{{ row.item.success_indicator ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200 align-top">
        <span :class="isTargetEditable ? 'text-indigo-600 hover:underline' : ''">{{ row.item.target ?? "—" }}</span>
      </td>
      <td :class="TD" class="border border-slate-200 align-top">
        <span :class="isOwner && isMutable && showAccomplishment ? 'text-indigo-600 hover:underline' : ''">{{ row.item.actual_accomplishment || (isOwner && isMutable && showAccomplishment ? "+ Add accomplishment" : "—") }}</span>
        <small v-if="row.item.mov_link" class="block text-slate-400 mt-1">MOV: {{ row.item.mov_link }}</small>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <div class="text-xs text-slate-400">Self: {{ row.item.self_quality_rating ?? "—" }}</div>
        <div>DC: {{ row.item.quality_rating ?? "—" }}</div>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <div class="text-xs text-slate-400">Self: {{ row.item.self_efficiency_rating ?? "—" }}</div>
        <div>DC: {{ row.item.efficiency_rating ?? "—" }}</div>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <div class="text-xs text-slate-400">Self: {{ row.item.self_timeliness_rating ?? "—" }}</div>
        <div>DC: {{ row.item.timeliness_rating ?? "—" }}</div>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
        <div class="text-xs text-slate-400 font-normal">Self: {{ row.item.self_row_average ?? "—" }}</div>
        <div>DC: {{ row.item.row_average ?? "—" }}</div>
      </td>
      <td :class="TD" class="border border-slate-200 align-top">
        {{ row.item.remarks ?? "—" }}
      </td>
    </tr>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="11">No Support Function rows yet.</td>
    </tr>
  </tbody>

  <AppModal :show="isModalOpen" :title="activeItem?.label ?? 'Support Function Item'" :subtitle="activeItem?.success_indicator ?? null" size="2xl" @close="closeModal">
    <template v-if="activeItem">
      <div class="space-y-6">
        <!-- Target -->
        <div>
          <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Target</h4>
          <AppTextarea v-if="isTargetEditable" v-model="activeItem.target" />
          <p v-else class="text-sm text-slate-700 mt-1">{{ activeItem.target ?? "—" }}</p>
          <AppButton v-if="isTargetEditable" size="sm" class="mt-3" @click="saveTarget(activeItem)">Save Target</AppButton>
        </div>

        <p v-if="!showAccomplishment" class="text-sm text-slate-400 italic border-t border-slate-100 pt-5">
          Accomplishment and ratings will be available once targets are approved.
        </p>

        <template v-else>
        <!-- Accomplishment -->
        <div class="border-t border-slate-100 pt-5">
          <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Accomplishment</h4>
          <div class="space-y-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Actual Accomplishment</label>
              <AppTextarea v-if="isOwner && isMutable" v-model="activeItem.actual_accomplishment" />
              <p v-else class="text-sm text-slate-700 mt-1">{{ activeItem.actual_accomplishment ?? "—" }}</p>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Means of Verification (link)</label>
              <input v-if="isOwner && isMutable" v-model="activeItem.mov_link" placeholder="MOV link" class="border rounded-lg px-3 py-2 text-sm w-full mt-1" />
              <p v-else-if="activeItem.mov_link"><a :href="activeItem.mov_link" target="_blank" class="text-indigo-600 hover:underline text-sm break-all">{{ activeItem.mov_link }}</a></p>
              <p v-else class="text-sm text-slate-400 mt-1">—</p>
            </div>
          </div>
          <AppButton v-if="isOwner && isMutable" size="sm" class="mt-3" @click="saveEmployeeFields(activeItem)">Save Accomplishment</AppButton>
        </div>

        <!-- Self-Rating -->
        <div class="border-t border-slate-100 pt-5">
          <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Self-Rating</h4>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Quality</label>
              <select v-if="isOwner && isMutable" v-model.number="activeItem.self_quality_rating" class="border rounded-lg text-sm px-2 py-1.5 w-full mt-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
              <p v-else class="text-sm mt-1">{{ activeItem.self_quality_rating ?? "—" }}</p>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Efficiency</label>
              <select v-if="isOwner && isMutable" v-model.number="activeItem.self_efficiency_rating" class="border rounded-lg text-sm px-2 py-1.5 w-full mt-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
              <p v-else class="text-sm mt-1">{{ activeItem.self_efficiency_rating ?? "—" }}</p>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Timeliness</label>
              <select v-if="isOwner && isMutable" v-model.number="activeItem.self_timeliness_rating" class="border rounded-lg text-sm px-2 py-1.5 w-full mt-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
              <p v-else class="text-sm mt-1">{{ activeItem.self_timeliness_rating ?? "—" }}</p>
            </div>
          </div>
          <p class="text-sm font-semibold mt-2">Average: {{ activeItem.self_row_average ?? "—" }}</p>
          <AppButton v-if="isOwner && isMutable" size="sm" class="mt-3" @click="rateSelf(activeItem)">Save Self-Rating</AppButton>
        </div>

        <!-- Division Chief Rating -->
        <div class="border-t border-slate-100 pt-5">
          <div class="flex items-center gap-2 mb-2">
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Division Chief Rating</h4>
            <AppBadge v-if="activeItem.is_committee_sourced" color="slate">Rated via Committee Assignment</AppBadge>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Quality</label>
              <select v-if="canRateActiveItem" v-model.number="activeItem.quality_rating" class="border rounded-lg text-sm px-2 py-1.5 w-full mt-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
              <p v-else class="text-sm mt-1">{{ activeItem.quality_rating ?? "—" }}</p>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Efficiency</label>
              <select v-if="canRateActiveItem" v-model.number="activeItem.efficiency_rating" class="border rounded-lg text-sm px-2 py-1.5 w-full mt-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
              <p v-else class="text-sm mt-1">{{ activeItem.efficiency_rating ?? "—" }}</p>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Timeliness</label>
              <select v-if="canRateActiveItem" v-model.number="activeItem.timeliness_rating" class="border rounded-lg text-sm px-2 py-1.5 w-full mt-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
              <p v-else class="text-sm mt-1">{{ activeItem.timeliness_rating ?? "—" }}</p>
            </div>
          </div>
          <p class="text-sm font-semibold mt-2">Average: {{ activeItem.row_average ?? "—" }}</p>

          <div class="mt-3">
            <label class="text-xs font-medium text-slate-500">Remarks</label>
            <input v-if="canRateActiveItem" v-model="activeItem.remarks" class="border rounded-lg px-3 py-2 text-sm w-full mt-1" />
            <p v-else class="text-sm text-slate-700 mt-1">{{ activeItem.remarks ?? "—" }}</p>
          </div>

          <AppButton v-if="canRateActiveItem" size="sm" class="mt-3" @click="rate(activeItem)">Save Rating</AppButton>
        </div>
        </template>
      </div>
    </template>

    <template #footer>
      <AppButton variant="secondary" @click="closeModal">Close</AppButton>
    </template>
  </AppModal>
</template>
