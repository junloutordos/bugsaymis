<template>
  <Head title="DTR Records" />
  <AdminLayout title="DTR Records">
    <div class="space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Daily Time Records</h1>
          <p class="text-sm text-slate-500 mt-0.5">Monthly attendance summary per employee.</p>
        </div>
        <button
          @click="generateModal = true"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm"
        >
          <Cog6ToothIcon class="h-4 w-4" />
          Generate DTR
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-slate-500 mb-1">Employee</label>
          <select v-model="filters.user_id" @change="applyFilters" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Employees</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Month</label>
          <input v-model="filters.month" type="month" @change="applyFilters" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>
        <button @click="applyFilters" class="bg-slate-700 hover:bg-slate-800 text-white text-sm px-4 py-2 rounded-lg transition-colors">Filter</button>
      </div>

      <!-- Summary Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Present</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Absent</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Half Day</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Leave</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Holiday</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Hrs Worked</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Late (min)</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">UT (min)</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">OT (min)</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!summaries.length">
                <td colspan="11" class="px-4 py-12 text-center text-slate-400 text-sm">No DTR records found for this period. Generate DTR from biometric logs.</td>
              </tr>
              <tr v-for="s in summaries" :key="s.user_id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800">{{ s.user?.name }}</div>
                  <div class="text-xs text-slate-400">{{ s.user?.badge_id }}</div>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700">{{ s.present_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.absent_count > 0 ? 'bg-red-50 text-red-600' : 'bg-slate-50 text-slate-400'" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">{{ s.absent_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.half_day_count > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-50 text-slate-400'" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">{{ s.half_day_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.on_leave_count > 0 ? 'bg-blue-50 text-blue-600' : 'bg-slate-50 text-slate-400'" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">{{ s.on_leave_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.holiday_count > 0 ? 'bg-purple-50 text-purple-600' : 'bg-slate-50 text-slate-400'" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">{{ s.holiday_count }}</span>
                </td>
                <td class="px-4 py-3 text-right text-slate-700 font-medium">{{ s.total_hours }}</td>
                <td class="px-4 py-3 text-right" :class="s.total_late > 0 ? 'text-amber-600 font-medium' : 'text-slate-400'">{{ s.total_late > 0 ? s.total_late : '—' }}</td>
                <td class="px-4 py-3 text-right" :class="s.total_ut > 0 ? 'text-orange-600 font-medium' : 'text-slate-400'">{{ s.total_ut > 0 ? s.total_ut : '—' }}</td>
                <td class="px-4 py-3 text-right" :class="s.total_ot > 0 ? 'text-emerald-600 font-medium' : 'text-slate-400'">{{ s.total_ot > 0 ? s.total_ot : '—' }}</td>
                <td class="px-4 py-3">
                  <Link
                    :href="route('hr.dtr.show', s.user_id) + '?month=' + month"
                    class="inline-flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap"
                  >
                    <EyeIcon class="h-3.5 w-3.5" />
                    View Details
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100 text-xs text-slate-400">
          {{ summaries.length }} employee(s) — {{ monthLabel }}
        </div>
      </div>
    </div>

    <!-- Generate DTR Modal -->
    <Teleport to="body">
      <div v-if="generateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Generate DTR Records</h3>
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="font-normal text-slate-400">(leave blank for all)</span></label>
              <select v-model="genForm.user_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">All active employees</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date From</label>
                <input v-model="genForm.date_from" type="date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date To</label>
                <input v-model="genForm.date_to" type="date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
            </div>
          </div>
          <div class="flex gap-3 justify-end mt-5">
            <button @click="generateModal = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitGenerate" :disabled="genForm.processing || !genForm.date_from || !genForm.date_to" class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors">
              {{ genForm.processing ? 'Queueing…' : 'Generate' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  EyeIcon,
  Cog6ToothIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  summaries: Array,
  users:     Array,
  filters:   Object,
  month:     String,
})

const monthLabel = computed(() => {
  const [y, m] = props.month.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

// ── Filters ────────────────────────────────────────────────────────────────
const filters = reactive({ user_id: props.filters?.user_id ?? '', month: props.month })

function applyFilters () {
  router.get(route('hr.dtr.index'), filters, { preserveState: true, replace: true })
}

// ── Generate DTR ───────────────────────────────────────────────────────────
const generateModal = ref(false)
const genForm = useForm({ user_id: '', date_from: '', date_to: '' })

function submitGenerate () {
  genForm.post(route('hr.dtr.generate'), {
    onSuccess: () => { generateModal.value = false; genForm.reset() },
  })
}
</script>
