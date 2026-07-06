<template>
  <Head title="Salary Schedule" />
  <AdminLayout title="Salary Schedule">
    <div class="space-y-5">

      <AppPageHeader title="DBM Salary Schedule" subtitle="Monthly and annual rates per Salary Grade and Step — used for PHTR computation">
        <template #actions>
          <AppButton variant="secondary" @click="openActivate()">
            <BoltIcon class="h-4 w-4" /> Activate Schedule
          </AppButton>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> Add Rate
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Schedule names info bar -->
      <div v-if="scheduleNames.length" class="flex flex-wrap gap-2">
        <AppBadge v-for="s in scheduleNames" :key="s.schedule_name" :color="s.is_current ? 'green' : 'slate'">
          {{ s.schedule_name }} (eff. {{ s.effective_date }})
        </AppBadge>
      </div>

      <!-- SST position quick reference -->
      <AppCard v-if="positions.length" title="SST Position → Salary Grade Mapping">
        <div class="flex flex-wrap gap-2">
          <AppBadge v-for="p in positions" :key="p.id" color="indigo">
            {{ p.code }} → SG {{ p.salary_grade }}
          </AppBadge>
        </div>
      </AppCard>

      <!-- Empty -->
      <EmptyState v-if="byGrade.length === 0" title="No salary schedule rates found" subtitle="Add rates or run the database seeder to populate SSL V 2023." :icon="TableCellsIcon" />

      <!-- Grouped by salary grade -->
      <div v-else class="space-y-4">
        <AppCard v-for="group in byGrade" :key="group.salary_grade" :padded="false">
          <template #header>
            <h3 class="text-sm font-semibold text-slate-700">Salary Grade {{ group.salary_grade }}</h3>
          </template>
          <template #default>
            <div class="px-5 pt-4" v-if="group.position_code">
              <AppBadge color="indigo">{{ group.position_code }}</AppBadge>
            </div>
            <AppTable :card="false">
              <template #head>
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Step</th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Monthly Rate</th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Annual Rate</th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">PHTR</th>
                  <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Current</th>
                  <th class="px-4 py-2"></th>
                </tr>
              </template>

              <tr v-for="r in group.steps" :key="r.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-2.5 font-medium text-slate-700">Step {{ r.step }}</td>
                <td class="px-4 py-2.5 text-right text-slate-700">{{ phpFmt(r.monthly_rate) }}</td>
                <td class="px-4 py-2.5 text-right font-semibold text-slate-800">{{ phpFmt(r.annual_rate) }}</td>
                <td class="px-4 py-2.5 text-right text-success-700 font-mono text-xs">
                  {{ phpFmt(phtr(r.annual_rate)) }}
                </td>
                <td class="px-4 py-2.5 text-center">
                  <span v-if="r.is_current" class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-success-100">
                    <CheckCircleIcon class="h-3 w-3 text-success-600" />
                  </span>
                  <span v-else class="text-xs text-slate-300">—</span>
                </td>
                <td class="px-4 py-2.5 text-right">
                  <AppIconButton label="Edit" @click="openForm(r)"><PencilIcon class="h-3.5 w-3.5" /></AppIconButton>
                </td>
              </tr>
            </AppTable>
          </template>
        </AppCard>
      </div>

    </div>

    <!-- Add / Edit rate modal -->
    <AppModal :show="modal" :title="`${form.id ? 'Edit' : 'Add'} Salary Rate`" size="sm" @close="modal = false">
      <div class="grid grid-cols-2 gap-3">
        <AppInput v-if="!form.id" v-model.number="form.salary_grade" label="Salary Grade" required type="number" min="1" max="33" />
        <div v-if="!form.id">
          <label class="block text-xs font-medium text-slate-600 mb-1">Step <span class="text-red-500">*</span></label>
          <select v-model.number="form.step" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="s in 8" :key="s" :value="s">Step {{ s }}</option>
          </select>
        </div>

        <div class="col-span-2">
          <AppInput v-model.number="form.monthly_rate" label="Monthly Rate (₱)" required type="number" step="1" min="1" />
          <p v-if="form.monthly_rate" class="text-xs text-slate-400 mt-1">
            Annual: {{ phpFmt(form.monthly_rate * 12) }} · PHTR: {{ phpFmt(phtr(form.monthly_rate * 12)) }}
          </p>
        </div>

        <div v-if="!form.id" class="col-span-2">
          <AppInput v-model="form.effective_date" label="Effective Date" required type="date" />
        </div>

        <div v-if="!form.id" class="col-span-2">
          <AppInput v-model="form.schedule_name" label="Schedule Name" type="text" placeholder="e.g. SSL V 2023" />
        </div>

        <div class="col-span-2 flex items-center gap-2">
          <input v-model="form.is_current" type="checkbox" id="is-current" class="rounded text-indigo-600" />
          <label for="is-current" class="text-sm text-slate-700">Mark as current schedule</label>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Save' }}</AppButton>
      </template>
    </AppModal>

    <!-- Activate schedule modal -->
    <AppModal :show="activateModal" title="Activate Salary Schedule" size="sm" @close="activateModal = false">
      <p class="text-sm text-slate-600 mb-4">All entries matching the selected schedule name will be marked as current. Existing current entries will be deactivated first.</p>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Schedule Name</label>
        <select v-model="activateName" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="s in scheduleNames" :key="s.schedule_name" :value="s.schedule_name">
            {{ s.schedule_name }}
          </option>
        </select>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="activateModal = false">Cancel</AppButton>
        <AppButton variant="success" @click="submitActivate">Activate</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppCard from '@/Components/AppCard.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
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
