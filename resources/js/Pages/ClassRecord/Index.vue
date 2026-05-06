<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { badgeBase } from '@/Composables/useStatusBadge.js'
import Swal from 'sweetalert2'
import { PlusIcon, ArrowRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  classRecords:   Array,
  gradingOptions: Array,
  isAdmin:        { type: Boolean, default: false },
})

// ── Search ────────────────────────────────────────────────────────────────────
const searchQuery = ref('')
const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return props.classRecords
  return props.classRecords.filter(r =>
    r.subject_name?.toLowerCase().includes(q) ||
    r.year_level_section?.toLowerCase().includes(q) ||
    r.school_year?.toLowerCase().includes(q) ||
    r.teacher?.name?.toLowerCase().includes(q)
  )
})

// ── Status badge ──────────────────────────────────────────────────────────────
function statusBadge(status) {
  return {
    draft:     'bg-slate-100 text-slate-600',
    submitted: 'bg-blue-100 text-blue-700',
    checked:   'bg-emerald-100 text-emerald-700',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

// ── Quarter progress dots ──────────────────────────────────────────────────────
function quarterDot(record, q) {
  const quarter = record.quarters?.find(qt => qt.quarter === q)
  if (!quarter)          return 'bg-slate-200 text-slate-400'
  if (quarter.is_locked) return 'bg-emerald-500 text-white'
  return 'bg-amber-400 text-white'
}

// ── Create modal ──────────────────────────────────────────────────────────────
const showModal    = ref(false)
const creating     = ref(false)
const createErrors = ref({})
const form = ref({
  subject_name:       '',
  year_level_section: '',
  school_year:        '',
  grading_option_id:  '',
})

function openCreate() {
  form.value = { subject_name: '', year_level_section: '', school_year: '', grading_option_id: '' }
  createErrors.value = {}
  showModal.value = true
}

async function handleCreate() {
  creating.value     = true
  createErrors.value = {}
  try {
    const { data } = await axios.post(route('class-records.store'), form.value)
    showModal.value = false
    router.visit(route('class-records.page.show', data.data.id))
  } catch (err) {
    if (err.response?.status === 422) {
      createErrors.value = err.response.data.errors ?? {}
    } else {
      Swal.fire('Error', err.response?.data?.message ?? 'Could not create class record.', 'error')
    }
  } finally {
    creating.value = false
  }
}

function navigateTo(record) {
  router.visit(route('class-records.page.show', record.id))
}

const selectedOption = computed(() =>
  props.gradingOptions?.find(o => o.id == form.value.grading_option_id) ?? null
)
</script>

<template>
  <Head title="Class Records" />
  <AdminLayout title="Class Records">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Class Records</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage grade class records per subject and section</p>
        </div>
        <button @click="openCreate"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="h-4 w-4" /> New Class Record
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search by subject, section, or school year…"
            class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Subject</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Year Level &amp; Section</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">School Year</th>
                <th v-if="isAdmin" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Teacher</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Grading Option</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Quarters</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!filtered.length">
                <td :colspan="isAdmin ? 8 : 7" class="py-16 text-center">
                  <p class="text-slate-400 text-sm">No class records yet.</p>
                  <button @click="openCreate"
                    class="mt-3 inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                    <PlusIcon class="h-4 w-4" /> Create your first class record
                  </button>
                </td>
              </tr>
              <tr v-for="r in filtered" :key="r.id"
                class="hover:bg-slate-50/60 cursor-pointer"
                @click="navigateTo(r)">
                <td class="px-4 py-3 font-medium text-slate-800">{{ r.subject_name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ r.year_level_section }}</td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ r.school_year }}</td>
                <td v-if="isAdmin" class="px-4 py-3 text-slate-600">{{ r.teacher?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">{{ r.grading_option?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadge(r.status)]">
                    {{ r.status === 'checked' ? 'Checked ✓' : r.status.charAt(0).toUpperCase() + r.status.slice(1) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <span v-for="q in [1,2,3,4]" :key="q"
                      :class="['inline-flex items-center justify-center h-5 w-5 rounded-full text-[9px] font-bold', quarterDot(r, q)]">
                      Q{{ q }}
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3" @click.stop>
                  <button @click="navigateTo(r)"
                    class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                    Open <ArrowRightIcon class="h-3.5 w-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">New Class Record</h2>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
          </div>

          <form @submit.prevent="handleCreate" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Subject Name <span class="text-red-500">*</span></label>
              <input v-model="form.subject_name" type="text" placeholder="e.g. Chemistry" required
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.subject_name ? 'border-red-400' : 'border-slate-200'" />
              <p v-if="createErrors.subject_name" class="text-xs text-red-500 mt-1">{{ createErrors.subject_name[0] }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Year Level &amp; Section <span class="text-red-500">*</span></label>
              <input v-model="form.year_level_section" type="text" placeholder="e.g. G-10 Graviton" required
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.year_level_section ? 'border-red-400' : 'border-slate-200'" />
              <p v-if="createErrors.year_level_section" class="text-xs text-red-500 mt-1">{{ createErrors.year_level_section[0] }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">School Year <span class="text-red-500">*</span></label>
              <input v-model="form.school_year" type="text" placeholder="e.g. 2025-2026" required
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.school_year ? 'border-red-400' : 'border-slate-200'" />
              <p v-if="createErrors.school_year" class="text-xs text-red-500 mt-1">{{ createErrors.school_year[0] }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Grading Option <span class="text-red-500">*</span></label>
              <select v-model="form.grading_option_id" required
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.grading_option_id ? 'border-red-400' : 'border-slate-200'">
                <option value="">— Select a grading option —</option>
                <option v-for="opt in gradingOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
              </select>
              <p v-if="selectedOption?.description" class="text-xs text-slate-400 mt-1">{{ selectedOption.description }}</p>
              <p v-if="createErrors.grading_option_id" class="text-xs text-red-500 mt-1">{{ createErrors.grading_option_id[0] }}</p>
            </div>

            <div class="flex gap-3 justify-end pt-2">
              <button type="button" @click="showModal = false"
                class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
              <button type="submit" :disabled="creating"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
                {{ creating ? 'Creating…' : 'Create Class Record' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
