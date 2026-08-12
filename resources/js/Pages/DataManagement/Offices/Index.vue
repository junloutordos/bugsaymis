<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppButton from "@/Components/AppButton.vue";
import AppIconButton from "@/Components/AppIconButton.vue";
import AppFilterBar from "@/Components/AppFilterBar.vue";
import AppTable from "@/Components/AppTable.vue";
import AppModal from "@/Components/AppModal.vue";
import EmptyState from "@/Components/EmptyState.vue";
import { PencilSquareIcon, TrashIcon, QrCodeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ offices: Array, divisions: Array, users: Array });
const officesList = ref(props.offices || []);
const usersList = ref(props.users || []);
const form = useForm({ id: null, name: '', division_id: null, unit_head: null });
const showModal = ref(false);

// Search & pagination (client-side, mirror Users template)
const searchQuery = ref('')
const appliedSearchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// Responsive: track window width to switch to card layout on small screens
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)

const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

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

const openModal = (office = null) => {
  if (office) {
    form.reset();
    form.id = office.id;
    form.name = office.name;
    form.division_id = office.division_id ?? null;
    form.unit_head = office.unit_head ?? null;
  } else {
    form.reset();
  }
  showModal.value = true;
};

const closeModal = () => { showModal.value = false; form.reset(); };

const submit = () => {
  if (form.id) {
    form.put(route('offices.update', form.id), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Office updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) }
    });
  } else {
    form.post(route('offices.store'), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Office added', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) }
    });
  }
};

const remove = (office) => {
  Swal.fire({
    title: 'Delete this office/unit?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then((res) => {
    if (!res.isConfirmed) return
    import('@inertiajs/vue3').then(({ router }) => {
      router.delete(route('offices.destroy', office.id), {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Office deleted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
        onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errors || {}).flat().join('\n') }) }
      })
    })
  })
};

// ── QR Survey modal ─────────────────────────────────────────────────────────
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

const regenerateToken = () => {
  Swal.fire({
    title: 'Regenerate QR code?',
    text: 'The old QR code (and any printed copies) will stop working. You must reprint and replace them.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, regenerate',
    cancelButtonText: 'Cancel'
  }).then((res) => {
    if (!res.isConfirmed) return
    import('@inertiajs/vue3').then(({ router }) => {
      router.post(route('offices.qr-survey.regenerate', qrOffice.value.id), {}, {
        onSuccess: () => {
          Swal.fire({ icon: 'success', title: 'QR code regenerated', timer: 1200, showConfirmButton: false })
            .then(() => { window.location.reload() })
        },
        onError: () => Swal.fire({ icon: 'error', title: 'Failed to regenerate' })
      })
    })
  })
};

const toggleQrSurvey = () => {
  import('@inertiajs/vue3').then(({ router }) => {
    router.put(route('offices.qr-survey.toggle', qrOffice.value.id), {}, {
      onSuccess: () => { window.location.reload() },
      onError: () => Swal.fire({ icon: 'error', title: 'Failed to update survey status' })
    })
  })
};
</script>

<template>
  <Head title="Offices" />
  <AdminLayout title="Data Management">
    <div class="space-y-5">

      <AppPageHeader title="Offices / Units" subtitle="Manage offices and organizational units">
        <template #actions>
          <AppButton @click.prevent="openModal()">+ New Office</AppButton>
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
      <AppTable :is-empty="!filteredOffices.length" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Division</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Unit Head</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="o in filteredOffices" :key="o.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ o.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ o.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ o.division?.division_name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ o.unitHeadUser?.name ?? o.unit_head_user?.name ?? '—' }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="QR Survey" @click.prevent="openQrModal(o)">
                <QrCodeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" @click.prevent="openModal(o)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click.prevent="remove(o)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="o in filteredOffices" :key="o.id" class="p-4 space-y-2">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-xs text-slate-400">ID: {{ o.id }}</div>
                <div class="text-sm font-semibold text-slate-800">{{ o.name }}</div>
                <div class="text-xs text-slate-500 mt-1">Division: {{ o.division?.division_name ?? '—' }}</div>
                <div class="text-xs text-slate-500">Unit Head: {{ o.unitHeadUser?.name ?? o.unit_head_user?.name ?? '—' }}</div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <AppButton size="sm" @click.prevent="openQrModal(o)">QR Survey</AppButton>
                <AppButton size="sm" @click.prevent="openModal(o)">Edit</AppButton>
                <AppButton size="sm" variant="danger" @click.prevent="remove(o)">Delete</AppButton>
              </div>
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

      <!-- Modal -->
      <AppModal :show="showModal" :title="form.id ? 'Edit Office' : 'New Office'" @close="closeModal">
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
            <input v-model="form.name" type="text"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.name" class="text-danger-600 text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Division</label>
            <select v-model="form.division_id"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="" disabled>Select division</option>
              <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.division_name }}</option>
            </select>
            <p v-if="form.errors.division_id" class="text-danger-600 text-xs mt-1">{{ form.errors.division_id }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Unit Head</label>
            <select v-model="form.unit_head"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="">-- No unit head --</option>
              <option v-for="u in usersList" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <p v-if="form.errors.unit_head" class="text-danger-600 text-xs mt-1">{{ form.errors.unit_head }}</p>
          </div>
          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click.prevent="closeModal">Cancel</AppButton>
            <AppButton type="submit" :disabled="form.processing" :loading="form.processing">
              {{ form.processing ? 'Saving...' : 'Save' }}
            </AppButton>
          </div>
        </form>
      </AppModal>
      <!-- QR Survey Modal -->
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
            <AppButton size="sm" variant="secondary" @click.prevent="toggleQrSurvey">
              {{ qrOffice.qr_survey_enabled ? 'Disable Survey' : 'Enable Survey' }}
            </AppButton>
            <AppButton size="sm" variant="danger" @click.prevent="regenerateToken">Regenerate QR</AppButton>
          </div>
          <p class="text-[11px] text-slate-400">
            Regenerating the QR code invalidates any previously printed copies — reprint and replace them after regenerating.
          </p>
        </div>
      </AppModal>
    </div>
  </AdminLayout>
</template>
