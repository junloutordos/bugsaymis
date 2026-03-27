<template>
  <Head title="Classrooms" />
  <AdminLayout title="Classrooms">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Classrooms</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage classroom and laboratory inventory</p>
        </div>
        <button @click="openForm()"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" /> New Classroom
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">{{ $page.props.errors.error }}</div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
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
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-xl font-bold text-slate-800">{{ classrooms.length }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Total Rooms</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-xl font-bold text-emerald-600">{{ classrooms.filter(c => c.is_available).length }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Available</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-xl font-bold text-red-500">{{ classrooms.filter(c => !c.is_available).length }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Unavailable</p>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="classrooms.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <BuildingOfficeIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No classrooms found</p>
      </div>

      <!-- Grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="c in classrooms" :key="c.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-col gap-2 hover:shadow-md transition-shadow">
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-slate-800">{{ c.name }}</p>
              <p class="text-xs text-slate-400 font-mono">{{ c.code }}</p>
            </div>
            <div class="flex items-center gap-1">
              <button @click="openForm(c)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                <PencilIcon class="h-4 w-4" />
              </button>
              <button @click="deleteClassroom(c)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <span :class="typeBadge(c.classroom_type)"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
              {{ c.classroom_type }}
            </span>
            <span :class="c.is_available ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
              {{ c.is_available ? 'Available' : 'Unavailable' }}
            </span>
          </div>
          <div class="text-xs text-slate-500 flex items-center gap-3">
            <span class="flex items-center gap-1"><UsersIcon class="h-3.5 w-3.5" /> {{ c.capacity }} seats</span>
            <span v-if="c.building">{{ c.building }}<template v-if="c.floor">, Floor {{ c.floor }}</template></span>
          </div>
          <p v-if="c.remarks" class="text-xs text-slate-400 italic">{{ c.remarks }}</p>
        </div>
      </div>

    </div>

    <!-- Classroom Modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'New' }} Classroom</h2>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name *</label>
            <input v-model="form.name" type="text" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <p v-if="form.errors.name" class="text-xs text-red-500 mt-0.5">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Code *</label>
            <input v-model="form.code" type="text" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <p v-if="form.errors.code" class="text-xs text-red-500 mt-0.5">{{ form.errors.code }}</p>
          </div>
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
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Capacity *</label>
            <input v-model.number="form.capacity" type="number" min="1" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Building</label>
            <input v-model="form.building" type="text" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Floor</label>
            <input v-model.number="form.floor" type="number" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
          </div>
          <div class="flex items-center gap-2">
            <input v-model="form.is_available" type="checkbox" id="room-avail" class="rounded text-indigo-600" />
            <label for="room-avail" class="text-sm text-slate-600">Available for scheduling</label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
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
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  BuildingOfficeIcon, CheckCircleIcon, PencilIcon, PlusIcon, TrashIcon, UsersIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  classrooms: { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({}) },
})

const filters = reactive({
  search: props.filters.search ?? '',
  classroom_type: props.filters.classroom_type ?? 'all',
  available: !! props.filters.available,
})

function applyFilters() {
  router.get(route('faculty-loading.classrooms.index'), filters, { preserveState: true })
}

function typeBadge(type) {
  const map = {
    lecture: 'bg-blue-50 text-blue-700', laboratory: 'bg-violet-50 text-violet-700',
    science_lab: 'bg-emerald-50 text-emerald-700', ict_lab: 'bg-cyan-50 text-cyan-700',
    language_lab: 'bg-teal-50 text-teal-700', seminar: 'bg-orange-50 text-orange-700',
  }
  return map[type] ?? 'bg-slate-50 text-slate-600'
}

const modal = ref(false)
const form  = useForm({
  id: null, name: '', code: '', classroom_type: 'lecture',
  capacity: 40, building: '', floor: null, is_available: true, remarks: '',
})

function openForm(c = null) {
  if (c) {
    Object.assign(form, { id: c.id, name: c.name, code: c.code, classroom_type: c.classroom_type,
      capacity: c.capacity, building: c.building ?? '', floor: c.floor,
      is_available: c.is_available, remarks: c.remarks ?? '' })
  } else {
    form.reset(); form.id = null; form.classroom_type = 'lecture'; form.capacity = 40; form.is_available = true
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

function deleteClassroom(c) {
  if (! confirm(`Delete classroom "${c.name}"?`)) return
  useForm({}).delete(route('faculty-loading.classrooms.destroy', c.id))
}
</script>
