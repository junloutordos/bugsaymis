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
        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700"
      >
        {{ office.name }}
      </span>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm">

      <!-- Toolbar -->
      <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row gap-3">
        <input
          v-model="searchQuery"
          @input="resetPage"
          type="text"
          placeholder="Search by employee, title, period, or status..."
          class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
        <select
          v-model="selectedPeriod"
          @change="resetPage"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        >
          <option value="">All Periods</option>
          <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office / Unit</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating Period</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Avg Rating</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="paginated.length === 0">
              <td colspan="8" class="py-16 text-center text-slate-400 text-sm">
                No IPCRs found for your unit with the selected filters.
              </td>
            </tr>
            <tr
              v-for="ipcr in paginated"
              :key="ipcr.id"
              class="hover:bg-slate-50/60"
            >
              <td class="px-4 py-3">
                <div class="font-medium text-slate-800">{{ ipcr.user?.name ?? "—" }}</div>
                <div class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-slate-600 text-xs">
                {{ ipcr.user?.office?.name ?? "—" }}
              </td>
              <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.rating_period ?? "—" }}</td>
              <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.title ?? "—" }}</td>
              <td class="px-4 py-3 text-center">
                <span
                  :class="statusBadge(ipcr.status)"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap"
                >
                  {{ ipcr.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <template v-if="ipcr.overall_average">
                  <span class="font-semibold text-slate-800">{{ ipcr.overall_average }}</span>
                  <div class="text-xs text-slate-400">{{ ipcrAdjectivalRating(ipcr.overall_average) }}</div>
                </template>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td class="px-4 py-3 text-center text-xs text-slate-500">
                {{ formatDate(ipcr.submitted_for_rating_at) }}
              </td>
              <td class="px-4 py-3 text-center">
                <button
                  @click="viewIPCR(ipcr)"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                  title="View"
                >
                  <EyeIcon class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
            <PaginationControl
        :current-page="currentPage"
        :total-pages="totalPages"
        @prev="goToPage(currentPage - 1)"
        @next="goToPage(currentPage + 1)"
        @page="goToPage"
      />

    </div>
  </AdminLayout>
</template>
