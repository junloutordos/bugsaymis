<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'
import { PencilSquareIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  applicants: { type: Object, required: true },
  filters:    { type: Object, default: () => ({}) },
})

const page = usePage()

// ── Filters ────────────────────────────────────────────────────────────────────
const search  = ref(props.filters?.search ?? '')
const status  = ref(props.filters?.status ?? '')
const isLoading = ref(false)
let debounceTimer = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('recruitment.applicants.index'), {
      search: search.value || undefined,
      status: status.value || undefined,
    }, {
      preserveState: true, replace: true,
      only: ['applicants', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))
watch(status, () => applyFilters(true))

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('recruitment.applicants.index'), {
    search: search.value || undefined,
    status: status.value || undefined,
    page: p,
  }, {
    preserveState: true, replace: true,
    only: ['applicants', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

// ── Status badge ───────────────────────────────────────────────────────────────
const statusColors = {
  active:      'green',
  blacklisted: 'red',
  hired:       'blue',
  withdrawn:   'slate',
}

// ── Modal ──────────────────────────────────────────────────────────────────────
const showModal   = ref(false)
const editingItem = ref(null)
const isSubmitting = ref(false)
const errors      = ref({})

const emptyForm = () => ({
  first_name: '', middle_name: '', last_name: '', suffix: '',
  birthdate: '', address: '', civil_status: '',
  email: '', contact_number: '',
  eligibility: '', prc_license_no: '',
  school: '', course: '', year_graduated: '',
  source: '',
})

const form = ref(emptyForm())

const openModal = (applicant = null) => {
  editingItem.value = applicant
  errors.value = {}
  form.value = applicant ? {
    first_name:    applicant.first_name,
    middle_name:   applicant.middle_name   ?? '',
    last_name:     applicant.last_name,
    suffix:        applicant.suffix        ?? '',
    birthdate:     applicant.birthdate?.slice(0, 10) ?? '',
    address:       applicant.address       ?? '',
    civil_status:  applicant.civil_status  ?? '',
    email:         applicant.email,
    contact_number:applicant.contact_number?? '',
    eligibility:   applicant.eligibility   ?? '',
    prc_license_no:applicant.prc_license_no?? '',
    school:        applicant.school        ?? '',
    course:        applicant.course        ?? '',
    year_graduated:applicant.year_graduated?? '',
    source:        applicant.source        ?? '',
  } : emptyForm()
  showModal.value = true
}

const closeModal = () => { showModal.value = false; editingItem.value = null }

const submit = () => {
  isSubmitting.value = true
  errors.value = {}
  const isEdit = !!editingItem.value
  const url    = isEdit
    ? route('recruitment.applicants.update', editingItem.value.id)
    : route('recruitment.applicants.store')
  const method = isEdit ? 'put' : 'post'

  router[method](url, form.value, {
    onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: isEdit ? 'Updated!' : 'Applicant added!', timer: 1500, showConfirmButton: false }) },
    onError: (e) => { errors.value = e },
    onFinish: () => { isSubmitting.value = false },
  })
}

// ── Delete ─────────────────────────────────────────────────────────────────────
const deleteApplicant = async (applicant) => {
  const confirmed = await confirmAction({
    title: `Remove ${applicant.last_name}, ${applicant.first_name}?`,
    text: 'This cannot be undone.',
    confirmText: 'Remove',
    icon: 'warning',
  })
  if (!confirmed) return

  router.delete(route('recruitment.applicants.destroy', applicant.id), {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Removed!', timer: 1200, showConfirmButton: false }),
    onError:   (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}
</script>

<template>
  <Head title="Applicant Pool — Recruitment" />
  <AdminLayout title="Applicant Pool">
    <div>
      <AppPageHeader title="Applicant Pool">
        <template #actions>
          <AppButton @click="openModal()">+ Add Applicant</AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="mb-4 px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <!-- Filter bar -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-[180px] sm:max-w-xs">
          <input v-model="search" type="text" placeholder="Search name or email…"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">…</span>
        </div>
        <select v-model="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="hired">Hired</option>
          <option value="blacklisted">Blacklisted</option>
          <option value="withdrawn">Withdrawn</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!applicants.data?.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Email</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Eligibility</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PRC License</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Applications</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="applicant in applicants.data" :key="applicant.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ applicant.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <div class="font-medium text-slate-800">
              {{ applicant.last_name }}, {{ applicant.first_name }}
              <span v-if="applicant.suffix" class="text-slate-400 text-xs"> {{ applicant.suffix }}</span>
            </div>
            <div v-if="applicant.course" class="text-xs text-slate-400">{{ applicant.course }}</div>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ applicant.email }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ applicant.eligibility ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ applicant.prc_license_no ?? '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge color="slate">{{ applicant.applications_count ?? 0 }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColors[applicant.status] ?? 'slate'">{{ applicant.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
              <AppButton as="link" size="sm" :href="route('recruitment.applicants.show', applicant.id)">View</AppButton>
              <AppIconButton label="Edit" @click="openModal(applicant)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
              <AppIconButton label="Remove" variant="danger" @click="deleteApplicant(applicant)"><TrashIcon class="w-4 h-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="applicant in applicants.data" :key="applicant.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">
                  {{ applicant.last_name }}, {{ applicant.first_name }}
                  <span v-if="applicant.suffix" class="text-slate-400 text-xs"> {{ applicant.suffix }}</span>
                </p>
                <p class="text-xs text-slate-400">{{ applicant.email }}</p>
                <p v-if="applicant.course" class="text-xs text-slate-400">{{ applicant.course }}</p>
              </div>
              <AppBadge :color="statusColors[applicant.status] ?? 'slate'">{{ applicant.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">
              {{ applicant.eligibility ?? '—' }} &middot; {{ applicant.prc_license_no ?? '—' }} &middot; {{ applicant.applications_count ?? 0 }} application(s)
            </p>
            <div class="flex items-center gap-1 pt-1">
              <AppButton as="link" size="sm" :href="route('recruitment.applicants.show', applicant.id)">View</AppButton>
              <AppIconButton label="Edit" @click="openModal(applicant)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
              <AppIconButton label="Remove" variant="danger" @click="deleteApplicant(applicant)"><TrashIcon class="w-4 h-4" /></AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No applicants found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="applicants.current_page"
            :total-pages="applicants.last_page"
            :total="applicants.total"
            @prev="goToPage(applicants.current_page - 1)"
            @next="goToPage(applicants.current_page + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>
    </div>

    <!-- ── Add / Edit Modal ──────────────────────────────────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-2xl shadow-xl relative max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ editingItem ? 'Edit Applicant' : 'Add Applicant' }}</h2>
          <AppIconButton label="Close" @click="closeModal"><XMarkIcon class="h-4 w-4" /></AppIconButton>
        </div>

        <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
          <!-- Personal Info -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">First Name *</label>
              <input v-model="form.first_name" type="text" required
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.first_name" class="text-danger-500 text-xs mt-1">{{ errors.first_name }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Middle Name</label>
              <input v-model="form.middle_name" type="text"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Last Name *</label>
              <input v-model="form.last_name" type="text" required
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.last_name" class="text-danger-500 text-xs mt-1">{{ errors.last_name }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Suffix</label>
              <input v-model="form.suffix" type="text" placeholder="Jr., III, etc."
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Birthdate</label>
              <input v-model="form.birthdate" type="date"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Civil Status</label>
              <select v-model="form.civil_status"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">— Select —</option>
                <option value="single">Single</option>
                <option value="married">Married</option>
                <option value="widowed">Widowed</option>
                <option value="separated">Separated</option>
              </select>
            </div>
          </div>

          <!-- Contact -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Email *</label>
              <input v-model="form.email" type="email" required
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.email" class="text-danger-500 text-xs mt-1">{{ errors.email }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Contact Number</label>
              <input v-model="form.contact_number" type="text"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
              <textarea v-model="form.address" rows="2"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
            </div>
          </div>

          <!-- Qualifications -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">CSC Eligibility</label>
              <input v-model="form.eligibility" type="text" placeholder="e.g. CS Professional"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">PRC License No.</label>
              <input v-model="form.prc_license_no" type="text"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">School</label>
              <input v-model="form.school" type="text"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Course / Degree</label>
              <input v-model="form.course" type="text"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Year Graduated</label>
              <input v-model="form.year_graduated" type="number" min="1970" :max="new Date().getFullYear()"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Source</label>
              <input v-model="form.source" type="text" placeholder="e.g. Walk-in, CSC Portal"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit" :loading="isSubmitting">
              {{ editingItem ? 'Update' : 'Add Applicant' }}
            </AppButton>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
