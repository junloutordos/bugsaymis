<template>
  <Head title="DTR Records" />
  <AdminLayout title="DTR Records">
    <div class="space-y-5">

      <!-- ── Page Header ────────────────────────────────────────────── -->
      <AppPageHeader title="Daily Time Records" subtitle="Monthly attendance summary per employee.">
        <template #actions>
          <AppButton
            :variant="activeView === 'penned' ? 'primary' : 'secondary'"
            @click="activeView = activeView === 'penned' ? 'summary' : 'penned'"
          >
            <InboxArrowDownIcon class="h-4 w-4" />
            Penned Entries
            <AppBadge v-if="pennedSubmissions.length" color="amber" class="ml-1">{{ pennedSubmissions.length }}</AppBadge>
          </AppButton>
          <AppButton as="a" :href="route('hr.dtr.hazard-report')" variant="secondary">
            <FireIcon class="h-4 w-4" />
            Generate Hazard Report
          </AppButton>
          <AppButton variant="secondary" @click="batchPrintModal = true">
            <PrinterIcon class="h-4 w-4" />
            Batch Print
          </AppButton>
          <AppButton @click="openGenerateModal">
            <Cog6ToothIcon class="h-4 w-4" />
            Generate DTR
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- ── Flash ──────────────────────────────────────────────────── -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <XCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.error }}
      </div>

      <!-- ── Penned Submissions tab panel ───────────────────────────── -->
      <div v-if="activeView === 'penned'" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <div class="flex items-center gap-2 mb-3">
          <InboxArrowDownIcon class="h-4 w-4 text-amber-600" />
          <h3 class="text-sm font-semibold text-slate-800">Penned Entries Awaiting Review</h3>
          <span class="text-xs text-slate-400 ml-1">— all months, not just the one selected below</span>
          <AppBadge v-if="pennedSubmissions.length" color="amber" class="ml-auto">{{ pennedSubmissions.length }}</AppBadge>
        </div>
        <div v-if="pennedSubmissions.length" class="divide-y divide-slate-100">
          <div v-for="sub in pennedSubmissions" :key="`${sub.id}-${sub.submission_month}`" class="py-2.5 flex items-center justify-between gap-3">
            <div>
              <span class="text-sm font-medium text-slate-800">{{ sub.name }}</span>
              <span class="ml-2 text-xs text-amber-600">{{ sub.entry_count }} record(s) — {{ sub.submission_month }}</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs text-slate-500">Submitted {{ new Date(sub.submitted_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
              <Link :href="route('hr.dtr.show', sub.id) + '?month=' + sub.submission_month"
                class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                <EyeIcon class="h-3.5 w-3.5" />Review
              </Link>
            </div>
          </div>
        </div>
        <EmptyState v-else :icon="InboxArrowDownIcon" title="Nothing awaiting review." subtitle="Penned submissions will appear here once employees submit them." />
      </div>

      <!-- ── Controls: Month + Search ──────────────────────────────── -->
      <template v-if="activeView === 'summary'">
      <AppFilterBar :result-label="`${filteredSummaries.length} result(s) — ${monthLabel}`">
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Month</label>
          <input
            v-model="filterMonth"
            type="month"
            @change="applyMonth"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
          />
        </div>
        <div class="flex-1 sm:max-w-xs">
          <label class="block text-xs font-medium text-slate-500 mb-1">Search employee</label>
          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              v-model="search"
              type="text"
              placeholder="Name or badge ID…"
              class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
            />
          </div>
        </div>
      </AppFilterBar>

      <!-- ── Category Tabs + Table ──────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- Tab bar -->
        <div class="flex overflow-x-auto border-b border-slate-200 bg-slate-50 scrollbar-none">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            @click="activeTab = tab.value"
            :class="[
              'flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors shrink-0',
              activeTab === tab.value
                ? 'border-indigo-600 text-indigo-700 bg-white'
                : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-100'
            ]"
          >
            {{ tab.label }}
            <span :class="[
              'inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-semibold',
              activeTab === tab.value ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-500'
            ]">
              {{ tabCount(tab.value) }}
            </span>
          </button>
        </div>

        <!-- Table -->
        <AppTable :card="false" :is-empty="!filteredSummaries.length" :skeleton-cols="12">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-emerald-600 uppercase tracking-wide whitespace-nowrap">Present</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-red-500 uppercase tracking-wide whitespace-nowrap">Absent</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-amber-600 uppercase tracking-wide whitespace-nowrap">Half Day</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-blue-600 uppercase tracking-wide whitespace-nowrap">Leave</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-purple-600 uppercase tracking-wide whitespace-nowrap">Holiday</th>
              <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Hrs Worked</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-amber-600 uppercase tracking-wide whitespace-nowrap">Late</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-orange-600 uppercase tracking-wide whitespace-nowrap">Undertime</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-emerald-600 uppercase tracking-wide whitespace-nowrap">Overtime</th>
              <th class="px-4 py-3"></th>
            </tr>
          </template>

              <tr
                v-for="(s, idx) in filteredSummaries"
                :key="s.user_id"
                class="hover:bg-indigo-50/30 transition-colors group"
              >
                <td class="px-4 py-3 text-xs text-slate-400 tabular-nums">{{ idx + 1 }}</td>

                <!-- Employee -->
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800 leading-tight">{{ s.user?.name }}</div>
                  <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-xs text-slate-400">{{ s.user?.badge_id }}</span>
                    <AppBadge v-if="s.user?.emp_category" :color="categoryBadgeClass(s.user.emp_category)">
                      {{ s.user.emp_category }}
                    </AppBadge>
                  </div>
                </td>

                <!-- Attendance counts -->
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center justify-center w-8 h-6 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700">{{ s.present_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.absent_count > 0 ? 'bg-red-50 text-red-600' : 'bg-slate-50 text-slate-400'" class="inline-flex items-center justify-center w-8 h-6 rounded-md text-xs font-semibold">{{ s.absent_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.half_day_count > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-50 text-slate-400'" class="inline-flex items-center justify-center w-8 h-6 rounded-md text-xs font-semibold">{{ s.half_day_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.on_leave_count > 0 ? 'bg-blue-50 text-blue-600' : 'bg-slate-50 text-slate-400'" class="inline-flex items-center justify-center w-8 h-6 rounded-md text-xs font-semibold">{{ s.on_leave_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.holiday_count > 0 ? 'bg-purple-50 text-purple-600' : 'bg-slate-50 text-slate-400'" class="inline-flex items-center justify-center w-8 h-6 rounded-md text-xs font-semibold">{{ s.holiday_count }}</span>
                </td>

                <!-- Totals -->
                <td class="px-4 py-3 text-right font-medium text-slate-700 tabular-nums">{{ s.total_hours ?? '—' }}</td>
                <td class="px-4 py-3 text-right tabular-nums" :class="s.total_late > 0 ? 'text-amber-600 font-medium' : 'text-slate-300'">
                  {{ s.total_late > 0 ? fmtMin(s.total_late) : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums" :class="s.total_ut > 0 ? 'text-orange-600 font-medium' : 'text-slate-300'">
                  {{ s.total_ut > 0 ? fmtMin(s.total_ut) : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums" :class="s.total_ot > 0 ? 'text-emerald-600 font-medium' : 'text-slate-300'">
                  {{ s.total_ot > 0 ? fmtMin(s.total_ot) : '—' }}
                </td>

                <!-- Actions -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2 justify-end">
                    <Link
                      :href="route('hr.dtr.show', s.user_id) + '?month=' + filterMonth"
                      class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity"
                    >
                      <EyeIcon class="h-3.5 w-3.5" />View
                    </Link>
                    <a
                      :href="route('hr.dtr.print', s.user_id) + '?month=' + filterMonth"
                      target="_blank"
                      class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 font-medium whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity"
                    >
                      <PrinterIcon class="h-3.5 w-3.5" />Print
                    </a>
                  </div>
                </td>
              </tr>

              <tr v-if="filteredSummaries.length" class="bg-slate-50 border-t-2 border-slate-200">
                <td colspan="2" class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase">Totals</td>
                <td class="px-4 py-2 text-center text-xs font-bold text-emerald-700">{{ sumCol('present_count') }}</td>
                <td class="px-4 py-2 text-center text-xs font-bold text-red-600">{{ sumCol('absent_count') }}</td>
                <td class="px-4 py-2 text-center text-xs font-bold text-amber-700">{{ sumCol('half_day_count') }}</td>
                <td class="px-4 py-2 text-center text-xs font-bold text-blue-600">{{ sumCol('on_leave_count') }}</td>
                <td class="px-4 py-2 text-center text-xs font-bold text-purple-600">{{ sumCol('holiday_count') }}</td>
                <td class="px-4 py-2 text-right text-xs font-bold text-slate-700">{{ sumCol('total_hours') }}</td>
                <td class="px-4 py-2 text-right text-xs font-bold text-amber-600">{{ sumCol('total_late') > 0 ? fmtMin(sumCol('total_late')) : '—' }}</td>
                <td class="px-4 py-2 text-right text-xs font-bold text-orange-600">{{ sumCol('total_ut') > 0 ? fmtMin(sumCol('total_ut')) : '—' }}</td>
                <td class="px-4 py-2 text-right text-xs font-bold text-emerald-600">{{ sumCol('total_ot') > 0 ? fmtMin(sumCol('total_ot')) : '—' }}</td>
                <td></td>
              </tr>

          <template #mobileCard>
            <div v-for="s in filteredSummaries" :key="s.user_id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-slate-800">{{ s.user?.name }}</p>
                  <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-xs text-slate-400">{{ s.user?.badge_id }}</span>
                    <AppBadge v-if="s.user?.emp_category" :color="categoryBadgeClass(s.user.emp_category)">{{ s.user.emp_category }}</AppBadge>
                  </div>
                </div>
                <span class="text-xs font-medium text-slate-700 tabular-nums">{{ s.total_hours ?? '—' }} hrs</span>
              </div>
              <div class="flex flex-wrap gap-2 text-xs">
                <span class="text-emerald-700">Present {{ s.present_count }}</span>
                <span class="text-red-500">Absent {{ s.absent_count }}</span>
                <span class="text-amber-600">Half {{ s.half_day_count }}</span>
                <span class="text-blue-600">Leave {{ s.on_leave_count }}</span>
                <span class="text-purple-600">Holiday {{ s.holiday_count }}</span>
              </div>
              <div class="flex items-center gap-3 pt-1">
                <Link :href="route('hr.dtr.show', s.user_id) + '?month=' + filterMonth" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
                <a :href="route('hr.dtr.print', s.user_id) + '?month=' + filterMonth" target="_blank" class="text-xs text-slate-500 hover:text-slate-700 font-medium">Print</a>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState
              :icon="ClipboardDocumentListIcon"
              title="No DTR records found for this period."
              subtitle="Use Generate DTR to process biometric logs."
            />
          </template>
        </AppTable>
      </div>
      </template>
    </div>

    <!-- ── Batch Print Modal ──────────────────────────────────────── -->
    <AppModal :show="batchPrintModal" title="Batch Print DTR" subtitle="CS Form 48 — two copies per employee, A4 landscape." @close="batchPrintModal = false">
          <div class="space-y-4">
            <!-- Category -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Category <span class="font-normal text-slate-400">(leave blank for all)</span></label>
              <select v-model="batchForm.category" @change="onBatchCategoryChange"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">All active employees</option>
                <option value="Plantilla Teaching">Plantilla Teaching</option>
                <option value="Plantilla Non-Teaching">Plantilla Non-Teaching</option>
                <option value="COS Teaching">COS Teaching</option>
                <option value="COS Non Teaching">COS Non Teaching</option>
              </select>
            </div>

            <!-- Plantilla / All: month picker -->
            <div v-if="!isBatchNonPlantilla">
              <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
              <input v-model="batchForm.month" type="month"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>

            <!-- Non-Plantilla: date range -->
            <template v-else>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Date From</label>
                  <input v-model="batchForm.date_from" type="date"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Date To</label>
                  <input v-model="batchForm.date_to" type="date"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
              </div>
              <p class="text-xs text-slate-400">Date range can span across months (e.g. Dec 25 – Jan 10).</p>
            </template>

            <!-- Optional: single employee override -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="font-normal text-slate-400">(optional — overrides category)</span></label>
              <select v-model="batchForm.user_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">— All in selected category —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
      <template #footer>
        <AppButton variant="secondary" @click="batchPrintModal = false">Cancel</AppButton>
        <AppButton @click="openBatchPrint" :disabled="isBatchNonPlantilla ? (!batchForm.date_from || !batchForm.date_to) : !batchForm.month">
          <PrinterIcon class="h-4 w-4" />Open Print
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Generate DTR Modal ─────────────────────────────────────── -->
    <AppModal :show="generateModal" title="Generate DTR Records" subtitle="Processes biometric logs into daily DTR entries." @close="generateModal = false">
          <div class="space-y-4">
            <!-- Category selector -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Employee Category</label>
              <div class="grid grid-cols-4 gap-1 bg-slate-100 rounded-lg p-1">
                <button
                  v-for="c in genCategories"
                  :key="c.value"
                  type="button"
                  @click="genCategory = c.value; syncGenDates()"
                  :class="[
                    'px-2 py-1.5 rounded-md text-xs font-medium transition-colors text-center',
                    genCategory === c.value
                      ? 'bg-white text-indigo-700 shadow-sm'
                      : 'text-slate-500 hover:text-slate-700'
                  ]"
                >{{ c.label }}</button>
              </div>
            </div>

            <!-- Single: employee picker -->
            <div v-if="genCategory === 'single'">
              <label class="block text-xs font-medium text-slate-600 mb-1">Employee</label>
              <select v-model="genForm.user_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">— Select employee —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>

            <!-- All / Plantilla: full-month -->
            <template v-if="genCategory === 'all' || genCategory === 'plantilla'">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
                <input v-model="genMonth" type="month" @change="syncGenDates"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div class="flex items-center gap-2 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 text-xs text-indigo-700">
                <CalendarDaysIcon class="h-3.5 w-3.5 shrink-0" />
                Period: <span class="font-semibold ml-1">{{ genForm.date_from }}</span>&nbsp;—&nbsp;<span class="font-semibold">{{ genForm.date_to }}</span>
              </div>
            </template>

            <!-- Non-plantilla / Single: manual date range -->
            <template v-else-if="genCategory === 'non_plantilla' || genCategory === 'single'">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Date From</label>
                  <input v-model="genForm.date_from" type="date"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Date To</label>
                  <input v-model="genForm.date_to" type="date"
                         class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                  <button type="button" @click="genForm.date_to = tomorrowStr"
                    class="mt-1.5 text-[11px] text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded px-2 py-0.5 font-medium transition-colors whitespace-nowrap">
                    → Use tomorrow (cut-off)
                  </button>
                </div>
              </div>
              <p class="text-xs text-slate-400">Date range can span across months (e.g. Dec 25 – Jan 10).</p>
              <p class="text-xs text-amber-600">For COS payroll cut-off, set Date To to tomorrow to generate an advance entry.</p>
            </template>
          </div>
      <template #footer>
        <AppButton variant="secondary" @click="generateModal = false">Cancel</AppButton>
        <AppButton
          @click="submitGenerate"
          :loading="genForm.processing"
          :disabled="genForm.processing || !genForm.date_from || !genForm.date_to || (genCategory === 'single' && !genForm.user_id)"
        >
          <Cog6ToothIcon v-if="!genForm.processing" class="h-4 w-4" />
          {{ genForm.processing ? 'Generating…' : 'Generate' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  EyeIcon,
  Cog6ToothIcon,
  CheckCircleIcon,
  XCircleIcon,
  PrinterIcon,
  MagnifyingGlassIcon,
  CalendarDaysIcon,
  ClipboardDocumentListIcon,
  InboxArrowDownIcon,
  FireIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  summaries:          Array,
  pennedSubmissions:  { type: Array, default: () => [] },
  users:              Array,
  filters:            Object,
  month:              String,
})

// ── Month label ─────────────────────────────────────────────────────────────
const monthLabel = computed(() => {
  const [y, m] = props.month.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

// ── Month filter (server-side) ──────────────────────────────────────────────
const filterMonth = ref(props.month)

function applyMonth () {
  router.get(route('hr.dtr.index'), { month: filterMonth.value }, { preserveState: true, replace: true })
}

// ── Client-side search ──────────────────────────────────────────────────────
const search = ref('')

// ── Category tabs ───────────────────────────────────────────────────────────
const tabs = [
  { value: 'all',                    label: 'All'                  },
  { value: 'Plantilla Teaching',     label: 'Plantilla Teaching'   },
  { value: 'Plantilla Non-Teaching', label: 'Plantilla Non-Teaching' },
  { value: 'COS Teaching',          label: 'COS Teaching'         },
  { value: 'COS Non Teaching',      label: 'COS Non Teaching'     },
]

const activeTab = ref('all')
const activeView = ref('summary') // 'summary' | 'penned'

function tabCount (tabValue) {
  if (tabValue === 'all') return props.summaries.length
  return props.summaries.filter(s => s.user?.emp_category === tabValue).length
}

// ── Filtered summaries (tab + search) ──────────────────────────────────────
const filteredSummaries = computed(() => {
  let list = props.summaries

  if (activeTab.value !== 'all') {
    list = list.filter(s => s.user?.emp_category === activeTab.value)
  }

  if (search.value.trim()) {
    const q = search.value.trim().toLowerCase()
    list = list.filter(s =>
      s.user?.name?.toLowerCase().includes(q) ||
      s.user?.badge_id?.toLowerCase().includes(q)
    )
  }

  return list
})

// ── Column totals footer ────────────────────────────────────────────────────
function sumCol (col) {
  return filteredSummaries.value.reduce((acc, s) => acc + (Number(s[col]) || 0), 0)
}

// ── Format minutes as Xh Ym ────────────────────────────────────────────────
function fmtMin (mins) {
  const m = Math.round(mins)
  const h = Math.floor(m / 60)
  const r = m % 60
  if (h > 0 && r > 0) return `${h}h ${r}m`
  if (h > 0)           return `${h}h`
  return `${r}m`
}

// ── Category badge colour (AppBadge color key) ───────────────────────────────
function categoryBadgeClass (cat) {
  if (!cat) return 'slate'
  const map = {
    'Plantilla Teaching':     'indigo',
    'Plantilla Non-Teaching': 'purple',
    'COS Teaching':           'blue',
    'COS Non Teaching':       'orange',
  }
  return map[cat] ?? 'slate'
}

// ── Batch Print ─────────────────────────────────────────────────────────────
const batchPrintModal = ref(false)
const batchForm = reactive({ month: props.month, date_from: '', date_to: '', user_id: '', category: '' })

const NON_PLANTILLA_CATS = ['COS Teaching', 'COS Non Teaching']
const isBatchNonPlantilla = computed(() => NON_PLANTILLA_CATS.includes(batchForm.category))

function onBatchCategoryChange () {
  // Reset date fields when switching modes
  batchForm.date_from = ''
  batchForm.date_to   = ''
  batchForm.month     = props.month
}

function openBatchPrint () {
  const params = new URLSearchParams()
  if (isBatchNonPlantilla.value) {
    params.set('date_from', batchForm.date_from)
    params.set('date_to',   batchForm.date_to)
  } else {
    params.set('month', batchForm.month)
  }
  if (batchForm.user_id)  params.set('user_id',  batchForm.user_id)
  if (batchForm.category) params.set('category', batchForm.category)
  window.open(route('hr.dtr.print.batch') + '?' + params.toString(), '_blank')
  batchPrintModal.value = false
}

// ── Generate DTR ─────────────────────────────────────────────────────────────
const generateModal = ref(false)
const genCategory   = ref('all')
const genMonth      = ref(props.month)

const genCategories = [
  { value: 'all',           label: 'All'           },
  { value: 'plantilla',     label: 'Plantilla'     },
  { value: 'non_plantilla', label: 'Non-Plantilla' },
  { value: 'single',        label: 'Single'        },
]

const genForm = useForm({ user_id: '', category: 'all', date_from: '', date_to: '' })

function syncGenDates () {
  genForm.category = genCategory.value

  if (genCategory.value === 'non_plantilla' || genCategory.value === 'single') {
    genForm.date_from = ''
    genForm.date_to   = ''
    return
  }

  const [y, m] = genMonth.value.split('-').map(Number)
  const lastDay = new Date(y, m, 0).getDate()
  const pad = d => String(d).padStart(2, '0')
  genForm.date_from = `${genMonth.value}-01`
  genForm.date_to   = `${genMonth.value}-${pad(lastDay)}`
}

function openGenerateModal () {
  genCategory.value = 'all'
  genMonth.value    = props.month
  syncGenDates()
  generateModal.value = true
}

syncGenDates()

const tomorrowStr = computed(() => {
  const t = new Date()
  t.setDate(t.getDate() + 1)
  return `${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`
})

function submitGenerate () {
  genForm.category = genCategory.value
  genForm.post(route('hr.dtr.generate'), {
    onSuccess: () => { generateModal.value = false; genForm.reset(); syncGenDates() },
  })
}
</script>
