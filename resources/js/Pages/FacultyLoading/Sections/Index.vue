<template>
  <Head title="Sections" />
  <AdminLayout title="Sections">
    <div class="space-y-5">

      <AppPageHeader title="Sections" subtitle="Manage student sections (Grades 7–12)">
        <template #actions>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> New Section
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

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="filters.school_year_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All School Years</option>
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </select>
      </AppFilterBar>

      <!-- Empty -->
      <AppCard v-if="sections.length === 0">
        <EmptyState title="No sections found" subtitle="Create a section to get started." :icon="RectangleGroupIcon" />
      </AppCard>

      <div v-else class="space-y-4">
        <AppCard v-for="grade in gradesPresent" :key="grade" :padded="false">
          <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">Grade {{ grade }}</h3>
            <span class="text-xs text-slate-400">{{ byGrade[grade].length }} section(s)</span>
          </div>

          <AppTable :card="false">
            <template #head>
              <tr>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Code</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Capacity</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Home Room</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Adviser</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                <th class="px-4 py-3"></th>
              </tr>
            </template>

            <tr v-for="s in byGrade[grade]" :key="s.id" class="hover:bg-slate-50/50"
              :class="{ 'opacity-50': !s.is_active }">
              <td class="px-4 py-3 font-medium text-slate-800">{{ s.sectionname }}</td>
              <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ s.section_code ?? '—' }}</td>
              <td class="px-4 py-3 text-center text-slate-600">{{ s.capacity ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-600 text-xs">
                <span v-if="s.classroom" class="font-medium">{{ s.classroom.name }}</span>
                <span v-else class="text-slate-400 italic">No room</span>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ s.adviser?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-center">
                <AppBadge :color="s.is_active ? 'green' : 'slate'">{{ s.is_active ? 'Active' : 'Inactive' }}</AppBadge>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <Link :href="route('faculty-loading.sections.show', s.id)" title="View detail"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-indigo-600">
                    <EyeIcon class="h-4 w-4" />
                  </Link>
                  <AppIconButton label="Edit section" @click="openForm(s)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                  <AppIconButton label="Delete section" variant="danger" @click="remove(s)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                </div>
              </td>
            </tr>
          </AppTable>
        </AppCard>
      </div>

    </div>

    <!-- Modal -->
    <AppModal :show="modal" :title="`${form.id ? 'Edit' : 'New'} Section`" size="lg" @close="modal = false">
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2">
          <AppInput v-model="form.sectionname" label="Section Name" required placeholder="e.g. Newton" :error="form.errors.sectionname" />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level *</label>
          <select v-model.number="form.levelid"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
          </select>
        </div>

        <AppInput v-model="form.section_code" label="Section Code" placeholder="e.g. G7-A" />

        <AppInput v-model.number="form.capacity" type="number" min="1" max="60" label="Capacity" />

        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Home Room</label>
          <select v-model.number="form.classroom_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">No fixed room</option>
            <option v-for="r in classrooms" :key="r.id" :value="r.id">
              {{ r.name }}<template v-if="r.code"> ({{ r.code }})</template>
            </option>
          </select>
          <p class="text-xs text-slate-400 mt-1">Used by the schedule generator to fix all classes to this room.</p>
        </div>

        <!-- Break times -->
        <div class="col-span-2 pt-1">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Break Times</p>
          <div class="grid grid-cols-2 gap-3">
            <AppInput v-model="form.recess_start" type="time" label="Recess Start" />
            <AppInput v-model="form.recess_end" type="time" label="Recess End" />
            <AppInput v-model="form.lunch_start" type="time" label="Lunch Start" />
            <AppInput v-model="form.lunch_end" type="time" label="Lunch End" />
            <AppInput v-model="form.afternoon_break_start" type="time" label="Afternoon Break Start" />
            <AppInput v-model="form.afternoon_break_end" type="time" label="Afternoon Break End" />
          </div>
        </div>

        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Adviser</label>
          <select v-model="form.adviser"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">No adviser</option>
            <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>

        <!-- School year (create only) -->
        <div v-if="!form.id" class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">School Year *</label>
          <select v-model="form.syid"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Select school year...</option>
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">{{ sy.name }}</option>
          </select>
          <p v-if="form.errors.syid" class="text-xs text-danger-500 mt-0.5">{{ form.errors.syid }}</p>
        </div>

        <div class="col-span-2 flex items-center gap-2">
          <input v-model="form.is_active" type="checkbox" id="is-active" class="rounded text-indigo-600" />
          <label for="is-active" class="text-sm text-slate-700">Active</label>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { CheckCircleIcon, EyeIcon, PencilIcon, PlusIcon, RectangleGroupIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  sections:    { type: Array,  default: () => [] },
  schoolYears: { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  classrooms:  { type: Array,  default: () => [] },
  filters:     { type: Object, default: () => ({}) },
})

const filters = reactive({
  school_year_id: props.filters.school_year_id ?? null,   // controller always supplies current year default
})

function applyFilters() {
  router.get(route('faculty-loading.sections.index'), filters, { preserveState: true })
}

const byGrade = computed(() => {
  const map = {}
  for (const s of props.sections) {
    if (!map[s.levelid]) map[s.levelid] = []
    map[s.levelid].push(s)
  }
  return map
})

const gradesPresent = computed(() => Object.keys(byGrade.value).map(Number).sort((a, b) => a - b))

const modal = ref(false)
const form = useForm({
  id: null,
  sectionname: '',
  levelid: 7,
  section_code: '',
  capacity: 30,
  classroom_id: null,
  recess_start: '',
  recess_end: '',
  lunch_start: '',
  lunch_end: '',
  afternoon_break_start: '',
  afternoon_break_end: '',
  adviser: null,
  syid: null,
  is_active: true,
})

function openForm(s = null) {
  if (s) {
    Object.assign(form, {
      id: s.id,
      sectionname: s.sectionname,
      levelid: s.levelid,
      section_code: s.section_code ?? '',
      capacity: s.capacity ?? null,
      classroom_id: s.classroom_id ?? null,
      recess_start: s.recess_start ?? '',
      recess_end: s.recess_end ?? '',
      lunch_start: s.lunch_start ?? '',
      lunch_end: s.lunch_end ?? '',
      afternoon_break_start: s.afternoon_break_start ?? '',
      afternoon_break_end: s.afternoon_break_end ?? '',
      adviser: s.adviser?.id ?? null,
      syid: null,
      is_active: s.is_active,
    })
  } else {
    form.reset()
    form.id = null
    form.levelid = 7
    form.capacity = 30
    form.classroom_id = null
    form.recess_start = ''
    form.recess_end = ''
    form.lunch_start = ''
    form.lunch_end = ''
    form.afternoon_break_start = ''
    form.afternoon_break_end = ''
    form.is_active = true
    const currentSy = props.schoolYears.find(sy => sy.is_current)
    form.syid = currentSy?.id ?? null
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.sections.update', form.id), {
      onSuccess: () => { modal.value = false },
    })
  } else {
    form.post(route('faculty-loading.sections.store'), {
      onSuccess: () => { modal.value = false },
    })
  }
}

async function remove(s) {
  if (! await confirmDelete(`Delete/deactivate "${s.sectionname}"?`)) return
  useForm({}).delete(route('faculty-loading.sections.destroy', s.id))
}
</script>
