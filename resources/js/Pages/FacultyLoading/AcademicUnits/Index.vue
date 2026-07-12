<template>
  <Head title="Academic Units" />
  <AdminLayout title="Academic Units">
    <div class="space-y-5">

      <AppPageHeader title="Academic Units" subtitle="Manage JHS, SHS, SST and administrative units">
        <template #actions>
          <AppButton variant="success" :disabled="syncing" @click="doSync">
            <ArrowPathIcon class="h-4 w-4" /> Sync to Offices
          </AppButton>
          <AppButton variant="secondary" @click="copyModal = true">
            <DocumentDuplicateIcon class="h-4 w-4" /> Copy from Year
          </AppButton>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> New Unit
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.errors.error }}
      </div>

      <!-- School Year picker -->
      <AppFilterBar>
        <div class="w-56">
          <AppSelect v-model="selectedSy" label="School Year" :show-blank="false" @change="switchYear">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!units.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Code</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Head</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="u in units" :key="u.id" class="hover:bg-slate-50/50">
          <td class="px-4 py-3 font-mono text-xs text-indigo-700 font-semibold">{{ u.code }}</td>
          <td class="px-4 py-3 font-medium text-slate-800">{{ u.name }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="typeColor(u.unit_type)">{{ typeLabel(u.unit_type) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-600">{{ u.head_name ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="u.is_active ? 'green' : 'slate'">{{ u.is_active ? 'Active' : 'Inactive' }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-1">
              <AppIconButton label="Edit" @click="openForm(u)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteUnit(u)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="u in units" :key="u.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs text-indigo-700 font-semibold">{{ u.code }}</p>
                <p class="font-medium text-slate-800">{{ u.name }}</p>
                <p class="text-xs text-slate-400">{{ u.head_name ?? '—' }}</p>
              </div>
              <AppBadge :color="u.is_active ? 'green' : 'slate'">{{ u.is_active ? 'Active' : 'Inactive' }}</AppBadge>
            </div>
            <AppBadge :color="typeColor(u.unit_type)">{{ typeLabel(u.unit_type) }}</AppBadge>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="Edit" @click="openForm(u)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteUnit(u)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No academic units for this school year" subtitle="Use &quot;Copy from Year&quot; to get started." />
        </template>
      </AppTable>

    </div>

    <!-- Edit/Create Modal -->
    <AppModal :show="modal" :title="`${form.id ? 'Edit' : 'New'} Academic Unit`" size="sm" @close="modal = false">
      <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="form.code" label="Code" required placeholder="e.g. JHS" :error="form.errors.code" />
          <AppSelect v-model="form.unit_type" label="Type" required :show-blank="false">
            <option value="department">Department</option>
            <option value="junior_high">Junior High School</option>
            <option value="senior_high">Senior High School</option>
            <option value="sst">SST Program</option>
            <option value="admin">Administrative</option>
          </AppSelect>
        </div>
        <AppInput v-model="form.name" label="Name" required placeholder="e.g. Junior High School" :error="form.errors.name" />
        <AppInput v-model.number="form.sort_order" label="Sort Order" type="number" min="0" />
        <AppSelect v-model="form.head_user_id" label="Unit Head" placeholder="— None —">
          <option v-for="f in facultyOptions" :key="f.id" :value="f.id">{{ f.name }}</option>
        </AppSelect>
        <div class="flex items-center gap-2">
          <input v-model="form.is_active" type="checkbox" id="unit-active" class="rounded text-indigo-600" />
          <label for="unit-active" class="text-sm text-slate-600">Active</label>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>

    <!-- Copy from Year Modal -->
    <AppModal :show="copyModal" title="Copy Academic Units from Another Year" size="sm" @close="copyModal = false">
      <p class="text-sm text-slate-500 mb-4">Copies all units from the source year into the target year. AUH designations are preserved. Units with duplicate codes are skipped.</p>
      <div class="space-y-3">
        <AppSelect v-model="copyForm.source_school_year_id" label="Source Year (copy from)" :show-blank="false">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </AppSelect>
        <AppSelect v-model="copyForm.target_school_year_id" label="Target Year (copy into)" :show-blank="false" :error="copyForm.errors.target_school_year_id">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </AppSelect>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="copyModal = false">Cancel</AppButton>
        <AppButton :loading="copyForm.processing" @click="doCopy">Copy Units</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import {
  ArrowPathIcon, CheckCircleIcon, DocumentDuplicateIcon, ExclamationCircleIcon,
  PencilIcon, PlusIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  units:               { type: Array,  default: () => [] },
  facultyOptions:      { type: Array,  default: () => [] },
  schoolYears:         { type: Array,  default: () => [] },
  currentSchoolYearId: { type: Number, default: null },
})

const selectedSy = ref(props.currentSchoolYearId)

function switchYear() {
  router.get(route('faculty-loading.academic-units.index'), { school_year_id: selectedSy.value }, { preserveState: false })
}

const syncing = ref(false)
async function doSync() {
  const confirmed = await confirmAction({
    title: 'Sync unit heads to offices?',
    text: 'Sync unit heads from academic units to matching offices? This will overwrite the current unit_head values in those offices.',
    confirmText: 'Sync',
    icon: 'warning',
  })
  if (!confirmed) return
  syncing.value = true
  router.post(route('faculty-loading.academic-units.sync-to-offices'), { school_year_id: selectedSy.value }, {
    onFinish: () => { syncing.value = false },
  })
}

const modal = ref(false)
const form  = useForm({
  id: null, school_year_id: props.currentSchoolYearId,
  code: '', name: '', unit_type: 'department', head_user_id: null, sort_order: 0, is_active: true,
})

function openForm(u = null) {
  if (u) {
    Object.assign(form, { id: u.id, school_year_id: props.currentSchoolYearId,
      code: u.code, name: u.name, unit_type: u.unit_type,
      head_user_id: u.head_user_id, sort_order: u.sort_order, is_active: u.is_active })
  } else {
    form.reset()
    form.id = null
    form.school_year_id = props.currentSchoolYearId
    form.unit_type = 'department'; form.is_active = true
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.academic-units.update', form.id), { onSuccess: () => { modal.value = false } })
  } else {
    form.post(route('faculty-loading.academic-units.store'), { onSuccess: () => { modal.value = false } })
  }
}

async function deleteUnit(u) {
  const confirmed = await confirmDelete(`Delete academic unit "${u.name}"?`)
  if (!confirmed) return
  useForm({}).delete(route('faculty-loading.academic-units.destroy', u.id))
}

const copyModal = ref(false)
const copyForm  = useForm({
  source_school_year_id: props.currentSchoolYearId,
  target_school_year_id: props.currentSchoolYearId,
})

function doCopy() {
  copyForm.post(route('faculty-loading.academic-units.copy-from-year'), {
    onSuccess: () => { copyModal.value = false }
  })
}

const TYPE_LABELS = {
  department:  'Dept',
  junior_high: 'Junior HS',
  senior_high: 'Senior HS',
  sst:         'SST',
  admin:       'Admin',
}
const TYPE_COLORS = {
  department:  'indigo',
  junior_high: 'blue',
  senior_high: 'purple',
  sst:         'amber',
  admin:       'slate',
}
const typeLabel = (t) => TYPE_LABELS[t] ?? t
const typeColor = (t) => TYPE_COLORS[t] ?? 'slate'
</script>
