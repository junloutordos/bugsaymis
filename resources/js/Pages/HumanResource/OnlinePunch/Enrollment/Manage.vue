<template>
  <Head title="Face Enrollment — HR Review" />
  <AdminLayout title="Face Enrollment — HR Review">
    <div class="space-y-5">

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="filterStatus" @change="load()"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="">All</option>
          </select>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div v-if="!rows.length && !loading" class="py-16 text-center text-slate-400 text-sm">
          No enrollments found.
        </div>

        <div v-else class="divide-y divide-slate-100">
          <div v-for="row in rows" :key="row.id" class="px-5 py-4 flex items-center gap-4">
            <img v-if="row.photo_url" :src="row.photo_url" class="w-16 h-16 rounded-full object-cover border" alt="Enrollment photo" />
            <div class="flex-1">
              <p class="font-medium text-slate-800">{{ row.user?.name }}</p>
              <p class="text-xs text-slate-400">{{ row.user?.position }}</p>
              <p class="text-xs text-slate-400">Submitted {{ fmtDate(row.consent_given_at) }}</p>
            </div>
            <span class="inline-flex items-center gap-1 text-xs font-medium rounded-full px-2 py-0.5" :class="statusPillClass(row.status)">
              {{ row.status }}
            </span>
            <div v-if="row.status === 'pending'" class="flex gap-2">
              <button @click="approve(row)"
                class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                Approve
              </button>
              <button @click="rejectPrompt(row)"
                class="inline-flex items-center gap-1 bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                Reject
              </button>
            </div>
          </div>
        </div>

        <div v-if="meta && meta.last_page > 1" class="px-5 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-500">
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <div class="flex gap-2">
            <button @click="load(meta.current_page - 1)" :disabled="meta.current_page <= 1"
              class="px-3 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors">← Prev</button>
            <button @click="load(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page"
              class="px-3 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors">Next →</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const filterStatus = ref('pending')
const rows    = ref([])
const meta    = ref(null)
const loading = ref(false)

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await axios.get(route('hr.face-enrollment.data'), {
      params: { status: filterStatus.value, page },
    })
    rows.value = data.data
    meta.value = { current_page: data.current_page, last_page: data.last_page }
  } finally {
    loading.value = false
  }
}

async function approve(row) {
  const confirm = await Swal.fire({
    title: `Approve ${row.user?.name}'s face enrollment?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Approve',
  })
  if (!confirm.isConfirmed) return

  await axios.post(route('hr.face-enrollment.approve', { enrollment: row.id }))
  Swal.fire({ icon: 'success', title: 'Approved', timer: 1500, showConfirmButton: false })
  load(meta.value?.current_page ?? 1)
}

async function rejectPrompt(row) {
  const { value: reason } = await Swal.fire({
    title: `Reject ${row.user?.name}'s face enrollment?`,
    input: 'text',
    inputPlaceholder: 'Reason for rejection',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    inputValidator: (val) => !val ? 'A reason is required' : null,
  })
  if (!reason) return

  await axios.post(route('hr.face-enrollment.reject', { enrollment: row.id }), { reason })
  Swal.fire({ icon: 'success', title: 'Rejected', timer: 1500, showConfirmButton: false })
  load(meta.value?.current_page ?? 1)
}

function statusPillClass(status) {
  if (status === 'approved') return 'bg-emerald-100 text-emerald-700'
  if (status === 'pending') return 'bg-amber-100 text-amber-700'
  return 'bg-rose-100 text-rose-700'
}

function fmtDate(val) {
  if (!val) return '—'
  const d = new Date(String(val).replace(' ', 'T'))
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(() => load())
</script>
