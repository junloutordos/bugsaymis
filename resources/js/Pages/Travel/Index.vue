<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, XMarkIcon, EyeIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  travels: Object,
  filters: Object,
  travelers: Array,
  divisionChiefs: Array,
  currentUser: Object,
})

const search = ref(props.filters?.search ?? '')
const showModal = ref(false)

const form = useForm({
  traveler_id: props.currentUser?.id ?? '',
  division_chief_id: '',
  travel_type: 'oed_initiated',
  funding_source: 'campus_funds',
  fund_cluster: '01101101',
  travel_mode: 'land',
  origin: 'PSHS-CRC',
  destination: '',
  purpose: '',
  start_date: '',
  end_date: '',
  passenger_count: 1,
  requires_cash_advance: false,
  cash_advance_amount: 0,
})

watch(search, (value) => {
  router.get(route('travel.index'), { search: value }, { preserveState: true, replace: true })
})

const canCreate = computed(() => props.currentUser?.permissions?.canCreate)
const rows = computed(() => props.travels?.data ?? [])

const openCreate = () => {
  form.reset()
  form.traveler_id = props.currentUser?.id ?? ''
  form.travel_type = 'oed_initiated'
  form.funding_source = 'campus_funds'
  form.fund_cluster = '01101101'
  form.travel_mode = 'land'
  form.origin = 'PSHS-CRC'
  form.passenger_count = 1
  form.requires_cash_advance = false
  form.cash_advance_amount = 0
  showModal.value = true
}

const submit = () => {
  form.post(route('travel.store'), {
    preserveScroll: true,
    onSuccess: () => { showModal.value = false },
  })
}

const statusClass = (status) => {
  if (['liquidated', 'completed', 'released'].includes(status)) return 'bg-emerald-100 text-emerald-700'
  if (['returned', 'cancelled'].includes(status)) return 'bg-red-100 text-red-700'
  if (status === 'draft') return 'bg-slate-100 text-slate-600'
  return 'bg-amber-100 text-amber-700'
}

const fmtDate = (date) => date ? new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'
const typeLabel = (type) => type === 'campus_initiated' ? 'Campus Initiated' : 'OED Initiated'
</script>

<template>
  <Head title="Travel Requests" />
  <AdminLayout title="Travel Requests">
    <div class="space-y-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">Travel Requests</h1>
          <p class="text-sm text-slate-500">Create and track Travel Orders, Special Orders, IOT, transport, cash advance, ORS, DV, and liquidation.</p>
        </div>
        <button v-if="canCreate" @click="openCreate" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
          <PlusIcon class="h-4 w-4" />
          New Travel
        </button>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="relative max-w-md">
            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input v-model="search" type="search" placeholder="Search control no, traveler, destination, purpose..."
              class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Control</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Traveler</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Travel</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Dates</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mode</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-for="travel in rows" :key="travel.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 text-sm font-mono text-slate-700">{{ travel.control_no }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <div class="font-medium text-slate-900">{{ travel.traveler?.name }}</div>
                  <div class="text-xs text-slate-500">{{ travel.traveler?.position || '-' }}</div>
                </td>
                <td class="max-w-sm px-4 py-3 text-sm text-slate-700">
                  <div class="font-medium text-slate-900">{{ travel.destination }}</div>
                  <div class="truncate text-xs text-slate-500">{{ typeLabel(travel.travel_type) }} · {{ travel.purpose }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ fmtDate(travel.start_date) }} - {{ fmtDate(travel.end_date) }}</td>
                <td class="px-4 py-3 text-sm capitalize text-slate-600">{{ travel.travel_mode }}</td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium', statusClass(travel.status)]">{{ travel.status_label }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <Link :href="route('travel.show', travel.id)" class="inline-flex rounded-lg p-1.5 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600" title="View">
                    <EyeIcon class="h-4 w-4" />
                  </Link>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400">No travel requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="travels?.links?.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
          <p class="text-xs text-slate-500">Showing {{ travels.from ?? 0 }} to {{ travels.to ?? 0 }} of {{ travels.total ?? 0 }}</p>
          <div class="flex flex-wrap gap-1">
            <Link v-for="link in travels.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
              :class="['rounded-md px-3 py-1.5 text-xs', link.active ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200', !link.url ? 'pointer-events-none opacity-50' : '']" />
          </div>
        </div>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <form @submit.prevent="submit" class="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-base font-semibold text-slate-900">New Travel Request</h2>
          <button type="button" @click="showModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>
        <div class="max-h-[68vh] overflow-y-auto px-6 py-5">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Traveler</label>
              <select v-model="form.traveler_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option v-for="user in travelers" :key="user.id" :value="user.id">{{ user.name }}</option>
              </select>
              <p v-if="form.errors.traveler_id" class="mt-1 text-xs text-red-600">{{ form.errors.traveler_id }}</p>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Division Chief</label>
              <select v-model="form.division_chief_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Select approver</option>
                <option v-for="chief in divisionChiefs" :key="chief.id" :value="chief.id">{{ chief.name }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Type</label>
              <select v-model="form.travel_type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="oed_initiated">OED Initiated</option>
                <option value="campus_initiated">Campus Initiated</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Funding Source</label>
              <select v-model="form.funding_source" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="campus_funds">Campus Funds</option>
                <option value="oed_funds">OED Funds</option>
                <option value="external_funds">External Funds</option>
                <option value="personal_no_cost">No Cost to Campus</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Travel Mode</label>
              <select v-model="form.travel_mode" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="land">Land</option>
                <option value="air">Air</option>
                <option value="sea">Sea</option>
                <option value="mixed">Mixed</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Fund Cluster</label>
              <input v-model="form.fund_cluster" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Origin</label>
              <input v-model="form.origin" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Destination</label>
              <input v-model="form.destination" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="form.errors.destination" class="mt-1 text-xs text-red-600">{{ form.errors.destination }}</p>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Start Date</label>
              <input v-model="form.start_date" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">End Date</label>
              <input v-model="form.end_date" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Passengers</label>
              <input v-model="form.passenger_count" type="number" min="1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Cash Advance Amount</label>
              <input v-model="form.cash_advance_amount" type="number" min="0" step="0.01" :disabled="!form.requires_cash_advance" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-100" />
            </div>
            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
              <input v-model="form.requires_cash_advance" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              Requires cash advance
            </label>
            <div class="md:col-span-2">
              <label class="mb-1 block text-xs font-medium text-slate-600">Purpose</label>
              <textarea v-model="form.purpose" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
              <p v-if="form.errors.purpose" class="mt-1 text-xs text-red-600">{{ form.errors.purpose }}</p>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
          <button type="button" @click="showModal = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
          <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Create Travel</button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
