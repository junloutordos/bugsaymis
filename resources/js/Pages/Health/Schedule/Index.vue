<script setup>
import { ref } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { confirmDelete as confirmDeleteDialog } from '@/Composables/useConfirm.js'

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

async function confirmDelete(s) {
  const confirmed = await confirmDeleteDialog('This action cannot be undone.')
  if (confirmed) {
    router.delete(route('physician-schedule.destroy', s.id), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Schedule deleted' }).then(() => {
          router.get(route('physician-schedule.index'), { q: q.value }, { replace: true })
        })
      }
    })
  }
}

function search() {
  router.get(route('physician-schedule.index'), { q: q.value }, { replace: true })
}

function goTo(url) {
  if (!url) return; window.location.href = url;
}

function capitalize(v) { if (!v) return ''; return v.charAt(0).toUpperCase() + v.slice(1) }
</script>

<template>
  <Head title="Schedule" />
  <AdminLayout title="Schedule">
    <div class="space-y-5">
      <AppPageHeader title="Physician Schedule" subtitle="Manage physician availability dates and times">
        <template #actions>
          <AppButton @click="openCreate">New Schedule</AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <AppInput v-model="q" @keydown.enter="search" placeholder="Search by day or time" class="w-full sm:w-64" />
        <template #actions>
          <AppButton size="sm" variant="secondary" @click="search">Search</AppButton>
        </template>
      </AppFilterBar>

      <AppTable :is-empty="(schedules.data || []).length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Start</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">End</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="s in schedules.data" :key="s.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.schedule_date || '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.time_start }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.time_end }}</td>
          <td class="px-4 py-3">
            <div class="flex gap-1">
              <AppIconButton label="Edit" @click.prevent="openEdit(s)">
                <PencilSquareIcon class="h-4 w-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click.prevent="confirmDelete(s)">
                <TrashIcon class="h-4 w-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="s in schedules.data" :key="'card-'+s.id" class="p-4">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-xs text-slate-500">#{{ s.id }}</div>
                <div class="font-medium text-slate-800 mt-0.5">{{ s.schedule_date || '—' }}</div>
                <div class="text-sm text-slate-600 mt-1">{{ s.time_start || '—' }} – {{ s.time_end || '—' }}</div>
              </div>
              <div class="flex gap-1">
                <AppIconButton label="Edit" @click.prevent="openEdit(s)">
                  <PencilSquareIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click.prevent="confirmDelete(s)">
                  <TrashIcon class="h-4 w-4" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No schedules found" />
        </template>

        <template #footer>
          <div class="flex items-center justify-between px-4 py-3 text-sm text-slate-600">
            <span>Page {{ schedules.current_page }} of {{ schedules.last_page }}</span>
            <div class="flex gap-2">
              <AppButton size="sm" variant="secondary" :disabled="!schedules.prev_page_url" @click.prevent="goTo(schedules.prev_page_url)">Prev</AppButton>
              <AppButton size="sm" variant="secondary" :disabled="!schedules.next_page_url" @click.prevent="goTo(schedules.next_page_url)">Next</AppButton>
            </div>
          </div>
        </template>
      </AppTable>

      <!-- Modal -->
      <AppModal :show="showModal" :title="`${editing ? 'Edit' : 'New'} Schedule`" @close="closeModal">
        <form @submit.prevent="submit" id="physician-schedule-form" class="space-y-4">
          <AppInput type="date" v-model="form.schedule_date" label="Date" />
          <AppInput type="time" v-model="form.time_start" label="Start Time" />
          <AppInput type="time" v-model="form.time_end" label="End Time" />
        </form>

        <template #footer>
          <AppButton variant="secondary" @click.prevent="closeModal">Cancel</AppButton>
          <AppButton type="submit" form="physician-schedule-form" :loading="form.processing" @click.prevent="submit">{{ editing ? 'Save Changes' : 'Create' }}</AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
