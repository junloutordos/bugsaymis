<template>
  <Head title="Certificate of Appearance" />
  <AdminLayout title="Certificate of Appearance">
    <div class="space-y-5">

      <AppPageHeader title="Certificate of Appearance"
        subtitle="Issue verifiable certificates to external visitors — emailed with a QR-coded PDF">
        <template #actions>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> New Visit
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <AppCard v-for="stat in stats" :key="stat.label" :padded="false">
          <div class="p-4 flex items-center gap-3">
            <div :class="['h-10 w-10 rounded-full flex items-center justify-center shrink-0', stat.iconBg]">
              <component :is="stat.icon" :class="['h-5 w-5', stat.iconColor]" />
            </div>
            <div class="min-w-0">
              <p class="text-2xl font-bold text-slate-800 leading-tight">{{ stat.value }}</p>
              <p class="text-xs text-slate-500 truncate">{{ stat.label }}</p>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative">
          <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="search" type="text" placeholder="Search purpose, visitor, control no..."
            class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-72" />
        </div>
        <select v-model="statusFilter"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="issued">Issued</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppCard :padded="false">
        <AppTable :is-empty="displayed.length === 0" :skeleton-cols="6" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Purpose</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date of Appearance</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Visitors</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Encoded By</th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
            </tr>
          </template>

          <tr v-for="v in displayed" :key="v.id" class="hover:bg-indigo-50/40 align-top">
            <td class="px-4 py-3">
              <p class="text-sm font-medium text-slate-800">{{ v.purpose }}</p>
              <p v-if="v.office_visited" class="text-xs text-slate-500">{{ v.office_visited }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">
              {{ fmtDate(v.date_from) }}<template v-if="v.date_to && v.date_to !== v.date_from"><br />– {{ fmtDate(v.date_to) }}</template>
            </td>
            <td class="px-4 py-3">
              <button type="button" @click="detail = v"
                class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                {{ v.certificates.length }} visitor(s)
              </button>
              <p class="text-xs text-slate-400 truncate max-w-[180px]">
                {{ v.certificates.map(c => c.visitor_name).join(', ') }}
              </p>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="v.status === 'issued' ? 'green' : 'amber'">
                {{ v.status === 'issued' ? 'Issued' : 'Draft' }}
              </AppBadge>
              <p v-if="v.issued_at" class="text-xs text-slate-400 mt-0.5">{{ fmtDate(v.issued_at) }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ v.created_by ?? '—' }}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <AppButton v-if="v.status === 'draft'" size="sm" class="mr-1" @click="confirmIssue(v)">
                <PaperAirplaneIcon class="h-3.5 w-3.5" /> Issue
              </AppButton>
              <button type="button" @click="detail = v"
                class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                <EyeIcon class="h-4 w-4" />
              </button>
              <template v-if="v.status === 'draft'">
                <button type="button" @click="openForm(v)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                  <PencilSquareIcon class="h-4 w-4" />
                </button>
                <button type="button" @click="confirmDelete(v)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-danger-600 hover:bg-danger-50 transition-colors">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </template>
            </td>
          </tr>

          <template #empty>
            <EmptyState title="No visit records"
              subtitle="Encode an external visit to issue Certificates of Appearance."
              :icon="IdentificationIcon" />
          </template>
        </AppTable>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
          <p class="text-xs text-slate-500">
            {{ filtered.length }} record(s) — page {{ currentPage }} of {{ totalPages }}
          </p>
          <div class="flex gap-1">
            <AppButton variant="secondary" size="sm" :disabled="currentPage === 1" @click="currentPage--">Prev</AppButton>
            <AppButton variant="secondary" size="sm" :disabled="currentPage === totalPages" @click="currentPage++">Next</AppButton>
          </div>
        </div>
      </AppCard>

    </div>

    <!-- Details modal -->
    <AppModal :show="!!detail" title="Visit Details" size="2xl"
      body-class="px-6 py-4 space-y-4" @close="detail = null">
      <template v-if="detail">
        <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
          <div class="col-span-2">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Purpose</p>
            <p class="text-slate-800">{{ detail.purpose }}</p>
          </div>
          <div v-if="detail.office_visited">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Office Visited</p>
            <p class="text-slate-800">{{ detail.office_visited }}</p>
          </div>
          <div>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date of Appearance</p>
            <p class="text-slate-800">
              {{ fmtDate(detail.date_from) }}<template v-if="detail.date_to && detail.date_to !== detail.date_from"> – {{ fmtDate(detail.date_to) }}</template>
            </p>
          </div>
          <div v-if="detail.remarks" class="col-span-2">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Remarks</p>
            <p class="text-slate-800">{{ detail.remarks }}</p>
          </div>
        </div>

        <div class="space-y-2">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
            Visitors ({{ detail.certificates.length }})
          </p>
          <div v-for="c in detail.certificates" :key="c.id"
            class="border border-slate-100 rounded-lg px-3 py-2.5 flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800">{{ c.visitor_name }}</p>
              <p class="text-xs text-slate-500">
                <template v-if="c.organization">{{ c.organization }} · </template>{{ c.email }}
              </p>
              <p v-if="c.control_number" class="text-xs font-mono text-indigo-600 mt-0.5">{{ c.control_number }}</p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <AppBadge v-if="detail.status === 'issued'"
                :color="{ sent: 'green', failed: 'red', pending: 'amber' }[c.email_status] ?? 'slate'">
                {{ { sent: 'Emailed', failed: 'Email failed', pending: 'Email pending' }[c.email_status] ?? c.email_status }}
              </AppBadge>
              <template v-if="detail.status === 'issued' && c.control_number">
                <a :href="route('coa.pdf', c.id)" target="_blank"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="View PDF">
                  <DocumentArrowDownIcon class="h-4 w-4" />
                </a>
                <button type="button" @click="resend(c)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Resend email">
                  <ArrowPathIcon class="h-4 w-4" />
                </button>
              </template>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <AppButton variant="ghost" @click="detail = null">Close</AppButton>
      </template>
    </AppModal>

    <!-- Create / Edit Modal -->
    <AppModal :show="modal" :title="(form.id ? 'Edit' : 'New') + ' Visit'" size="2xl"
      body-class="px-6 py-4 space-y-4" @close="modal = false">

      <div v-if="Object.keys(form.errors).length"
        class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-3 py-2 text-xs space-y-0.5">
        <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2">
          <AppInput v-model="form.purpose" label="Purpose of Visit" required
            placeholder="e.g. Benchmarking on laboratory facilities" />
        </div>
        <AppInput v-model="form.office_visited" label="Office / Unit Visited"
          placeholder="e.g. Office of the Campus Director" />
        <div class="grid grid-cols-2 gap-2">
          <AppInput v-model="form.date_from" type="date" label="Date From" required />
          <AppInput v-model="form.date_to" type="date" label="Date To" />
        </div>
      </div>

      <!-- Visitors -->
      <div class="space-y-2">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Visitors — each receives their own certificate</p>
        <div v-for="(row, i) in form.visitors" :key="i"
          class="border border-slate-100 rounded-lg p-3 space-y-2 relative">
          <button type="button" @click="form.visitors.splice(i, 1)" :disabled="form.visitors.length === 1"
            class="absolute top-2 right-2 p-1 rounded-lg text-slate-400 hover:text-danger-600 hover:bg-danger-50 disabled:opacity-30 transition-colors">
            <XMarkIcon class="h-4 w-4" />
          </button>
          <div class="grid grid-cols-2 gap-2 pr-8">
            <AppInput v-model="row.visitor_name" label="Full Name" required placeholder="e.g. Juan A. Dela Cruz" />
            <AppInput v-model="row.email" type="email" label="Email" required placeholder="visitor@example.com" />
            <AppInput v-model="row.organization" label="Organization / Agency" placeholder="e.g. DepEd Caraga" />
            <AppInput v-model="row.position" label="Position" placeholder="e.g. Education Program Supervisor" />
          </div>
        </div>
        <AppButton variant="secondary" size="sm"
          @click="form.visitors.push({ visitor_name: '', organization: '', position: '', email: '' })">
          <PlusIcon class="h-3 w-3" /> Add Visitor
        </AppButton>
      </div>

      <AppTextarea v-model="form.remarks" label="Remarks" :rows="2" />

      <template #footer>
        <AppButton variant="ghost" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Save as Draft' }}</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Swal from 'sweetalert2'
import {
  ArrowPathIcon, CheckCircleIcon, DocumentArrowDownIcon, EnvelopeIcon, EyeIcon,
  IdentificationIcon, MagnifyingGlassIcon, PaperAirplaneIcon, PencilSquareIcon,
  PlusIcon, TrashIcon, UsersIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  visits:      { type: Array, default: () => [] },
  requiresPin: { type: Boolean, default: false },
})

// ── Stats ────────────────────────────────────────────────────────────────────

const stats = computed(() => {
  const certs = props.visits.flatMap(v => v.certificates)
  return [
    { label: 'Total Visits', value: props.visits.length,
      icon: IdentificationIcon, iconBg: 'bg-indigo-50', iconColor: 'text-indigo-600' },
    { label: 'Certificates Issued', value: certs.filter(c => c.control_number).length,
      icon: CheckCircleIcon, iconBg: 'bg-blue-50', iconColor: 'text-blue-600' },
    { label: 'Emails Sent', value: certs.filter(c => c.email_status === 'sent').length,
      icon: EnvelopeIcon, iconBg: 'bg-emerald-50', iconColor: 'text-emerald-600' },
    { label: 'Draft Visits', value: props.visits.filter(v => v.status === 'draft').length,
      icon: UsersIcon, iconBg: 'bg-amber-50', iconColor: 'text-amber-600' },
  ]
})

// ── Filters & pagination ─────────────────────────────────────────────────────

const search       = ref('')
const statusFilter = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.visits.filter(v => {
    if (statusFilter.value && v.status !== statusFilter.value) return false
    if (!q) return true
    const haystack = [
      v.purpose, v.office_visited,
      ...v.certificates.map(c => `${c.visitor_name} ${c.organization ?? ''} ${c.email} ${c.control_number ?? ''}`),
    ].join(' ').toLowerCase()
    return haystack.includes(q)
  })
})

const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})
watch([search, statusFilter], () => { currentPage.value = 1 })

// ── Formatting ───────────────────────────────────────────────────────────────

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

// ── Detail modal ─────────────────────────────────────────────────────────────

const detail = ref(null)

// Keep the open detail modal in sync after resend/issue reloads props
watch(() => props.visits, (visits) => {
  if (detail.value) {
    detail.value = visits.find(v => v.id === detail.value.id) ?? null
  }
})

// ── Form & modal ─────────────────────────────────────────────────────────────

const modal = ref(false)

const form = useForm({
  id: null,
  purpose: '',
  office_visited: '',
  date_from: '',
  date_to: '',
  remarks: '',
  visitors: [{ visitor_name: '', organization: '', position: '', email: '' }],
})

function openForm(v = null) {
  form.reset()
  form.clearErrors()
  if (v && v.id) {
    Object.assign(form, {
      id:             v.id,
      purpose:        v.purpose,
      office_visited: v.office_visited ?? '',
      date_from:      v.date_from ?? '',
      date_to:        v.date_to ?? '',
      remarks:        v.remarks ?? '',
      visitors: v.certificates.map(c => ({
        visitor_name: c.visitor_name,
        organization: c.organization ?? '',
        position:     c.position ?? '',
        email:        c.email,
      })),
    })
  }
  modal.value = true
}

function save() {
  const opts = {
    preserveScroll: true,
    onSuccess: () => { modal.value = false },
  }
  if (form.id) {
    form.put(route('coa.update', form.id), opts)
  } else {
    form.post(route('coa.store'), opts)
  }
}

async function confirmIssue(v) {
  const base = {
    title: 'Issue certificates?',
    html: `<p class="text-sm">${v.certificates.length} certificate(s) will be generated, digitally signed, and emailed to the visitor(s).</p>`,
    showCancelButton: true,
    confirmButtonText: 'Issue & Send',
  }

  const res = props.requiresPin
    ? await Swal.fire({
        ...base,
        input: 'password',
        inputLabel: 'Enter your signing PIN',
        inputAttributes: { maxlength: 12, autocapitalize: 'off', autocorrect: 'off' },
        inputValidator: (val) => (!val ? 'PIN is required to sign the certificates.' : undefined),
      })
    : await Swal.fire({ ...base, icon: 'question' })

  if (!res.isConfirmed) return

  router.post(route('coa.issue', v.id), { pin: res.value ?? null }, {
    preserveScroll: true,
    onError: (errors) => {
      if (errors.pin) {
        Swal.fire({ icon: 'error', title: 'Signing failed', text: errors.pin })
      }
    },
  })
}

function resend(c) {
  Swal.fire({
    icon: 'question',
    title: 'Resend certificate email?',
    text: `${c.control_number} will be re-sent to ${c.email}.`,
    showCancelButton: true,
    confirmButtonText: 'Resend',
  }).then((res) => {
    if (!res.isConfirmed) return
    router.post(route('coa.resend', c.id), {}, { preserveScroll: true })
  })
}

function confirmDelete(v) {
  Swal.fire({
    icon: 'warning',
    title: 'Delete this draft visit?',
    text: `"${v.purpose}" and its visitor list will be removed.`,
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  }).then((res) => {
    if (!res.isConfirmed) return
    router.delete(route('coa.destroy', v.id), { preserveScroll: true })
  })
}
</script>
