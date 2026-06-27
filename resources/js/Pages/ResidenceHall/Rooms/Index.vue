<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  rooms: Array,
  myHall: String,
})

const showModal = ref(false)
const editTarget = ref(null)
const form = ref({
  residence_hall: props.myHall || 'BRH',
  room_number: '',
  capacity: 4,
  floor: '',
  description: '',
  status: 'active',
})

function openAdd() {
  editTarget.value = null
  form.value = {
    residence_hall: props.myHall || 'BRH',
    room_number: '',
    capacity: 4,
    floor: '',
    description: '',
    status: 'active',
  }
  showModal.value = true
}

function openEdit(room) {
  editTarget.value = room
  form.value = {
    residence_hall: room.residence_hall,
    room_number: room.room_number,
    capacity: room.capacity,
    floor: room.floor || '',
    description: room.description || '',
    status: room.status,
  }
  showModal.value = true
}

function save() {
  if (editTarget.value) {
    router.put(route('rh.rooms.update', editTarget.value.id), form.value, {
      preserveScroll: true,
      onSuccess: () => { showModal.value = false },
    })
  } else {
    router.post(route('rh.rooms.store'), form.value, {
      preserveScroll: true,
      onSuccess: () => { showModal.value = false },
    })
  }
}

function remove(room) {
  if (!confirm(`Delete Room ${room.room_number} (${room.residence_hall})?`)) return
  router.delete(route('rh.rooms.destroy', room.id), { preserveScroll: true })
}

const brhRooms = computed(() => props.rooms.filter(r => r.residence_hall === 'BRH'))
const grhRooms = computed(() => props.rooms.filter(r => r.residence_hall === 'GRH'))

const occupancyColor = (room) => {
  const pct = room.active_interns_count / room.capacity
  if (pct >= 1) return 'bg-rose-500'
  if (pct >= 0.75) return 'bg-amber-400'
  return 'bg-emerald-400'
}
</script>

<template>
  <Head title="RH Rooms" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Room Management</h1>
          <p class="text-sm text-slate-500">{{ myHall ? (myHall === 'BRH' ? 'Boys Residence Hall' : 'Girls Residence Hall') : 'All Halls' }}</p>
        </div>
        <button @click="openAdd"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" /> Add Room
        </button>
      </div>

      <!-- BRH -->
      <div v-if="!myHall || myHall === 'BRH'">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Boys Residence Hall (BRH)</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <div v-for="room in brhRooms" :key="room.id"
               class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="flex items-start justify-between mb-3">
              <div>
                <div class="text-lg font-bold text-slate-800">{{ room.room_number }}</div>
                <div class="text-xs text-slate-400">Floor {{ room.floor || '—' }}</div>
              </div>
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', room.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500']">
                {{ room.status }}
              </span>
            </div>
            <!-- Occupancy bar -->
            <div class="mb-3">
              <div class="flex justify-between text-xs text-slate-500 mb-1">
                <span>{{ room.active_interns_count }} / {{ room.capacity }}</span>
                <span>{{ Math.round(room.active_interns_count / room.capacity * 100) }}%</span>
              </div>
              <div class="w-full bg-slate-100 rounded-full h-1.5">
                <div :class="['h-1.5 rounded-full transition-all', occupancyColor(room)]"
                     :style="`width: ${Math.min(100, room.active_interns_count / room.capacity * 100)}%`"></div>
              </div>
            </div>
            <p v-if="room.description" class="text-xs text-slate-400 mb-3 truncate">{{ room.description }}</p>
            <div class="flex gap-2">
              <button @click="openEdit(room)"
                      class="flex-1 text-xs py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center gap-1">
                <PencilIcon class="w-3 h-3" /> Edit
              </button>
              <button @click="remove(room)"
                      class="text-xs py-1.5 px-2 rounded-lg border border-slate-200 text-rose-500 hover:bg-rose-50">
                <TrashIcon class="w-3 h-3" />
              </button>
            </div>
          </div>
          <div v-if="!brhRooms.length" class="col-span-full text-sm text-slate-400 text-center py-8">
            No BRH rooms yet. Add one above.
          </div>
        </div>
      </div>

      <!-- GRH -->
      <div v-if="!myHall || myHall === 'GRH'">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Girls Residence Hall (GRH)</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <div v-for="room in grhRooms" :key="room.id"
               class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="flex items-start justify-between mb-3">
              <div>
                <div class="text-lg font-bold text-slate-800">{{ room.room_number }}</div>
                <div class="text-xs text-slate-400">Floor {{ room.floor || '—' }}</div>
              </div>
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', room.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500']">
                {{ room.status }}
              </span>
            </div>
            <div class="mb-3">
              <div class="flex justify-between text-xs text-slate-500 mb-1">
                <span>{{ room.active_interns_count }} / {{ room.capacity }}</span>
                <span>{{ Math.round(room.active_interns_count / room.capacity * 100) }}%</span>
              </div>
              <div class="w-full bg-slate-100 rounded-full h-1.5">
                <div :class="['h-1.5 rounded-full transition-all', occupancyColor(room)]"
                     :style="`width: ${Math.min(100, room.active_interns_count / room.capacity * 100)}%`"></div>
              </div>
            </div>
            <p v-if="room.description" class="text-xs text-slate-400 mb-3 truncate">{{ room.description }}</p>
            <div class="flex gap-2">
              <button @click="openEdit(room)"
                      class="flex-1 text-xs py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center gap-1">
                <PencilIcon class="w-3 h-3" /> Edit
              </button>
              <button @click="remove(room)"
                      class="text-xs py-1.5 px-2 rounded-lg border border-slate-200 text-rose-500 hover:bg-rose-50">
                <TrashIcon class="w-3 h-3" />
              </button>
            </div>
          </div>
          <div v-if="!grhRooms.length" class="col-span-full text-sm text-slate-400 text-center py-8">
            No GRH rooms yet. Add one above.
          </div>
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
          <h3 class="text-base font-semibold text-slate-800 mb-4">{{ editTarget ? 'Edit Room' : 'Add Room' }}</h3>
          <div class="space-y-3">
            <div v-if="!myHall">
              <label class="block text-xs font-medium text-slate-600 mb-1">Residence Hall</label>
              <select v-model="form.residence_hall"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="BRH">BRH — Boys Residence Hall</option>
                <option value="GRH">GRH — Girls Residence Hall</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Room Number</label>
                <input v-model="form.room_number" type="text" placeholder="e.g. 101"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Capacity</label>
                <input v-model.number="form.capacity" type="number" min="1" max="20"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Floor</label>
                <input v-model.number="form.floor" type="number" min="1"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select v-model="form.status"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description (optional)</label>
              <input v-model="form.description" type="text"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
          <div class="flex gap-3 mt-5">
            <button @click="showModal = false"
                    class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">
              Cancel
            </button>
            <button @click="save"
                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
              {{ editTarget ? 'Save Changes' : 'Add Room' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
