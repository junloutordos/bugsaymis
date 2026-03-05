<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"

const props = defineProps({
  ipcrs: Array,
})

const searchQuery = ref("")

const statusClasses = ipcrStatusClass

const adjectivalRating = (avg) => {
  const n = parseFloat(avg)
  if (isNaN(n)) return '—'
  if (n >= 4.500) return 'Outstanding'
  if (n >= 3.500) return 'Very Satisfactory'
  if (n >= 2.500) return 'Satisfactory'
  if (n >= 1.500) return 'Unsatisfactory'
  return 'Poor'
}

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
    <div>
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">IPCR Review — Performance Management Team</h1>
      </div>

      <!-- Search -->
      <div class="bg-white p-4 rounded-xl shadow mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by employee, title, period, division..."
          class="w-full sm:w-1/2 rounded-lg border-gray-300 shadow-sm focus:ring-purple-500 focus:border-purple-500"
        />
      </div>

      <!-- Table -->
      <div class="overflow-x-auto bg-white p-4 rounded-xl shadow">
        <table class="min-w-full border border-gray-200 text-center text-sm">
          <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
            <tr>
              <th class="px-4 py-3">Employee</th>
              <th class="px-4 py-3">Division</th>
              <th class="px-4 py-3">Rating Period</th>
              <th class="px-4 py-3">Title</th>
              <th class="px-4 py-3">Overall Avg.</th>
              <th class="px-4 py-3">Adjectival Rating</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Submitted to PMT</th>
              <th class="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="ipcr in filteredIPCRs" :key="ipcr.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium">{{ ipcr.user?.name ?? '—' }}</td>
              <td class="px-4 py-3">{{ ipcr.user?.division?.division_name ?? '—' }}</td>
              <td class="px-4 py-3">{{ ipcr.rating_period }}</td>
              <td class="px-4 py-3">{{ ipcr.title }}</td>
              <td class="px-4 py-3 font-semibold">{{ ipcr.overall_average ?? '—' }}</td>
              <td class="px-4 py-3 font-semibold">{{ adjectivalRating(ipcr.overall_average) }}</td>
              <td class="px-4 py-3">
                <span :class="`inline-block px-3 py-1 rounded-full text-xs font-semibold ${statusClasses(ipcr.status)}`">
                  {{ ipcr.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-gray-500">
                {{ ipcr.submitted_for_pmtreview_at ?? '—' }}
              </td>
              <td class="px-4 py-3">
                <button
                  @click="router.visit(route('pmt-ipcr.show', ipcr.id))"
                  class="p-2 hover:bg-gray-100 rounded"
                  title="Review IPCR"
                >
                  <EyeIcon class="w-5 h-5 text-purple-600" />
                </button>
              </td>
            </tr>
            <tr v-if="filteredIPCRs.length === 0">
              <td colspan="9" class="px-4 py-6 text-gray-500">No IPCRs submitted for PMT review.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>
