<template>
  <Head title="Classrooms" />
  <AdminLayout title="Classrooms">
    <div class="space-y-5">

      <AppPageHeader title="Classrooms" subtitle="Manage classroom and laboratory inventory">
        <template #actions>
          <AppButton variant="secondary" @click="copyModal = true">
            <DocumentDuplicateIcon class="h-4 w-4" /> Copy from Year
          </AppButton>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> New Classroom
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">{{ $page.props.errors.error }}</div>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="filters.school_year_id" @change="applyFilters"
          class="text-sm border border-indigo-300 bg-indigo-50 text-indigo-700 font-medium rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <input v-model="filters.search" @input="applyFilters" type="search" placeholder="Search name, code, building..."
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 w-56 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
        <select v-model="filters.classroom_type" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="all">All Types</option>
          <option value="lecture">Lecture</option>
          <option value="laboratory">Laboratory</option>
          <option value="science_lab">Science Lab</option>
          <option value="ict_lab">ICT Lab</option>
          <option value="language_lab">Language Lab</option>
        </select>
        <label class="flex items-center gap-1.5 text-sm text-slate-600 cursor-pointer">
          <input v-model="filters.available" @change="applyFilters" type="checkbox" class="rounded text-indigo-600" />
          Available only
        </label>
      </AppFilterBar>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-3">
        <AppCard>
          <div class="text-center">
            <p class="text-xl font-bold text-slate-800">{{ classrooms.length }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Total Rooms</p>
          </div>
        </AppCard>
        <AppCard>
          <div class="text-center">
            <p class="text-xl font-bold text-success-600">{{ classrooms.filter(c => c.is_available).length }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Available</p>
          </div>
        </AppCard>
        <AppCard>
          <div class="text-center">
            <p class="text-xl font-bold text-danger-500">{{ classrooms.filter(c => !c.is_available).length }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Unavailable</p>
          </div>
        </AppCard>
      </div>

      <!-- Empty -->
      <AppCard v-if="classrooms.length === 0">
        <EmptyState title="No classrooms found for this school year" subtitle="Use &quot;Copy from Year&quot; to import classrooms from a previous year." :icon="BuildingOfficeIcon" />
      </AppCard>

      <!-- Grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <AppCard v-for="c in classrooms" :key="c.id" class="hover:shadow-md transition-shadow">
          <div class="flex flex-col gap-2">
            <div class="flex items-start justify-between">
              <div>
                <p class="font-semibold text-slate-800">{{ c.name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ c.code }}</p>
              </div>
              <div class="flex items-center gap-1">
                <AppIconButton label="Edit classroom" @click="openForm(c)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                <AppIconButton label="Delete classroom" variant="danger" @click="deleteClassroom(c)"><TrashIcon class="h-4 w-4" /></AppIconButton>
              </div>
            </div>
            <div class="flex flex-wrap gap-1.5">
              <AppBadge :color="typeBadge(c.classroom_type)">{{ c.classroom_type }}</AppBadge>
              <AppBadge :color="c.is_available ? 'green' : 'red'">{{ c.is_available ? 'Available' : 'Unavailable' }}</AppBadge>
            </div>
            <div class="text-xs text-slate-500 flex items-center gap-3">
              <span class="flex items-center gap-1"><UsersIcon class="h-3.5 w-3.5" /> {{ c.capacity }} seats</span>
              <span v-if="c.building">{{ c.building }}<template v-if="c.floor">, Floor {{ c.floor }}</template></span>
            </div>
            <p v-if="c.remarks" class="text-xs text-slate-400 italic">{{ c.remarks }}</p>

            <!-- NFC section -->
            <div class="border-t border-slate-100 pt-2 mt-1">
              <div class="flex items-center justify-between gap-2">
                <AppBadge :color="c.nfc_uuid ? 'green' : 'slate'">
                  <SignalIcon class="h-3.5 w-3.5 mr-1" />
                  {{ c.nfc_uuid ? 'NFC Ready' : 'No NFC' }}
                </AppBadge>
                <div class="flex items-center gap-1">
                  <AppButton v-if="c.nfc_url" size="sm" :variant="copied === c.id ? 'success' : 'secondary'" @click="copyNfcUrl(c)">
                    <ClipboardDocumentIcon class="h-3 w-3" />
                    {{ copied === c.id ? 'Copied!' : 'Copy URL' }}
                  </AppButton>
                  <AppButton size="sm" variant="warning" @click="regenerate(c)" title="Generate new NFC UUID (reprogram physical tag after)">
                    <ArrowPathIcon class="h-3 w-3" />
                    Regenerate
                  </AppButton>
                </div>
              </div>
            </div>
          </div>
        </AppCard>
      </div>

    </div>

    <!-- Classroom Modal -->
    <AppModal :show="modal" :title="`${form.id ? 'Edit' : 'New'} Classroom`" @close="modal = false">
      <div class="grid grid-cols-2 gap-3">
        <AppInput v-model="form.name" label="Name" required :error="form.errors.name" />
        <AppInput v-model="form.code" label="Code" required :error="form.errors.code" />
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Type *</label>
          <select v-model="form.classroom_type" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="lecture">Lecture</option>
            <option value="laboratory">Laboratory</option>
            <option value="science_lab">Science Lab</option>
            <option value="physics_lab">Physics Lab</option>
            <option value="chemistry_lab">Chemistry Lab</option>
            <option value="biology_lab">Biology Lab</option>
            <option value="mathematics_lab">Mathematics Lab</option>
            <option value="ict_lab">ICT Lab</option>
            <option value="language_lab">Language Lab</option>
            <option value="seminar">Seminar Room</option>
            <option value="gymnasium">Gymnasium</option>
            <option value="other">Other</option>
          </select>
        </div>
        <AppInput v-model.number="form.capacity" type="number" min="1" label="Capacity" required />
        <AppInput v-model="form.building" label="Building" />
        <AppInput v-model.number="form.floor" type="number" label="Floor" />
        <div class="col-span-2">
          <AppTextarea v-model="form.remarks" label="Remarks" :rows="2" />
        </div>
        <div class="flex items-center gap-2">
          <input v-model="form.is_available" type="checkbox" id="room-avail" class="rounded text-indigo-600" />
          <label for="room-avail" class="text-sm text-slate-600">Available for scheduling</label>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>

    <!-- Copy from Year Modal -->
    <AppModal :show="copyModal" title="Copy Classrooms from Another Year"
      subtitle="Copies all classrooms from the source year into the target year, including NFC UUIDs so physical tags remain functional. Rooms with duplicate codes are skipped."
      @close="copyModal = false">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Source Year (copy from)</label>
          <select v-model="copyForm.source_school_year_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Target Year (copy into)</label>
          <select v-model="copyForm.target_school_year_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
            </option>
          </select>
        </div>
        <p v-if="copyForm.errors.target_school_year_id" class="text-xs text-danger-500">{{ copyForm.errors.target_school_year_id }}</p>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="copyModal = false">Cancel</AppButton>
        <AppButton :loading="copyForm.processing" @click="doCopy">Copy Classrooms</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import {
  BuildingOfficeIcon, CheckCircleIcon, DocumentDuplicateIcon, PencilIcon, PlusIcon,
  TrashIcon, UsersIcon, SignalIcon, ClipboardDocumentIcon, ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  classrooms:          { type: Array,  default: () => [] },
  schoolYears:         { type: Array,  default: () => [] },
  currentSchoolYearId: { type: Number, default: null },
  filters:             { type: Object, default: () => ({}) },
})

const filters = reactive({
  search:          props.filters.search          ?? '',
  classroom_type:  props.filters.classroom_type  ?? 'all',
  available:       !! props.filters.available,
  school_year_id:  props.filters.school_year_id  ?? props.currentSchoolYearId,
})

function applyFilters() {
  router.get(route('faculty-loading.classrooms.index'), filters, { preserveState: true })
}

function typeBadge(type) {
  const map = {
    lecture: 'blue', laboratory: 'purple',
    science_lab: 'green', ict_lab: 'indigo',
    language_lab: 'orange', seminar: 'orange',
  }
  return map[type] ?? 'slate'
}

const modal = ref(false)
const form  = useForm({
  id: null, school_year_id: props.currentSchoolYearId,
  name: '', code: '', classroom_type: 'lecture',
  capacity: 40, building: '', floor: null, is_available: true, remarks: '',
})

function openForm(c = null) {
  if (c) {
    Object.assign(form, { id: c.id, school_year_id: props.currentSchoolYearId,
      name: c.name, code: c.code, classroom_type: c.classroom_type,
      capacity: c.capacity, building: c.building ?? '', floor: c.floor,
      is_available: c.is_available, remarks: c.remarks ?? '' })
  } else {
    form.reset()
    form.id = null
    form.school_year_id = props.currentSchoolYearId
    form.classroom_type = 'lecture'; form.capacity = 40; form.is_available = true
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.classrooms.update', form.id), { onSuccess: () => { modal.value = false } })
  } else {
    form.post(route('faculty-loading.classrooms.store'), { onSuccess: () => { modal.value = false } })
  }
}

async function deleteClassroom(c) {
  if (! await confirmDelete(`Delete classroom "${c.name}"?`)) return
  useForm({}).delete(route('faculty-loading.classrooms.destroy', c.id))
}

const copied = ref(null)
function copyNfcUrl(c) {
  navigator.clipboard.writeText(c.nfc_url)
  copied.value = c.id
  setTimeout(() => { copied.value = null }, 2000)
}

async function regenerate(c) {
  if (! await confirmAction({
    title: 'Regenerate NFC UUID?',
    text: `Regenerate NFC UUID for "${c.name}"? The old NFC tag URL will stop working. You must reprogram the physical tag with the new URL.`,
    confirmText: 'Regenerate',
  })) return
  useForm({}).post(route('faculty-loading.classrooms.regenerate-nfc', c.id))
}

const copyModal = ref(false)
const copyForm  = useForm({
  source_school_year_id: props.currentSchoolYearId,
  target_school_year_id: props.currentSchoolYearId,
})

function doCopy() {
  copyForm.post(route('faculty-loading.classrooms.copy-from-year'), {
    onSuccess: () => { copyModal.value = false }
  })
}
</script>
