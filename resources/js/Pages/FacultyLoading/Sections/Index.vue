<template>
  <Head title="Sections" />
  <AdminLayout title="Sections">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Sections</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage student sections (Grades 7–12)</p>
        </div>
        <button @click="openForm()"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" /> New Section
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <select v-model="filters.school_year_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All School Years</option>
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </select>
      </div>

      <!-- Grouped by grade -->
      <div v-if="sections.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <RectangleGroupIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No sections found</p>
        <p class="text-xs text-slate-400 mt-1">Create a section to get started.</p>
      </div>

      <div v-else class="space-y-4">
        <div v-for="grade in gradesPresent" :key="grade"
          class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">Grade {{ grade }}</h3>
            <span class="text-xs text-slate-400">{{ byGrade[grade].length }} section(s)</span>
          </div>
          <table class="min-w-full divide-y divide-slate-50 text-sm">
            <thead>
              <tr class="text-xs text-slate-400 uppercase tracking-wide">
                <th class="px-4 py-2 text-left">Name</th>
                <th class="px-4 py-2 text-left">Code</th>
                <th class="px-4 py-2 text-left">Strand</th>
                <th class="px-4 py-2 text-center">Capacity</th>
                <th class="px-4 py-2 text-left">Adviser</th>
                <th class="px-4 py-2 text-center">Status</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="s in byGrade[grade]" :key="s.id" class="hover:bg-slate-50/50"
                :class="{ 'opacity-50': !s.is_active }">
                <td class="px-4 py-3 font-medium text-slate-800">{{ s.sectionname }}</td>
                <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ s.section_code ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600">{{ s.strand ?? '—' }}</td>
                <td class="px-4 py-3 text-center text-slate-600">{{ s.capacity ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600">{{ s.adviser?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                    {{ s.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button @click="openForm(s)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                      <PencilIcon class="h-4 w-4" />
                    </button>
                    <button @click="remove(s)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 my-8">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'New' }} Section</h2>

        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Section Name *</label>
            <input v-model="form.sectionname" type="text" placeholder="e.g. Newton"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level *</label>
            <select v-model.number="form.levelid" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Section Code</label>
            <input v-model="form.section_code" type="text" placeholder="e.g. G7-A"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Strand (SHS)</label>
            <input v-model="form.strand" type="text" placeholder="e.g. STEM"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Capacity</label>
            <input v-model.number="form.capacity" type="number" min="1" max="60"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Adviser</label>
            <select v-model="form.adviser" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">No adviser</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>

          <!-- School year (create only) -->
          <div v-if="!form.id" class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year *</label>
            <select v-model="form.syid" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select school year...</option>
              <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">{{ sy.name }}</option>
            </select>
          </div>

          <div class="col-span-2 flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" id="is-active" class="rounded text-indigo-600" />
            <label for="is-active" class="text-sm text-slate-700">Active</label>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button @click="modal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="save" :disabled="form.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
            {{ form.id ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CheckCircleIcon, PencilIcon, PlusIcon, RectangleGroupIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  sections:    { type: Array,  default: () => [] },
  schoolYears: { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  filters:     { type: Object, default: () => ({}) },
})

const filters = reactive({
  school_year_id: props.filters.school_year_id ?? null,
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
  strand: '',
  capacity: null,
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
      strand: s.strand ?? '',
      capacity: s.capacity ?? null,
      adviser: s.adviser?.id ?? null,
      syid: null,
      is_active: s.is_active,
    })
  } else {
    form.reset()
    form.id = null
    form.levelid = 7
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

function remove(s) {
  if (!confirm(`Delete/deactivate "${s.sectionname}"?`)) return
  useForm({}).delete(route('faculty-loading.sections.destroy', s.id))
}
</script>
