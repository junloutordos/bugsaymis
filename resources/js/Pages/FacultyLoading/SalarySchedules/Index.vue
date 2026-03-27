<template>
  <Head title="Salary Schedule" />
  <AdminLayout title="Salary Schedule">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">DBM Salary Schedule</h1>
          <p class="text-sm text-slate-500 mt-0.5">Monthly and annual rates per Salary Grade and Step — used for PHTR computation</p>
        </div>
        <div class="flex gap-2">
          <button @click="openActivate()"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg font-medium">
            <BoltIcon class="h-4 w-4" /> Activate Schedule
          </button>
          <button @click="openForm()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
            <PlusIcon class="h-4 w-4" /> Add Rate
          </button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Schedule names info bar -->
      <div v-if="scheduleNames.length" class="flex flex-wrap gap-2">
        <span v-for="s in scheduleNames" :key="s.schedule_name"
          :class="s.is_current ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-slate-50 border-slate-200 text-slate-500'"
          class="inline-flex items-center gap-1.5 border rounded-full px-3 py-0.5 text-xs font-medium">
          <span v-if="s.is_current" class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
          {{ s.schedule_name }} (eff. {{ s.effective_date }})
        </span>
      </div>

      <!-- SST position quick reference -->
      <div v-if="positions.length" class="bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
        <p class="text-xs font-semibold text-indigo-700 mb-2">SST Position → Salary Grade Mapping</p>
        <div class="flex flex-wrap gap-3">
          <span v-for="p in positions" :key="p.id"
            class="text-xs text-indigo-800 bg-white border border-indigo-100 rounded-lg px-2.5 py-1">
            {{ p.code }} → SG {{ p.salary_grade }}
          </span>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="byGrade.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <TableCellsIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No salary schedule rates found</p>
        <p class="text-xs text-slate-400 mt-1">Add rates or run the database seeder to populate SSL V 2023.</p>
      </div>

      <!-- Grouped by salary grade -->
      <div v-else class="space-y-4">
        <div v-for="group in byGrade" :key="group.salary_grade"
          class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
            <h3 class="text-sm font-semibold text-slate-700">Salary Grade {{ group.salary_grade }}</h3>
            <span v-if="group.position_code"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-700">
              {{ group.position_code }}
            </span>
          </div>
          <table class="min-w-full divide-y divide-slate-50 text-sm">
            <thead>
              <tr class="text-xs text-slate-400 uppercase tracking-wide">
                <th class="px-4 py-2 text-left">Step</th>
                <th class="px-4 py-2 text-right">Monthly Rate</th>
                <th class="px-4 py-2 text-right">Annual Rate</th>
                <th class="px-4 py-2 text-right">PHTR</th>
                <th class="px-4 py-2 text-center">Current</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="r in group.steps" :key="r.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-2.5 font-medium text-slate-700">Step {{ r.step }}</td>
                <td class="px-4 py-2.5 text-right text-slate-700">{{ phpFmt(r.monthly_rate) }}</td>
                <td class="px-4 py-2.5 text-right font-semibold text-slate-800">{{ phpFmt(r.annual_rate) }}</td>
                <td class="px-4 py-2.5 text-right text-emerald-700 font-mono text-xs">
                  {{ phpFmt(phtr(r.annual_rate)) }}
                </td>
                <td class="px-4 py-2.5 text-center">
                  <span v-if="r.is_current" class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-emerald-100">
                    <CheckCircleIcon class="h-3 w-3 text-emerald-600" />
                  </span>
                  <span v-else class="text-xs text-slate-300">—</span>
                </td>
                <td class="px-4 py-2.5 text-right">
                  <button @click="openForm(r)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                    <PencilIcon class="h-3.5 w-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Add / Edit rate modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 my-8">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'Add' }} Salary Rate</h2>

        <div class="grid grid-cols-2 gap-3">
          <div v-if="!form.id">
            <label class="block text-xs font-medium text-slate-600 mb-1">Salary Grade *</label>
            <input v-model.number="form.salary_grade" type="number" min="1" max="33"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div v-if="!form.id">
            <label class="block text-xs font-medium text-slate-600 mb-1">Step *</label>
            <select v-model.number="form.step" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="s in 8" :key="s" :value="s">Step {{ s }}</option>
            </select>
          </div>

          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Monthly Rate (₱) *</label>
            <input v-model.number="form.monthly_rate" type="number" step="1" min="1"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <p v-if="form.monthly_rate" class="text-xs text-slate-400 mt-1">
              Annual: {{ phpFmt(form.monthly_rate * 12) }} · PHTR: {{ phpFmt(phtr(form.monthly_rate * 12)) }}
            </p>
          </div>

          <div v-if="!form.id" class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date *</label>
            <input v-model="form.effective_date" type="date"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div v-if="!form.id" class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Schedule Name</label>
            <input v-model="form.schedule_name" type="text" placeholder="e.g. SSL V 2023"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div class="col-span-2 flex items-center gap-2">
            <input v-model="form.is_current" type="checkbox" id="is-current" class="rounded text-indigo-600" />
            <label for="is-current" class="text-sm text-slate-700">Mark as current schedule</label>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button @click="modal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="save" :disabled="form.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
            {{ form.id ? 'Update' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Activate schedule modal -->
    <div v-if="activateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Activate Salary Schedule</h2>
        <p class="text-sm text-slate-600">All entries matching the selected schedule name will be marked as current. Existing current entries will be deactivated first.</p>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Schedule Name</label>
          <select v-model="activateName" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="s in scheduleNames" :key="s.schedule_name" :value="s.schedule_name">
              {{ s.schedule_name }}
            </option>
          </select>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="activateModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="submitActivate"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
            Activate
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { BoltIcon, CheckCircleIcon, PencilIcon, PlusIcon, TableCellsIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  byGrade:       { type: Array,  default: () => [] },
  positions:     { type: Array,  default: () => [] },
  scheduleNames: { type: Array,  default: () => [] },
  filters:       { type: Object, default: () => ({}) },
})

function phpFmt(val) {
  if (val == null) return '—'
  return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function phtr(annualRate) {
  return (annualRate / 1600) * 1.25
}

// ── Add/Edit form ─────────────────────────────────────────────────────────────
const modal = ref(false)
const form  = useForm({
  id: null,
  salary_grade:   null,
  step:           1,
  monthly_rate:   0,
  effective_date: '',
  is_current:     true,
  schedule_name:  '',
})

function openForm(r = null) {
  if (r) {
    Object.assign(form, {
      id: r.id, salary_grade: r.salary_grade, step: r.step,
      monthly_rate: r.monthly_rate, effective_date: r.effective_date,
      is_current: r.is_current, schedule_name: r.schedule_name ?? '',
    })
  } else {
    form.reset()
    form.id = null
    form.step = 1
    form.is_current = true
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.salary-schedules.update', form.id), {
      onSuccess: () => { modal.value = false },
    })
  } else {
    form.post(route('faculty-loading.salary-schedules.store'), {
      onSuccess: () => { modal.value = false },
    })
  }
}

// ── Activate schedule ─────────────────────────────────────────────────────────
const activateModal = ref(false)
const activateName  = ref('')

function openActivate() {
  activateName.value = props.scheduleNames[0]?.schedule_name ?? ''
  activateModal.value = true
}

function submitActivate() {
  useForm({ schedule_name: activateName.value })
    .post(route('faculty-loading.salary-schedules.activate'), {
      onSuccess: () => { activateModal.value = false },
    })
}
</script>
