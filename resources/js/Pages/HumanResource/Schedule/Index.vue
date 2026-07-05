<template>
  <Head title="Schedule" />
  <AdminLayout title="Schedule">
    <div class="space-y-5">

      <AppPageHeader title="Schedule">
        <template #actions>
          <AppButton v-if="canCreate" @click="openCreate">
            <PlusIcon class="w-4 h-4" /> New Schedule
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <input v-model="q" @keydown.enter="search" placeholder="Search by time"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
        <template #actions>
          <AppButton size="sm" @click="search">Search</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="(schedules.data || []).length === 0" :skeleton-cols="10">
        <template #head>
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
        </template>

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
              <AppIconButton v-if="isAdmin" label="Edit" @click.prevent="openEdit(s)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="isAdmin" label="Delete" variant="danger" @click.prevent="confirmDelete(s)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="s in schedules.data" :key="s.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">Badge {{ s.badgeNumber }}</p>
                <p class="text-xs text-slate-400">#{{ s.id }}</p>
              </div>
              <div class="flex gap-1">
                <AppIconButton v-if="isAdmin" label="Edit" @click.prevent="openEdit(s)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="isAdmin" label="Delete" variant="danger" @click.prevent="confirmDelete(s)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </div>
            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600">
              <div><dt class="text-slate-400">Mon</dt><dd>{{ displayDay(s.m_timein, s.m_timeout) }}</dd></div>
              <div><dt class="text-slate-400">Tue</dt><dd>{{ displayDay(s.t_timein, s.t_timeout) }}</dd></div>
              <div><dt class="text-slate-400">Wed</dt><dd>{{ displayDay(s.w_timein, s.w_timeout) }}</dd></div>
              <div><dt class="text-slate-400">Thu</dt><dd>{{ displayDay(s.th_timein, s.th_timeout) }}</dd></div>
              <div><dt class="text-slate-400">Fri</dt><dd>{{ displayDay(s.f_timein, s.f_timeout) }}</dd></div>
              <div><dt class="text-slate-400">Sat</dt><dd>{{ displayDay(s.sat_timein, s.sat_timeout) }}</dd></div>
              <div><dt class="text-slate-400">Sun</dt><dd>{{ displayDay(s.sun_timein, s.sun_timeout) }}</dd></div>
            </dl>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No schedules found" />
        </template>
      </AppTable>

      <!-- Modal -->
      <AppModal :show="showModal" :title="`${editing ? 'Edit' : 'New'} Schedule`" size="2xl" @close="closeModal">
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
            <AppButton type="submit" :loading="form.processing">{{ editing ? 'Save' : 'Create' }}</AppButton>
            <AppButton variant="secondary" @click.prevent="closeModal">Cancel</AppButton>
          </div>
        </form>
      </AppModal>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Swal from 'sweetalert2'
import { confirmDelete as confirmDeleteDialog } from '@/Composables/useConfirm.js'
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

async function confirmDelete(s) {
  if (!(await confirmDeleteDialog('This action cannot be undone.'))) return
  router.delete(route('schedules.destroy', s.id), {
    onSuccess: () => { Swal.fire({ icon: 'success', title: 'Schedule deleted' }).then(()=>{ router.get(route('schedules.index'), { q: q.value }, { replace: true }) }) }
  })
}

function search() { router.get(route('schedules.index'), { q: q.value }, { replace: true }) }

function displayDay(start, end) {
  if (!start && !end) return '—'
  return `${start || '—'} ${end ? '– '+end : ''}`
}
</script>
