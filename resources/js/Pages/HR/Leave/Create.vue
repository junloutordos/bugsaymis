<template>
  <Head title="File Leave Application" />
  <AdminLayout title="File Leave Application">
    <div class="max-w-2xl mx-auto space-y-5">

      <!-- Header -->
      <div>
        <h1 class="text-xl font-semibold text-slate-800">File Leave Application</h1>
        <p class="text-sm text-slate-500 mt-0.5">Submit a new leave request for approval.</p>
      </div>

      <!-- Credits summary -->
      <div v-if="credits.length" class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-2">Leave Credit Balance ({{ currentYear }})</p>
        <div class="flex flex-wrap gap-3">
          <div v-for="c in credits" :key="c.id" class="text-xs">
            <span class="font-medium text-slate-700">{{ c.leave_type?.name }}:</span>
            <span class="ml-1 font-bold" :class="Number(c.balance ?? c.earned) > 0 ? 'text-emerald-600' : 'text-red-500'">
              {{ c.balance ?? (Number(c.earned) - Number(c.used ?? 0)) }} days
            </span>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-5">

        <!-- Leave Type -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Leave Type <span class="text-red-500">*</span></label>
          <select v-model="form.leave_type_id"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                  :class="{ 'border-red-400': form.errors.leave_type_id }">
            <option value="">Select leave type…</option>
            <option v-for="t in leaveTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <p v-if="form.errors.leave_type_id" class="text-red-500 text-xs mt-1">{{ form.errors.leave_type_id }}</p>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date From <span class="text-red-500">*</span></label>
            <input v-model="form.date_from" type="date"
                   class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                   :class="{ 'border-red-400': form.errors.date_from }" />
            <p v-if="form.errors.date_from" class="text-red-500 text-xs mt-1">{{ form.errors.date_from }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date To <span class="text-red-500">*</span></label>
            <input v-model="form.date_to" type="date" :min="form.date_from"
                   class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                   :class="{ 'border-red-400': form.errors.date_to }" />
            <p v-if="form.errors.date_to" class="text-red-500 text-xs mt-1">{{ form.errors.date_to }}</p>
          </div>
        </div>

        <!-- Computed working days preview -->
        <div v-if="computedDays !== null"
             class="bg-slate-50 rounded-lg px-4 py-2 text-sm text-slate-600">
          Estimated working days: <strong class="text-slate-800">{{ computedDays }}</strong>
        </div>

        <!-- Leave Details (conditional) -->
        <div v-if="showLeaveDetails">
          <label class="block text-xs font-medium text-slate-600 mb-1">Leave Details</label>
          <select v-model="form.leave_details"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">Select…</option>
            <option v-for="opt in leaveDetailOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <input v-if="form.leave_details === 'other' || form.leave_details === 'within_philippines' || form.leave_details === 'abroad'"
                 v-model="form.leave_details_specify"
                 type="text" placeholder="Please specify…"
                 class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>

        <!-- Reason -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Reason / Remarks</label>
          <textarea v-model="form.reason" rows="3" placeholder="Optional remarks…"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
        </div>

        <!-- Supporting Document -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Supporting Document <span class="font-normal text-slate-400">(PDF/JPG/PNG, max 5MB)</span></label>
          <input @change="e => form.supporting_document = e.target.files[0]"
                 type="file" accept=".pdf,.jpg,.jpeg,.png"
                 class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-slate-200 file:text-xs file:font-medium file:bg-slate-50 hover:file:bg-slate-100" />
          <p v-if="form.errors.supporting_document" class="text-red-500 text-xs mt-1">{{ form.errors.supporting_document }}</p>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 justify-end pt-2">
          <Link :href="route('hr.leave.index')"
                class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
            Cancel
          </Link>
          <button @click="submit" :disabled="form.processing"
                  class="px-6 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors font-medium">
            {{ form.processing ? 'Filing…' : 'File Application' }}
          </button>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  leaveTypes: Array,
  credits:    Array,
})

const currentYear = new Date().getFullYear()

const form = useForm({
  leave_type_id:         '',
  date_from:             '',
  date_to:               '',
  leave_details:         '',
  leave_details_specify: '',
  reason:                '',
  supporting_document:   null,
})

// Determine leave detail options based on selected type
const selectedType = computed(() => props.leaveTypes.find(t => t.id == form.leave_type_id))

const showLeaveDetails = computed(() => {
  const code = selectedType.value?.code
  return ['VL', 'SL', 'SPL'].includes(code)
})

const leaveDetailOptions = computed(() => {
  const code = selectedType.value?.code
  if (code === 'VL') return [
    { value: 'within_philippines', label: 'Within the Philippines' },
    { value: 'abroad',             label: 'Abroad' },
  ]
  if (code === 'SL') return [
    { value: 'in_hospital',  label: 'In Hospital (Confinement)' },
    { value: 'out_patient',  label: 'Out Patient' },
  ]
  if (code === 'SPL') return [
    { value: 'master_degree',     label: 'Master\'s Degree' },
    { value: 'bar_board_review',  label: 'Bar/Board Review' },
    { value: 'other',             label: 'Other' },
  ]
  return [{ value: 'other', label: 'Other' }]
})

const computedDays = computed(() => {
  if (!form.date_from || !form.date_to) return null
  const start = new Date(form.date_from)
  const end   = new Date(form.date_to)
  if (end < start) return null
  let days = 0
  const d = new Date(start)
  while (d <= end) {
    const dow = d.getDay()
    if (dow !== 0 && dow !== 6) days++
    d.setDate(d.getDate() + 1)
  }
  return days
})

function submit() {
  form.post(route('hr.leave.store'), {
    forceFormData: true,
  })
}
</script>
