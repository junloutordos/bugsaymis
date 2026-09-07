<script setup>
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppTable from "@/Components/AppTable.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import { EyeIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { useSubmit } from "@/Composables/useSubmit"
import Swal from "sweetalert2"

const props = defineProps({
  records: Array,
  openPeriods: Array,
})

const { isSubmitting, submit } = useSubmit()
const selectedPeriod = ref(props.openPeriods[0]?.id ?? null)
const searchQuery = ref("")
const currentPage = ref(1)
const PER_PAGE = 15

const DELETABLE_STATUSES = ["New Target", "Returned for Revision"]

const filteredRecords = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.records
  return props.records.filter(r =>
    (r.period?.label ?? "").toLowerCase().includes(q) || (r.status ?? "").toLowerCase().includes(q)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / PER_PAGE)))
const displayedRecords = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filteredRecords.value.slice(start, start + PER_PAGE)
})

function statusBadgeColor(status) {
  const map = {
    "New Target": "blue",
    "For Review": "amber",
    "Targets Approved": "green",
    "Submitted for Rating": "orange",
    "Rated & For PMT Review": "purple",
    "Submitted to PMT": "purple",
    "PMT Returned for Revision": "red",
    "Submitted to HR": "blue",
    "Approved by PMT": "green",
    "Director Signed": "green",
    "Returned for Revision": "red",
  }
  return map[status] ?? "slate"
}

function generateTargets() {
  submit((opts) => router.post(route("employee-ipcr-v2.generateTargets"), { rating_period_id: selectedPeriod.value }, opts))
}

function viewRecord(record) {
  router.get(route("employee-ipcr-v2.show", record.id))
}

function destroyRecord(record) {
  Swal.fire({
    title: "Are you sure?",
    text: "This IPCR V2 record will be permanently deleted!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#dc2626",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route("employee-ipcr-v2.destroy", record.id), {
        onSuccess: () => Swal.fire({ icon: "success", title: "Deleted", timer: 2000, showConfirmButton: false }),
      })
    }
  })
}
</script>

<template>
  <Head title="My IPCR V2" />
  <AdminLayout title="My IPCR V2">
    <div class="p-6 space-y-5">
      <AppPageHeader title="My IPCR V2" subtitle="Strategic / Core / Support Functions">
        <template #actions>
          <template v-if="openPeriods.length">
            <AppSelect v-model="selectedPeriod" :show-blank="false" class="w-56">
              <option v-for="p in openPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
            </AppSelect>
            <AppButton :disabled="isSubmitting || !selectedPeriod" @click="generateTargets">
              <PlusIcon class="w-4 h-4" /> Generate Targets
            </AppButton>
          </template>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <AppInput v-model="searchQuery" placeholder="Search by period or status..." class="min-w-[180px] flex-1 sm:flex-none sm:w-64" />
      </AppFilterBar>

      <AppTable :is-empty="!displayedRecords.length" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Period</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Final Rating</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="record in displayedRecords" :key="record.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ record.period?.label }}</td>
          <td class="px-4 py-3"><AppBadge :color="statusBadgeColor(record.status)">{{ record.status }}</AppBadge></td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ record.final_numeric_rating ?? "—" }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1">
              <AppIconButton label="View" @click="viewRecord(record)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="DELETABLE_STATUSES.includes(record.status)" label="Delete" variant="danger" @click="destroyRecord(record)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="record in displayedRecords" :key="'m-' + record.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-medium text-slate-800 truncate">{{ record.period?.label }}</p>
                <p class="text-xs text-slate-500">{{ record.final_numeric_rating ?? "—" }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(record.status)">{{ record.status }}</AppBadge>
            </div>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="View" @click="viewRecord(record)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="DELETABLE_STATUSES.includes(record.status)" label="Delete" variant="danger" @click="destroyRecord(record)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IPCR V2 records found." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>
