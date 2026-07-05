<template>
  <Head title="Counseling Session Reports" />
  <AdminLayout title="Counseling Session Reports">
    <template #default>
      <div class="space-y-5">

        <AppPageHeader title="Counseling Session Reports" subtitle="Create and manage PSHS Counseling Session Report (CS) forms" />

        <!-- sub-nav -->
        <div class="flex items-center gap-2 text-sm flex-wrap">
          <Link href="/guidance/dashboard" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Dashboard</Link>
          <Link href="/guidance/consultations" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Consultations</Link>
          <span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium">Session Reports</span>
          <Link href="/guidance/reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Reports</Link>
        </div>

        <!-- Filter + New button -->
        <AppFilterBar>
          <input v-model="search" type="text" placeholder="Search by client name or CS No..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full sm:w-64" />
          <select v-model="filterSY" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All School Years</option>
            <option v-for="sy in schoolYears" :key="sy" :value="sy">SY {{ sy }}</option>
          </select>

          <template #actions>
            <AppButton @click="openCreate">+ New Session Report</AppButton>
          </template>
        </AppFilterBar>

        <!-- Table -->
        <AppTable :is-empty="!displayedReports.length" :skeleton-cols="8">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">CS No.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date Filed</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">SY</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Client Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Grade/Section</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Strategy</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Accomplished By</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Actions</th>
            </tr>
          </template>

          <tr v-for="r in displayedReports" :key="r.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-2.5 font-mono text-xs text-indigo-700 font-semibold">{{ r.cs_no }}</td>
            <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ fmtDate(r.date_filed) }}</td>
            <td class="px-4 py-2.5 text-slate-500">{{ r.school_year }}</td>
            <td class="px-4 py-2.5 font-medium text-slate-800">{{ r.client_name }}</td>
            <td class="px-4 py-2.5 text-slate-600">{{ r.grade_section ?? '—' }}</td>
            <td class="px-4 py-2.5">
              <AppBadge v-for="s in (r.referral_strategies ?? [])" :key="s" color="indigo" class="mr-1">{{ s }}</AppBadge>
            </td>
            <td class="px-4 py-2.5 text-slate-600">{{ r.accomplished_by_user?.name ?? '—' }}</td>
            <td class="px-4 py-2.5">
              <div class="flex items-center gap-2">
                <button @click="openEdit(r)" class="text-xs text-indigo-600 hover:underline font-medium">Edit</button>
                <a :href="`/guidance/session-reports/${r.id}/print`" target="_blank" class="text-xs text-slate-500 hover:underline font-medium">Print</a>
              </div>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="r in displayedReports" :key="r.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-mono text-xs text-indigo-700 font-semibold">{{ r.cs_no }}</p>
                  <p class="font-medium text-slate-800">{{ r.client_name }}</p>
                  <p class="text-xs text-slate-500">{{ r.grade_section ?? '—' }} &middot; SY {{ r.school_year }}</p>
                </div>
                <span class="text-xs text-slate-400 whitespace-nowrap">{{ fmtDate(r.date_filed) }}</span>
              </div>
              <div class="flex flex-wrap gap-1">
                <AppBadge v-for="s in (r.referral_strategies ?? [])" :key="s" color="indigo">{{ s }}</AppBadge>
              </div>
              <div class="flex items-center justify-between pt-1">
                <span class="text-xs text-slate-400">{{ r.accomplished_by_user?.name ?? '—' }}</span>
                <div class="flex items-center gap-3">
                  <button @click="openEdit(r)" class="text-xs text-indigo-600 hover:underline font-medium">Edit</button>
                  <a :href="`/guidance/session-reports/${r.id}/print`" target="_blank" class="text-xs text-slate-500 hover:underline font-medium">Print</a>
                </div>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No session reports found." />
          </template>
        </AppTable>

      </div>

      <!-- Create / Edit Modal -->
      <AppModal :show="showModal" :title="editing ? 'Edit Session Report — ' + form.cs_no : 'New Counseling Session Report'" size="2xl" @close="closeModal">
        <form @submit.prevent="saveReport" class="space-y-5">

          <!-- Row 1: Date Filed / School Year -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date Filed <span class="text-danger-600">*</span></label>
              <input v-model="form.date_filed" type="date" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">School Year <span class="text-danger-600">*</span></label>
              <input v-model="form.school_year" type="text" placeholder="e.g. 2025-2026" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>

          <!-- Client Profile -->
          <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
            <legend class="text-xs font-semibold text-slate-600 px-1">I. Client Profile</legend>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-danger-600">*</span></label>
              <input v-model="form.client_name" type="text" required placeholder="Full name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Age</label>
                <input v-model.number="form.client_age" type="number" min="1" max="99" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sex</label>
                <select v-model="form.client_sex" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="">—</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Batch</label>
                <input v-model="form.batch" type="text" placeholder="e.g. 2028" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Grade &amp; Section</label>
              <input v-model="form.grade_section" type="text" placeholder="e.g. Grade 11 — Einstein" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </fieldset>

          <!-- Referral Strategies -->
          <fieldset class="border border-slate-200 rounded-xl p-4">
            <legend class="text-xs font-semibold text-slate-600 px-1">II. Referral Details / Strategies <span class="text-danger-600">*</span></legend>
            <div class="flex flex-wrap gap-4 mt-2">
              <label v-for="s in strategyOptions" :key="s" class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" :value="s" v-model="form.referral_strategies" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                {{ s }}
              </label>
            </div>
            <p v-if="strategyError" class="text-xs text-danger-600 mt-1">Please select at least one strategy.</p>
          </fieldset>

          <!-- Concern -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Concern</label>
            <input v-model="form.concern" type="text" placeholder="e.g. Academic, Personal/Social" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Difficulties Presented -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Difficulties Presented</label>
            <textarea v-model="form.difficulties_presented" rows="3" placeholder="Describe the difficulties presented..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
          </div>

          <!-- Counselor's Notes -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Counselor's Notes</label>
            <textarea v-model="form.counselor_notes" rows="4" placeholder="Counselor's notes and observations..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
          </div>

          <!-- Accomplished By -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Accomplished By <span class="text-danger-600">*</span></label>
            <input v-model="form.accomplished_by_name" type="text" readonly class="w-full rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-600 cursor-not-allowed" />
            <input type="hidden" v-model="form.accomplished_by" />
          </div>

          <!-- Error message -->
          <p v-if="saveError" class="text-sm text-danger-600">{{ saveError }}</p>

          <!-- Actions -->
          <div class="flex justify-end gap-3 pt-2">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit" :loading="saving">{{ editing ? 'Update Report' : 'Create Report' }}</AppButton>
          </div>
        </form>
      </AppModal>

    </template>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  reports:     Array,
  schoolYears: Array,
  currentUser: Object,
  defaultSY:   String,
})

// ─── Filters ──────────────────────────────────────────────────────────────────
const search   = ref('')
const filterSY = ref('')

const displayedReports = computed(() => {
  let list = props.reports ?? []
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(r =>
      r.client_name.toLowerCase().includes(q) ||
      r.cs_no.toLowerCase().includes(q)
    )
  }
  if (filterSY.value) {
    list = list.filter(r => r.school_year === filterSY.value)
  }
  return list
})

// ─── Modal ────────────────────────────────────────────────────────────────────
const showModal = ref(false)
const editing   = ref(null) // null = create; GuidanceSessionReport id = edit

const strategyOptions = ['Referral', 'Called-In', 'Walk-In', 'Case Conference', 'Homevisit']
const strategyError  = ref(false)
const saving         = ref(false)
const saveError      = ref('')

const form = ref(blankForm())

function blankForm() {
  const today = new Date().toISOString().slice(0, 10)
  return {
    date_filed:              today,
    school_year:             props.defaultSY ?? '',
    client_name:             '',
    client_age:              null,
    client_sex:              '',
    grade_section:           '',
    batch:                   '',
    referral_strategies:     [],
    concern:                 '',
    difficulties_presented:  '',
    counselor_notes:         '',
    accomplished_by:         props.currentUser?.id ?? '',
    accomplished_by_name:    props.currentUser?.name ?? '',
  }
}

function openCreate() {
  editing.value  = null
  form.value     = blankForm()
  strategyError.value = false
  saveError.value     = ''
  showModal.value = true
}

function openEdit(r) {
  editing.value = r.id
  form.value = {
    date_filed:             r.date_filed ?? '',
    school_year:            r.school_year ?? '',
    client_name:            r.client_name ?? '',
    client_age:             r.client_age ?? null,
    client_sex:             r.client_sex ?? '',
    grade_section:          r.grade_section ?? '',
    batch:                  r.batch ?? '',
    referral_strategies:    r.referral_strategies ?? [],
    concern:                r.concern ?? '',
    difficulties_presented: r.difficulties_presented ?? '',
    counselor_notes:        r.counselor_notes ?? '',
    accomplished_by:        r.accomplished_by ?? props.currentUser?.id,
    accomplished_by_name:   r.accomplished_by_user?.name ?? props.currentUser?.name ?? '',
  }
  strategyError.value = false
  saveError.value     = ''
  showModal.value     = true
}

function closeModal() {
  showModal.value = false
}

async function saveReport() {
  strategyError.value = false
  saveError.value     = ''

  if (!form.value.referral_strategies.length) {
    strategyError.value = true
    return
  }

  saving.value = true
  try {
    const payload = { ...form.value }
    delete payload.accomplished_by_name

    if (editing.value) {
      await axios.put(`/guidance/session-reports/${editing.value}`, payload)
    } else {
      await axios.post('/guidance/session-reports', payload)
    }

    closeModal()
    router.reload({ preserveScroll: true })
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Failed to save. Please try again.'
  } finally {
    saving.value = false
  }
}

function fmtDate(d) {
  if (!d) return '—'
  const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, day).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>
