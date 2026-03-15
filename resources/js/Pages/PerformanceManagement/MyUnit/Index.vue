<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { EyeIcon } from "@heroicons/vue/24/outline";
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass";
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating";

const props = defineProps({
  offices:       Array,
  ipcrs:         Array,
  unitHead:      Object,
  ratingPeriods: { type: Array, default: () => [] },
});

// ---------- Filters ----------
const searchQuery       = ref("");
const selectedPeriod    = ref("");

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase();
  return (props.ipcrs || []).filter(ipcr => {
    const matchesSearch =
      !q ||
      ipcr.user?.name?.toLowerCase().includes(q) ||
      ipcr.title?.toLowerCase().includes(q) ||
      ipcr.rating_period?.toLowerCase().includes(q) ||
      ipcr.status?.toLowerCase().includes(q);

    const matchesPeriod =
      !selectedPeriod.value || ipcr.rating_period === selectedPeriod.value;

    return matchesSearch && matchesPeriod;
  });
});

// ---------- Pagination ----------
const perPage    = 10;
const currentPage = ref(1);
const totalPages  = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));

const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return filtered.value.slice(start, start + perPage);
});

const goToPage = (p) => {
  if (p >= 1 && p <= totalPages.value) currentPage.value = p;
};

// Reset page when filters change
const resetPage = () => { currentPage.value = 1; };

const statusBadge = ipcrStatusClass;

const viewIPCR = (ipcr) => {
  router.get(route("my-unit-ipcr.show", ipcr.id));
};

const formatDate = (val) => {
  if (!val) return "—";
  return new Date(val).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
};
</script>

<template>
  <Head title="My Unit" />
  <AdminLayout title="My Unit">

    <!-- Office(s) header -->
    <div class="mb-4 flex flex-wrap gap-2">
      <span
        v-for="office in offices"
        :key="office.id"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800"
      >
        {{ office.name }}
      </span>
    </div>

    <div class="bg-white p-4 rounded-lg shadow">

      <!-- Toolbar -->
      <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input
          v-model="searchQuery"
          @input="resetPage"
          type="text"
          placeholder="Search by employee, title, period, or status..."
          class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
        />
        <select
          v-model="selectedPeriod"
          @change="resetPage"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="">All Periods</option>
          <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border-collapse">
          <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
            <tr>
              <th class="border px-4 py-2 text-left">Employee</th>
              <th class="border px-4 py-2 text-left">Office / Unit</th>
              <th class="border px-4 py-2 text-left">Rating Period</th>
              <th class="border px-4 py-2 text-left">Title</th>
              <th class="border px-4 py-2 text-center">Status</th>
              <th class="border px-4 py-2 text-center">Avg Rating</th>
              <th class="border px-4 py-2 text-center">Submitted</th>
              <th class="border px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="paginated.length === 0">
              <td colspan="8" class="border px-4 py-6 text-center text-gray-400">
                No IPCRs found for your unit with the selected filters.
              </td>
            </tr>
            <tr
              v-for="ipcr in paginated"
              :key="ipcr.id"
              class="hover:bg-gray-50"
            >
              <td class="border px-4 py-2">
                <div class="font-medium text-gray-800">{{ ipcr.user?.name ?? "—" }}</div>
                <div class="text-xs text-gray-500">{{ ipcr.user?.position ?? "" }}</div>
              </td>
              <td class="border px-4 py-2 text-gray-600 text-xs">
                {{ ipcr.user?.office?.name ?? "—" }}
              </td>
              <td class="border px-4 py-2 text-gray-600">{{ ipcr.rating_period ?? "—" }}</td>
              <td class="border px-4 py-2 text-gray-600">{{ ipcr.title ?? "—" }}</td>
              <td class="border px-4 py-2 text-center">
                <span
                  :class="statusBadge(ipcr.status)"
                  class="px-2 py-1 text-xs font-semibold rounded-full whitespace-nowrap"
                >
                  {{ ipcr.status }}
                </span>
              </td>
              <td class="border px-4 py-2 text-center font-medium">
                <template v-if="ipcr.overall_average">
                  {{ ipcr.overall_average }}
                  <div class="text-xs text-gray-400">{{ ipcrAdjectivalRating(ipcr.overall_average) }}</div>
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="border px-4 py-2 text-center text-xs text-gray-500">
                {{ formatDate(ipcr.submitted_for_rating_at) }}
              </td>
              <td class="border px-4 py-2 text-center">
                <button
                  @click="viewIPCR(ipcr)"
                  class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 text-sm font-medium"
                >
                  <EyeIcon class="w-4 h-4" /> View
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
        <span>Page {{ currentPage }} of {{ totalPages }} ({{ filtered.length }} records)</span>
        <div class="flex gap-1">
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-3 py-1 border rounded disabled:opacity-40 hover:bg-gray-100"
          >&laquo;</button>
          <button
            v-for="p in totalPages"
            :key="p"
            @click="goToPage(p)"
            :class="p === currentPage ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'"
            class="px-3 py-1 border rounded"
          >{{ p }}</button>
          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 border rounded disabled:opacity-40 hover:bg-gray-100"
          >&raquo;</button>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
