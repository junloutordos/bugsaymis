<script setup>
import { Head } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppButton from "@/Components/AppButton.vue";
import AppIconButton from "@/Components/AppIconButton.vue";
import AppFilterBar from "@/Components/AppFilterBar.vue";
import AppTable from "@/Components/AppTable.vue";
import AppModal from "@/Components/AppModal.vue";
import EmptyState from "@/Components/EmptyState.vue";
import { QrCodeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ offices: Array });
const officesList = ref(props.offices || []);

// Search & pagination (client-side, mirror Offices/Index template)
const searchQuery = ref('')
const appliedSearchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filteredOffices = computed(() => {
  const q = appliedSearchQuery.value.trim().toLowerCase()
  let results = officesList.value.filter(o =>
    o.name?.toLowerCase().includes(q) ||
    (o.division?.division_name || '').toLowerCase().includes(q)
  )
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil((officesList.value.filter(o => {
  const q = appliedSearchQuery.value.trim().toLowerCase()
  return o.name?.toLowerCase().includes(q) || (o.division?.division_name || '').toLowerCase().includes(q)
}).length) / perPage)))

function applyFilters() {
  appliedSearchQuery.value = searchQuery.value
  currentPage.value = 1
}

function clearFilters() {
  searchQuery.value = ''
  appliedSearchQuery.value = ''
  currentPage.value = 1
}

// ── QR Survey modal (view/print only — no edit, delete, regenerate, toggle) ──
const showQrModal = ref(false);
const qrOffice = ref(null);

const openQrModal = (office) => {
  qrOffice.value = office;
  showQrModal.value = true;
};

const closeQrModal = () => {
  showQrModal.value = false;
  qrOffice.value = null;
};

const copySurveyUrl = async () => {
  try {
    await navigator.clipboard.writeText(qrOffice.value.survey_url);
    Swal.fire({ icon: 'success', title: 'Link copied', timer: 1000, showConfirmButton: false });
  } catch {
    Swal.fire({ icon: 'error', title: 'Could not copy link' });
  }
};

const downloadQrPdf = () => {
  window.open(route('offices.qr-survey.pdf', qrOffice.value.id), '_blank');
};
</script>

<template>
  <Head title="Office QR Codes" />
  <AdminLayout title="CSM Feedback">
    <div class="space-y-5">

      <AppPageHeader title="Office QR Codes" subtitle="View and print each office's client satisfaction survey QR code">
        <template #actions>
          <AppButton as="link" variant="secondary" :href="route('csm.dashboard')">Dashboard</AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <input v-model="searchQuery" type="text" placeholder="Search offices..."
          @keydown.enter.prevent="applyFilters"
          class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <template #actions>
          <AppButton size="sm" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredOffices.length" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Division</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Survey Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="o in filteredOffices" :key="o.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ o.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ o.division?.division_name ?? '—' }}</td>
          <td class="px-4 py-3">
            <span :class="[
                'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium',
                o.qr_survey_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'
              ]">
              {{ o.qr_survey_enabled ? 'Active' : 'Disabled' }}
            </span>
          </td>
          <td class="px-4 py-3">
            <AppIconButton label="View QR" @click.prevent="openQrModal(o)">
              <QrCodeIcon class="w-4 h-4" />
            </AppIconButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="o in filteredOffices" :key="o.id" class="p-4 space-y-2">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-sm font-semibold text-slate-800">{{ o.name }}</div>
                <div class="text-xs text-slate-500 mt-1">Division: {{ o.division?.division_name ?? '—' }}</div>
                <span :class="[
                    'inline-flex items-center gap-1 mt-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium',
                    o.qr_survey_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'
                  ]">
                  {{ o.qr_survey_enabled ? 'Active' : 'Disabled' }}
                </span>
              </div>
              <AppButton size="sm" @click.prevent="openQrModal(o)">View QR</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No offices found" />
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

      <!-- QR Survey Modal (view/print only) -->
      <AppModal :show="showQrModal" title="Office QR Survey" @close="closeQrModal">
        <div v-if="qrOffice" class="space-y-4">
          <div>
            <p class="text-xs text-slate-500">Client Satisfaction Survey QR code for</p>
            <p class="text-sm font-bold text-indigo-700">{{ qrOffice.name }}</p>
          </div>

          <div class="flex flex-col items-center gap-3 py-4 bg-indigo-50/60 rounded-xl border border-indigo-100">
            <img :src="route('offices.qr-survey.preview', qrOffice.id)" alt="QR Survey Code"
              class="w-40 h-40 bg-white rounded-lg border border-slate-200 p-2" />
            <span class="text-[11px] font-medium text-indigo-600 uppercase tracking-wide">Scan to give feedback</span>
            <span :class="[
                'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium',
                qrOffice.qr_survey_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'
              ]">
              {{ qrOffice.qr_survey_enabled ? 'Survey Active' : 'Survey Disabled' }}
            </span>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Public survey link</label>
            <div class="flex gap-2">
              <input :value="qrOffice.survey_url" readonly
                class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs text-slate-600" />
              <AppButton size="sm" variant="secondary" @click.prevent="copySurveyUrl">Copy</AppButton>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-100">
            <AppButton size="sm" @click.prevent="downloadQrPdf">Download Printable PDF</AppButton>
          </div>
          <p class="text-[11px] text-slate-400">
            To regenerate or enable/disable this QR code, contact an Administrator under Data Management &gt; Offices.
          </p>
        </div>
      </AppModal>
    </div>
  </AdminLayout>
</template>
