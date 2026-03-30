<template>
  <Head title="Schedule" />
  <AdminLayout title="Schedule">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Schedule</h1>
        <div class="flex items-center gap-2">
          <button v-if="canCreate" @click="openCreate" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <PlusIcon class="w-4 h-4" /> New Schedule
          </button>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input v-model="q" @keydown.enter="search" placeholder="Search by time" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-xl border border-slate-100">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Badge</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Mon</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Tue</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Wed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Thu</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Fri</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Sat</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Sun</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="s in schedules.data" :key="s.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ s.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ s.badgeNumber }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ displayDay(s.m_timein, s.m_timeout) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ displayDay(s.t_timein, s.t_timeout) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ displayDay(s.w_timein, s.w_timeout) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ displayDay(s.th_timein, s.th_timeout) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ displayDay(s.f_timein, s.f_timeout) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ displayDay(s.sat_timein, s.sat_timeout) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ displayDay(s.sun_timein, s.sun_timeout) }}</td>
                <td class="px-4 py-3">
                  <div class="flex gap-1">
                    <button v-if="isAdmin" @click.prevent="openEdit(s)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" aria-label="Edit" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button v-if="isAdmin" @click.prevent="confirmDelete(s)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" aria-label="Delete" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="(schedules.data || []).length === 0">
                <td colspan="10" class="py-16 text-center text-slate-400 text-sm">No schedules found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" :class="isSmallScreen ? 'fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 py-6 overflow-y-auto' : 'fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50'">
        <div :class="isSmallScreen ? 'bg-white w-full max-w-full max-h-screen relative rounded-none p-4 overflow-y-auto' : 'bg-white rounded-2xl shadow-xl w-full max-w-2xl relative'">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">{{ editing ? 'Edit' : 'New' }} Schedule</h3>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">✕</button>
          </div>
          <div class="px-6 py-5">
            <form @submit.prevent="submit">
              <div class="space-y-4">
                <!-- Monday -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                  <div class="w-full sm:w-28 text-sm font-medium text-slate-700">Monday</div>
                  <input type="time" v-model="form.m_timein" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" placeholder="Time In" />
                  <input type="time" v-model="form.m_breakout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" placeholder="Break Out" />
                  <input type="time" v-model="form.m_breakin" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" placeholder="Break In" />
                  <input type="time" v-model="form.m_timeout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" placeholder="Time Out" />
                </div>

                <!-- Tuesday -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                  <div class="w-full sm:w-28 text-sm font-medium text-slate-700">Tuesday</div>
                  <input type="time" v-model="form.t_timein" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.t_breakout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.t_breakin" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.t_timeout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                </div>

                <!-- Wednesday -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                  <div class="w-full sm:w-28 text-sm font-medium text-slate-700">Wednesday</div>
                  <input type="time" v-model="form.w_timein" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.w_breakout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.w_breakin" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.w_timeout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                </div>

                <!-- Thursday -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                  <div class="w-full sm:w-28 text-sm font-medium text-slate-700">Thursday</div>
                  <input type="time" v-model="form.th_timein" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.th_breakout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.th_breakin" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.th_timeout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                </div>

                <!-- Friday -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                  <div class="w-full sm:w-28 text-sm font-medium text-slate-700">Friday</div>
                  <input type="time" v-model="form.f_timein" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.f_breakout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.f_breakin" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.f_timeout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                </div>

                <!-- Saturday -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                  <div class="w-full sm:w-28 text-sm font-medium text-slate-700">Saturday</div>
                  <input type="time" v-model="form.sat_timein" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.sat_breakout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.sat_breakin" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.sat_timeout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                </div>

                <!-- Sunday -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                  <div class="w-full sm:w-28 text-sm font-medium text-slate-700">Sunday</div>
                  <input type="time" v-model="form.sun_timein" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.sun_breakout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.sun_breakin" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                  <input type="time" v-model="form.sun_timeout" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-40" />
                </div>
              </div>

              <div class="mt-6 flex gap-2">
                <button :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">{{ editing ? 'Save' : 'Create' }}</button>
                <button @click.prevent="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              </div>
            </form>
          </div>
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
