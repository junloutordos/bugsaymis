<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

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
  active:      'bg-green-100 text-green-700',
  blacklisted: 'bg-red-100 text-red-600',
  hired:       'bg-blue-100 text-blue-700',
  withdrawn:   'bg-gray-100 text-gray-500',
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
  const result = await Swal.fire({
    title: `Remove ${applicant.last_name}, ${applicant.first_name}?`,
    text: 'This cannot be undone.',
    icon: 'warning', showCancelButton: true,
    confirmButtonColor: '#ef4444', confirmButtonText: 'Remove', reverseButtons: true,
  })
  if (!result.isConfirmed) return

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
      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Applicant Pool</h1>
        <button @click="openModal()"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + Add Applicant
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
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
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-xl">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
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
            </thead>
            <tbody class="divide-y divide-slate-100">
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
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                    {{ applicant.applications_count ?? 0 }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                        :class="{
                          'bg-emerald-50 text-emerald-700': applicant.status === 'active',
                          'bg-emerald-50 text-emerald-700': applicant.status === 'hired',
                          'bg-red-50 text-red-600':         applicant.status === 'blacklisted',
                          'bg-slate-100 text-slate-600':    applicant.status === 'withdrawn',
                        }">
                    {{ applicant.status }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1 justify-center">
                    <Link :href="route('recruitment.applicants.show', applicant.id)"
                          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                      View
                    </Link>
                    <button @click="openModal(applicant)"
                            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                      Edit
                    </button>
                    <button @click="deleteApplicant(applicant)"
                            class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                      Remove
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="applicants.data?.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No applicants found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="applicants.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click="goToPage(applicants.current_page - 1)"
                  :disabled="applicants.current_page === 1 || isLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
          <span>Page {{ applicants.current_page }} of {{ applicants.last_page }}</span>
          <button @click="goToPage(applicants.current_page + 1)"
                  :disabled="applicants.current_page === applicants.last_page || isLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>

    <!-- ── Add / Edit Modal ──────────────────────────────────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-2xl shadow-xl relative max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ editingItem ? 'Edit Applicant' : 'Add Applicant' }}</h2>
          <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">✕</button>
        </div>

        <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
          <!-- Personal Info -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">First Name *</label>
              <input v-model="form.first_name" type="text" required
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.first_name" class="text-red-500 text-xs mt-1">{{ errors.first_name }}</p>
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
              <p v-if="errors.last_name" class="text-red-500 text-xs mt-1">{{ errors.last_name }}</p>
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
              <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email }}</p>
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
            <button type="button" @click="closeModal"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Cancel
            </button>
            <button type="submit" :disabled="isSubmitting"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ isSubmitting ? 'Saving…' : (editingItem ? 'Update' : 'Add Applicant') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
