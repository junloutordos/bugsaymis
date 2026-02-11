<template>
  <Head title="Schedule" />
  <AdminLayout title="Schedule">
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Schedule</h1>
        <div class="flex items-center gap-2">
          <button v-if="canCreate" @click="openCreate" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow inline-flex items-center">
            <PlusIcon class="w-5 h-5 inline-block mr-1" /> New Schedule
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="mb-4">
          <input v-model="q" @keydown.enter="search" placeholder="Search by time" class="w-1/3 rounded border-gray-300 px-3 py-2" />
        </div>

        <div class="overflow-x-auto">
          <div class="hidden sm:block">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-100 text-gray-700"><tr>
                <th class="px-3 py-2">#</th>
                <th class="px-3 py-2">Badge</th>
                <th class="px-3 py-2">Mon</th>
                <th class="px-3 py-2">Tue</th>
                <th class="px-3 py-2">Wed</th>
                <th class="px-3 py-2">Thu</th>
                <th class="px-3 py-2">Fri</th>
                <th class="px-3 py-2">Sat</th>
                <th class="px-3 py-2">Sun</th>
                <th class="px-3 py-2">Actions</th>
              </tr></thead>
              <tbody class="divide-y">
                <tr v-for="s in schedules.data" :key="s.id">
                  <td class="px-3 py-2">{{ s.id }}</td>
                  <td class="px-3 py-2">{{ s.badgeNumber }}</td>
                  <td class="px-3 py-2">{{ displayDay(s.m_timein, s.m_timeout) }}</td>
                  <td class="px-3 py-2">{{ displayDay(s.t_timein, s.t_timeout) }}</td>
                  <td class="px-3 py-2">{{ displayDay(s.w_timein, s.w_timeout) }}</td>
                  <td class="px-3 py-2">{{ displayDay(s.th_timein, s.th_timeout) }}</td>
                  <td class="px-3 py-2">{{ displayDay(s.f_timein, s.f_timeout) }}</td>
                  <td class="px-3 py-2">{{ displayDay(s.sat_timein, s.sat_timeout) }}</td>
                  <td class="px-3 py-2">{{ displayDay(s.sun_timein, s.sun_timeout) }}</td>
                  <td class="px-3 py-2">
                      <div class="flex gap-2">
                        <button v-if="isAdmin" @click.prevent="openEdit(s)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" aria-label="Edit" title="Edit">
                          <PencilSquareIcon class="w-5 h-5" />
                        </button>
                        <button v-if="isAdmin" @click.prevent="confirmDelete(s)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" aria-label="Delete" title="Delete">
                          <TrashIcon class="w-5 h-5" />
                        </button>
                      </div>
                  </td>
                </tr>
                <tr v-if="(schedules.data || []).length === 0"><td colspan="10" class="px-3 py-6 text-center text-gray-500">No schedules found.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Modal -->
      <div v-if="showModal" :class="isSmallScreen ? 'fixed inset-0 z-50 flex items-start justify-center bg-black bg-opacity-50 py-6 overflow-y-auto' : 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50'">
        <div :class="isSmallScreen ? 'bg-white w-full max-w-full max-h-screen relative rounded-none p-4 overflow-y-auto' : 'bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative'">
          <button class="absolute top-3 right-3" @click="closeModal">✕</button>
          <h3 class="text-lg font-semibold mb-3">{{ editing ? 'Edit' : 'New' }} Schedule</h3>
          <form @submit.prevent="submit">
            <div class="space-y-4">
              <!-- Monday -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="w-full sm:w-28 font-medium">Monday</div>
                <input type="time" v-model="form.m_timein" class="mt-1 block w-full sm:w-40 rounded border-gray-300" placeholder="Time In" />
                <input type="time" v-model="form.m_breakout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" placeholder="Break Out" />
                <input type="time" v-model="form.m_breakin" class="mt-1 block w-full sm:w-40 rounded border-gray-300" placeholder="Break In" />
                <input type="time" v-model="form.m_timeout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" placeholder="Time Out" />
              </div>

              <!-- Tuesday -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="w-full sm:w-28 font-medium">Tuesday</div>
                <input type="time" v-model="form.t_timein" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.t_breakout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.t_breakin" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.t_timeout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
              </div>

              <!-- Wednesday -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="w-full sm:w-28 font-medium">Wednesday</div>
                <input type="time" v-model="form.w_timein" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.w_breakout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.w_breakin" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.w_timeout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
              </div>

              <!-- Thursday -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="w-full sm:w-28 font-medium">Thursday</div>
                <input type="time" v-model="form.th_timein" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.th_breakout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.th_breakin" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.th_timeout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
              </div>

              <!-- Friday -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="w-full sm:w-28 font-medium">Friday</div>
                <input type="time" v-model="form.f_timein" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.f_breakout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.f_breakin" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.f_timeout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
              </div>

              <!-- Saturday -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="w-full sm:w-28 font-medium">Saturday</div>
                <input type="time" v-model="form.sat_timein" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.sat_breakout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.sat_breakin" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.sat_timeout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
              </div>

              <!-- Sunday -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="w-full sm:w-28 font-medium">Sunday</div>
                <input type="time" v-model="form.sun_timein" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.sun_breakout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.sun_breakin" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
                <input type="time" v-model="form.sun_timeout" class="mt-1 block w-full sm:w-40 rounded border-gray-300" />
              </div>
            </div>

            <div class="mt-4 flex gap-2">
              <button :disabled="form.processing" class="bg-blue-600 text-white px-4 py-2 rounded">{{ editing ? 'Save' : 'Create' }}</button>
              <button @click.prevent="closeModal" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import { PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline'

const page = usePage()
const props = defineProps({ schedules: Object, q: String })

const schedules = props.schedules || { data: [], current_page: 1, last_page: 1 }
const q = ref(props.q || '')

const showModal = ref(false)
const editing = ref(false)
const current = ref(null)

// responsive modal flag (true when viewport is small)
const isSmallScreen = ref(false)
const updateIsSmallScreen = () => { isSmallScreen.value = typeof window !== 'undefined' && window.innerWidth < 640 }
onMounted(() => {
  updateIsSmallScreen()
  window.addEventListener('resize', updateIsSmallScreen)
})
onBeforeUnmount(() => { window.removeEventListener('resize', updateIsSmallScreen) })

const userRole = page.props.auth?.user?.role?.name || ''
const isAdmin = userRole === 'Administrator'
const canCreate = ['Administrator', 'Staff', 'Faculty'].includes(userRole)

const form = useForm({
  m_timein:'',m_breakout:'',m_breakin:'',m_timeout:'',
  t_timein:'',t_breakout:'',t_breakin:'',t_timeout:'',
  w_timein:'',w_breakout:'',w_breakin:'',w_timeout:'',
  th_timein:'',th_breakout:'',th_breakin:'',th_timeout:'',
  f_timein:'',f_breakout:'',f_breakin:'',f_timeout:'',
  sat_timein:'',sat_breakout:'',sat_breakin:'',sat_timeout:'',
  sun_timein:'',sun_breakout:'',sun_breakin:'',sun_timeout:'',
})

function openCreate() { editing.value = false; current.value = null; form.reset(); showModal.value = true }
function openEdit(s) {
  editing.value = true
  current.value = s
  // assign to form
  Object.keys(form).forEach(k => { if (s[k] !== undefined) form[k] = s[k] || '' })
  showModal.value = true
}
function closeModal() { showModal.value = false }

function submit() {
  if (editing.value && current.value) {
    form.put(route('schedules.update', current.value.id), {
      onSuccess: () => { showModal.value = false; Swal.fire({ icon: 'success', title: 'Schedule updated' }).then(()=>{ router.get(route('schedules.index'), { q: q.value }, { replace: true }) }) },
      onError: (errors) => {
        let text = Object.values(errors || {}).flat().join('\n')
        if (errors && errors.badgeNumber) text = (Array.isArray(errors.badgeNumber) ? errors.badgeNumber.join('\n') : errors.badgeNumber)
        Swal.fire({ icon: 'error', title: 'Failed to update', text })
      }
    })
  } else {
    form.post(route('schedules.store'), {
      onSuccess: () => { showModal.value = false; Swal.fire({ icon: 'success', title: 'Schedule created' }).then(()=>{ router.get(route('schedules.index'), { q: q.value }, { replace: true }) }) },
      onError: (errors) => {
        let text = Object.values(errors || {}).flat().join('\n')
        if (errors && errors.badgeNumber) text = (Array.isArray(errors.badgeNumber) ? errors.badgeNumber.join('\n') : errors.badgeNumber)
        Swal.fire({ icon: 'error', title: 'Failed to create', text })
      }
    })
  }
}

function confirmDelete(s) {
  Swal.fire({ title: 'Delete schedule?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true }).then((r) => {
    if (r.isConfirmed) {
      router.delete(route('schedules.destroy', s.id), {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Schedule deleted' }).then(()=>{ router.get(route('schedules.index'), { q: q.value }, { replace: true }) }) }
      })
    }
  })
}

function search() { router.get(route('schedules.index'), { q: q.value }, { replace: true }) }

function displayDay(start, end) {
  if (!start && !end) return '—'
  return `${start || '—'} ${end ? '– '+end : ''}`
}
</script>
