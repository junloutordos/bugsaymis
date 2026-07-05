<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppFilterBar from "@/Components/AppFilterBar.vue";
import AppTable from "@/Components/AppTable.vue";
import AppBadge from "@/Components/AppBadge.vue";
import AppIconButton from "@/Components/AppIconButton.vue";
import EmptyState from "@/Components/EmptyState.vue";
import PaginationControl from "@/Components/PaginationControl.vue";
import { EyeIcon } from "@heroicons/vue/24/outline";
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
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

function statusColor(status) {
  const map = {
    "New Target":                "blue",
    "For Review":                "amber",
    "Targets Approved":          "green",
    "Submitted for Rating":      "orange",
    "Rated & For PMT Review":    "purple",
    "Submitted to PMT":          "purple",
    "PMT Returned for Revision": "red",
    "Submitted to HR":           "blue",
    "Approved by PMT":           "green",
    "Director Signed":           "green",
    "Returned for Revision":     "red",
    "Rejected":                  "red",
  };
  return map[status] ?? "slate";
}

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
    <div class="space-y-5">

      <AppPageHeader title="My Unit" subtitle="Team IPCR performance overview." />

      <!-- Office(s) -->
      <div class="flex flex-wrap gap-2">
        <AppBadge v-for="office in offices" :key="office.id" color="indigo">{{ office.name }}</AppBadge>
      </div>

      <!-- Filters -->
      <AppFilterBar>
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
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="paginated.length === 0" :skeleton-cols="8">
        <template #head>
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
        </template>

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
            <AppBadge :color="statusColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
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
            <AppIconButton label="View" @click="viewIPCR(ipcr)">
              <EyeIcon class="w-4 h-4" />
            </AppIconButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="ipcr in paginated" :key="ipcr.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ ipcr.user?.name ?? "—" }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.user?.office?.name ?? "—" }}</p>
              </div>
              <AppBadge :color="statusColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ ipcr.title ?? "—" }} &middot; {{ ipcr.rating_period ?? "—" }}</p>
            <div class="flex items-center justify-between pt-1">
              <div class="text-xs text-slate-500">
                <template v-if="ipcr.overall_average">
                  <span class="font-semibold text-slate-800">{{ ipcr.overall_average }}</span>
                  ({{ ipcrAdjectivalRating(ipcr.overall_average) }})
                </template>
                <span v-else>—</span>
                <span class="ml-2">Submitted {{ formatDate(ipcr.submitted_for_rating_at) }}</span>
              </div>
              <AppIconButton label="View" @click="viewIPCR(ipcr)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IPCRs found" subtitle="No IPCRs found for your unit with the selected filters." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
