<template>
  <Head title="Schedule" />
  <AdminLayout title="Schedule">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Physician Schedule</h1>
          <p class="text-sm text-slate-500">Manage physician availability dates and times</p>
        </div>
        <div class="flex items-center gap-2">
          <button @click="openCreate" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            New Schedule
          </button>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input v-model="q" @keydown.enter="search" placeholder="Search by day or time" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
        <button @click="search" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          Search
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <!-- Desktop table -->
          <div class="hidden sm:block">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Start</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">End</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="s in schedules.data" :key="s.id" class="hover:bg-slate-50/60">
                  <td class="px-4 py-3 text-sm text-slate-700">{{ s.id }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700">{{ s.schedule_date || '—' }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700">{{ s.time_start }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700">{{ s.time_end }}</td>
                  <td class="px-4 py-3">
                    <div class="flex gap-1">
                      <button @click.prevent="openEdit(s)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" aria-label="Edit" title="Edit">
                        <PencilSquareIcon class="h-4 w-4" />
                      </button>
                      <button @click.prevent="confirmDelete(s)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" aria-label="Delete" title="Delete">
                        <TrashIcon class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="(schedules.data || []).length === 0">
                  <td colspan="5" class="py-16 text-center text-slate-400 text-sm">No schedules found.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile stacked cards -->
          <div class="sm:hidden divide-y divide-slate-100">
            <div v-for="s in schedules.data" :key="'card-'+s.id" class="p-4">
              <div class="flex justify-between items-start">
                <div>
                  <div class="text-xs text-slate-500">#{{ s.id }}</div>
                  <div class="font-medium text-slate-800 mt-0.5">{{ s.schedule_date || '—' }}</div>
                  <div class="text-sm text-slate-600 mt-1">{{ s.time_start || '—' }} – {{ s.time_end || '—' }}</div>
                </div>
                <div class="flex gap-1">
                  <button @click.prevent="openEdit(s)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                    <PencilSquareIcon class="h-4 w-4" />
                  </button>
                  <button @click.prevent="confirmDelete(s)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
            <div v-if="(schedules.data || []).length === 0" class="py-16 text-center text-slate-400 text-sm">No schedules found.</div>
          </div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ schedules.current_page }} of {{ schedules.last_page }}</span>
          <div class="flex gap-2">
            <button @click.prevent="goTo(schedules.prev_page_url)" :disabled="!schedules.prev_page_url" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
            <button @click.prevent="goTo(schedules.next_page_url)" :disabled="!schedules.next_page_url" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">{{ editing ? 'Edit' : 'New' }} Schedule</h3>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>
          <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
              <input type="date" v-model="form.schedule_date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Start Time</label>
              <input type="time" v-model="form.time_start" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">End Time</label>
              <input type="time" v-model="form.time_end" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
          </form>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click.prevent="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">{{ editing ? 'Save Changes' : 'Create' }}</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

const page = usePage()
const props = defineProps({ schedules: Object, q: String })

const schedules = props.schedules || { data: [], current_page: 1, last_page: 1 }
const q = ref(props.q || '')

const showModal = ref(false)
const editing = ref(false)
const current = ref(null)

const form = useForm({ schedule_date: '', time_start: '', time_end: '' })

function openCreate() { editing.value = false; current.value = null; form.reset(); showModal.value = true }
function openEdit(s) {
  editing.value = true
  current.value = s
  // Normalize time to HH:MM for time inputs (strip seconds if present)
  const normalize = (t) => {
    if (!t) return ''
    return t.length >= 5 ? t.slice(0,5) : t
  }
  // assign directly to the form fields to avoid fill() compatibility issues
  form.time_start = normalize(s.time_start)
  form.time_end = normalize(s.time_end)
  form.schedule_date = s.schedule_date ? (s.schedule_date.length >= 10 ? s.schedule_date.slice(0,10) : s.schedule_date) : ''
  showModal.value = true
}
function closeModal() { showModal.value = false }

function submit() {
  if (editing.value && current.value) {
    form.put(route('physician-schedule.update', current.value.id), {
      onSuccess: () => {
        showModal.value = false
        Swal.fire({ icon: 'success', title: 'Schedule updated' }).then(() => {
          router.get(route('physician-schedule.index'), { q: q.value }, { replace: true })
        })
      }
    })
  } else {
    form.post(route('physician-schedule.store'), {
      onSuccess: () => {
        showModal.value = false
        Swal.fire({ icon: 'success', title: 'Schedule created' }).then(() => {
          router.get(route('physician-schedule.index'), { q: q.value }, { replace: true })
        })
      }
    })
  }
}

function confirmDelete(s) {
  Swal.fire({ title: 'Delete schedule?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true }).then((r) => {
    if (r.isConfirmed) {
      router.delete(route('physician-schedule.destroy', s.id), {
        onSuccess: () => {
          Swal.fire({ icon: 'success', title: 'Schedule deleted' }).then(() => {
            router.get(route('physician-schedule.index'), { q: q.value }, { replace: true })
          })
        }
      })
    }
  })
}

function search() {
  router.get(route('physician-schedule.index'), { q: q.value }, { replace: true })
}

function goTo(url) {
  if (!url) return; window.location.href = url;
}

function capitalize(v) { if (!v) return ''; return v.charAt(0).toUpperCase() + v.slice(1) }
</script>
