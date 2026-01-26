<template>
  <Head title="Schedule" />
  <AdminLayout title="Schedule">
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Physician Schedule</h1>
          <div class="flex items-center gap-2">
            <button @click="openCreate" class="bg-blue-600 text-white px-4 py-2 rounded">New</button>
          </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="mb-4">
          <input v-model="q" @keydown.enter="search" placeholder="Search by day or time" class="w-1/3 rounded border-gray-300 px-3 py-2" />
        </div>
        <div class="overflow-x-auto">
          <!-- Desktop / larger screens: table -->
          <div class="hidden sm:block">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-100 text-gray-700"><tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Start</th><th class="px-3 py-2">End</th><th class="px-3 py-2">Actions</th></tr></thead>
              <tbody class="divide-y">
                <tr v-for="s in schedules.data" :key="s.id">
                  <td class="px-3 py-2">{{ s.id }}</td>
                  <td class="px-3 py-2">{{ s.schedule_date || '—' }}</td>
                  <td class="px-3 py-2">{{ s.time_start }}</td>
                  <td class="px-3 py-2">{{ s.time_end }}</td>
                  <td class="px-3 py-2">
                    <div class="flex gap-2">
                      <button @click.prevent="openEdit(s)" class="p-2 bg-indigo-100 text-indigo-700 rounded" aria-label="Edit" title="Edit">
                        <PencilSquareIcon class="h-4 w-4" />
                      </button>
                      <button @click.prevent="confirmDelete(s)" class="p-2 bg-red-100 text-red-700 rounded" aria-label="Delete" title="Delete">
                        <TrashIcon class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="(schedules.data || []).length === 0"><td colspan="5" class="px-3 py-6 text-center text-gray-500">No schedules found.</td></tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile / small screens: stacked cards -->
          <div class="sm:hidden space-y-3">
            <div v-for="s in schedules.data" :key="'card-'+s.id" class="bg-white border rounded-lg p-4">
              <div class="flex justify-between items-center">
                <div>
                  <div class="text-sm text-gray-600">#{{ s.id }}</div>
                  <div class="font-medium">{{ s.schedule_date || '—' }}</div>
                </div>
                <div class="text-sm text-gray-700 text-right">
                  <div>Start: {{ s.time_start || '—' }}</div>
                  <div>End: {{ s.time_end || '—' }}</div>
                </div>
              </div>
              <div class="mt-3 flex gap-2">
                <button @click.prevent="openEdit(s)" class="px-3 py-2 bg-indigo-100 rounded text-sm">Edit</button>
                <button @click.prevent="confirmDelete(s)" class="px-3 py-2 bg-red-100 rounded text-sm">Delete</button>
              </div>
            </div>
            <div v-if="(schedules.data || []).length === 0" class="text-center text-gray-500">No schedules found.</div>
          </div>
        </div>

        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click.prevent="goTo(schedules.prev_page_url)" :disabled="!schedules.prev_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ schedules.current_page }} of {{ schedules.last_page }}</span>
          <button @click.prevent="goTo(schedules.next_page_url)" :disabled="!schedules.next_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3" @click="closeModal">✕</button>
          <h3 class="text-lg font-semibold mb-3">{{ editing ? 'Edit' : 'New' }} Schedule</h3>
          <form @submit.prevent="submit">
            <!-- Day removed: using schedule_date instead -->
            <div class="mb-3">
              <label class="block text-sm font-medium">Date</label>
              <input type="date" v-model="form.schedule_date" class="mt-1 block w-full rounded border-gray-300" />
            </div>
            <div class="mb-3">
              <label class="block text-sm font-medium">Start</label>
              <input type="time" v-model="form.time_start" class="mt-1 block w-full rounded border-gray-300" />
            </div>
            <div class="mb-3">
              <label class="block text-sm font-medium">End</label>
              <input type="time" v-model="form.time_end" class="mt-1 block w-full rounded border-gray-300" />
            </div>
            <div class="flex gap-2">
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
