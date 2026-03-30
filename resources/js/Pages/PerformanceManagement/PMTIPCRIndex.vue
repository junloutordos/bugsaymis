<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

const props = defineProps({
  ipcrs: Array,
})

const searchQuery = ref("")

const statusClasses = ipcrStatusClass

const adjectivalRating = ipcrAdjectivalRating

const filteredIPCRs = computed(() => {
  const q = searchQuery.value.toLowerCase()
  if (!q) return props.ipcrs
  return props.ipcrs.filter(i =>
    i.title?.toLowerCase().includes(q) ||
    i.rating_period?.toLowerCase().includes(q) ||
    i.status?.toLowerCase().includes(q) ||
    i.user?.name?.toLowerCase().includes(q) ||
    i.user?.division?.division_name?.toLowerCase().includes(q)
  )
})
</script>

<template>
  <Head title="PMT IPCR Review" />
  <AdminLayout title="PMT IPCR Review">
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">IPCR Review — Performance Management Team</h1>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by employee, title, period, division..."
          class="flex-1 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
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
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Overall Avg.</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Adjectival Rating</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted to PMT</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="ipcr in filteredIPCRs" :key="ipcr.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-medium text-slate-800">{{ ipcr.user?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.user?.division?.division_name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.rating_period }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.title }}</td>
                <td class="px-4 py-3 text-center font-semibold text-slate-800">{{ ipcr.overall_average ?? '—' }}</td>
                <td class="px-4 py-3 text-center text-sm text-slate-700">{{ adjectivalRating(ipcr.overall_average) }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ${statusClasses(ipcr.status)}`">
                    {{ ipcr.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center text-xs text-slate-500">{{ ipcr.submitted_for_pmtreview_at ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <button
                    @click="router.visit(route('pmt-ipcr.show', ipcr.id))"
                    class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-700 transition-colors"
                    title="Review IPCR"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredIPCRs.length === 0">
                <td colspan="9" class="py-16 text-center text-slate-400 text-sm">No IPCRs submitted for PMT review.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
