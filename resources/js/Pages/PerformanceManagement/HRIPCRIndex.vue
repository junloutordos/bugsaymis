<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import { ref, computed } from "vue"
import Swal from "sweetalert2"
import { useSubmit } from "@/Composables/useSubmit"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

const props = defineProps({
  ipcrs:         Array,
  ratingPeriods: { type: Array, default: () => [] },
})

const searchQuery    = ref("")
const selectedPeriod = ref("")
const statusClasses  = ipcrStatusClass
const adjectival     = ipcrAdjectivalRating

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return (props.ipcrs || []).filter(i => {
    const matchSearch  = !q || i.user?.name?.toLowerCase().includes(q) || i.title?.toLowerCase().includes(q) || i.rating_period?.toLowerCase().includes(q)
    const matchPeriod  = !selectedPeriod.value || i.rating_period === selectedPeriod.value
    return matchSearch && matchPeriod
  })
})

const formatDate = (val) => val ? new Date(val).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) : "—"

// ── Batch Submit to PMT ───────────────────────────────────────
const submitToPMTPeriod = ref(props.ratingPeriods[0] ?? "")
const submittedToHRCount = computed(() =>
  (props.ipcrs || []).filter(i =>
    i.status === 'Submitted to HR' &&
    (!submitToPMTPeriod.value || i.rating_period === submitToPMTPeriod.value)
  ).length
)

const { isSubmitting, submit } = useSubmit()

const batchSubmitToPMT = () => {
  if (!submitToPMTPeriod.value) return
  Swal.fire({
    title: "Submit to PMT?",
    text: `Submit all ${submittedToHRCount.value} IPCR(s) for "${submitToPMTPeriod.value}" to PMT?`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#7c3aed",
    confirmButtonText: "Yes, submit!",
  }).then(result => {
    if (result.isConfirmed) {
      submit.post(route('hr-ipcr.batchSubmitToPMT'), { rating_period: submitToPMTPeriod.value })
    }
  })
}
</script>

<template>
  <Head title="HR IPCR Review" />
  <AdminLayout title="HR IPCR Review">
    <div>
      <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
        <h1 class="text-xl font-bold text-gray-800">HR IPCR Review</h1>
        <!-- Batch Submit to PMT -->
        <div class="flex items-center gap-2 border border-purple-300 rounded-lg px-3 py-1.5 bg-purple-50">
          <select
            v-model="submitToPMTPeriod"
            class="border-0 bg-transparent text-sm text-purple-800 focus:ring-0 p-0"
          >
            <option value="" disabled>— Period —</option>
            <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
          </select>
          <button
            @click="batchSubmitToPMT"
            :disabled="submittedToHRCount === 0 || !submitToPMTPeriod || isSubmitting"
            class="bg-purple-600 hover:bg-purple-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-3 py-1 rounded text-sm font-medium"
          >
            {{ isSubmitting ? 'Processing…' : `Submit to PMT (${submittedToHRCount})` }}
          </button>
        </div>
      </div>

      <div class="bg-white p-4 rounded-xl shadow mb-4">
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by employee, title, or period..."
            class="flex-1 rounded-lg border-gray-300 shadow-sm text-sm"
          />
          <select
            v-model="selectedPeriod"
            class="rounded-lg border-gray-300 shadow-sm text-sm"
          >
            <option value="">All Periods</option>
            <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200 text-sm">
            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
              <tr>
                <th class="px-4 py-3 text-left">Employee</th>
                <th class="px-4 py-3 text-left">Division</th>
                <th class="px-4 py-3 text-left">Rating Period</th>
                <th class="px-4 py-3 text-left">Title</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Avg Rating</th>
                <th class="px-4 py-3 text-center">Submitted to HR</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="ipcr in filtered" :key="ipcr.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-800">{{ ipcr.user?.name ?? "—" }}</div>
                  <div class="text-xs text-gray-500">{{ ipcr.user?.position ?? "" }}</div>
                </td>
                <td class="px-4 py-3 text-gray-600 text-xs">{{ ipcr.user?.division?.name ?? "—" }}</td>
                <td class="px-4 py-3 text-gray-600">{{ ipcr.rating_period ?? "—" }}</td>
                <td class="px-4 py-3 text-gray-600">{{ ipcr.title ?? "—" }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="statusClasses(ipcr.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                    {{ ipcr.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center font-medium">
                  <template v-if="ipcr.overall_average">
                    {{ ipcr.overall_average }}
                    <div class="text-xs text-gray-400">{{ adjectival(ipcr.overall_average) }}</div>
                  </template>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ formatDate(ipcr.submitted_to_hr_at) }}</td>
                <td class="px-4 py-3 text-center">
                  <button
                    @click="router.visit(route('hr-ipcr.show', ipcr.id))"
                    class="inline-flex items-center gap-1 text-cyan-600 hover:text-cyan-800 text-sm font-medium"
                  >
                    <EyeIcon class="w-4 h-4" /> View
                  </button>
                </td>
              </tr>
              <tr v-if="filtered.length === 0">
                <td colspan="8" class="px-4 py-6 text-center text-gray-400">No IPCRs submitted to HR.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
