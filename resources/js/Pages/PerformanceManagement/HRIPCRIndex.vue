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
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">HR IPCR Review</h1>
        <!-- Batch Submit to PMT -->
        <div class="flex items-center gap-2 rounded-lg px-3 py-2 bg-white border border-slate-200 shadow-sm">
          <select
            v-model="submitToPMTPeriod"
            class="border-0 bg-transparent text-sm text-slate-700 focus:ring-0 p-0"
          >
            <option value="" disabled>— Period —</option>
            <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
          </select>
          <button
            @click="batchSubmitToPMT"
            :disabled="submittedToHRCount === 0 || !submitToPMTPeriod || isSubmitting"
            class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-3 py-1 rounded-lg text-sm font-medium transition-colors"
          >
            {{ isSubmitting ? 'Processing…' : `Submit to PMT (${submittedToHRCount})` }}
          </button>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by employee, title, or period..."
          class="flex-1 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
        <select
          v-model="selectedPeriod"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        >
          <option value="">All Periods</option>
          <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Division</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating Period</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Avg Rating</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted to HR</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="ipcr in filtered" :key="ipcr.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800 text-sm">{{ ipcr.user?.name ?? "—" }}</div>
                  <div class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</div>
                </td>
                <td class="px-4 py-3 text-xs text-slate-600">{{ ipcr.user?.division?.name ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.rating_period ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.title ?? "—" }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="statusClasses(ipcr.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                    {{ ipcr.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <template v-if="ipcr.overall_average">
                    <div class="font-semibold text-slate-800 text-sm">{{ ipcr.overall_average }}</div>
                    <div class="text-xs text-slate-400">{{ adjectival(ipcr.overall_average) }}</div>
                  </template>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="px-4 py-3 text-center text-xs text-slate-500">{{ formatDate(ipcr.submitted_to_hr_at) }}</td>
                <td class="px-4 py-3 text-center">
                  <button
                    @click="router.visit(route('hr-ipcr.show', ipcr.id))"
                    class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium"
                  >
                    <EyeIcon class="w-4 h-4" /> View
                  </button>
                </td>
              </tr>
              <tr v-if="filtered.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No IPCRs submitted to HR.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
