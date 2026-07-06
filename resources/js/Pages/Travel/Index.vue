<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, EyeIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

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

const statusBadgeColor = (status) => {
  if (['liquidated', 'completed', 'released'].includes(status)) return 'green'
  if (['returned', 'cancelled'].includes(status)) return 'red'
  if (status === 'draft') return 'slate'
  return 'amber'
}

const fmtDate = (date) => date ? new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'
const typeLabel = (type) => type === 'campus_initiated' ? 'Campus Initiated' : 'OED Initiated'
</script>

<template>
  <Head title="Travel Requests" />
  <AdminLayout title="Travel Requests">
    <div class="space-y-5">
      <AppPageHeader title="Travel Requests" subtitle="Create and track Travel Orders, Special Orders, IOT, transport, cash advance, ORS, DV, and liquidation.">
        <template #actions>
          <AppButton v-if="canCreate" @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Travel
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <div class="relative w-full sm:w-96">
          <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" type="search" placeholder="Search control no, traveler, destination, purpose..."
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </AppFilterBar>

      <AppTable :is-empty="!rows.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Control</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Traveler</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Travel</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Dates</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mode</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

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
            <AppBadge :color="statusBadgeColor(travel.status)">{{ travel.status_label }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-right">
            <Link :href="route('travel.show', travel.id)" class="inline-flex rounded-lg p-1.5 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600" title="View">
              <EyeIcon class="h-4 w-4" />
            </Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="travel in rows" :key="travel.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-mono text-sm font-medium text-slate-800">{{ travel.control_no }}</p>
                <p class="text-sm text-slate-700">{{ travel.traveler?.name }}</p>
                <p class="text-xs text-slate-500">{{ travel.destination }} &middot; {{ typeLabel(travel.travel_type) }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(travel.status)">{{ travel.status_label }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ fmtDate(travel.start_date) }} - {{ fmtDate(travel.end_date) }}</span>
              <span class="capitalize">{{ travel.travel_mode }}</span>
            </div>
            <div class="pt-1">
              <Link :href="route('travel.show', travel.id)" class="text-xs font-medium text-slate-500 hover:text-indigo-600">View</Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No travel requests found." />
        </template>

        <template #footer>
          <PaginationControl :links="travels.links" :current-page="travels.current_page" :total-pages="travels.last_page" :total="travels.total" />
        </template>
      </AppTable>
    </div>

    <AppModal :show="showModal" title="New Travel Request" size="3xl" @close="showModal = false">
      <form @submit.prevent="submit" class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Traveler</label>
          <select v-model="form.traveler_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="user in travelers" :key="user.id" :value="user.id">{{ user.name }}</option>
          </select>
          <p v-if="form.errors.traveler_id" class="mt-1 text-xs text-danger-600">{{ form.errors.traveler_id }}</p>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Division Chief</label>
          <select v-model="form.division_chief_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">Select approver</option>
            <option v-for="chief in divisionChiefs" :key="chief.id" :value="chief.id">{{ chief.name }}</option>
          </select>
        </div>
        <AppSelect v-model="form.travel_type" label="Type" :show-blank="false">
          <option value="oed_initiated">OED Initiated</option>
          <option value="campus_initiated">Campus Initiated</option>
        </AppSelect>
        <AppSelect v-model="form.funding_source" label="Funding Source" :show-blank="false">
          <option value="campus_funds">Campus Funds</option>
          <option value="oed_funds">OED Funds</option>
          <option value="external_funds">External Funds</option>
          <option value="personal_no_cost">No Cost to Campus</option>
        </AppSelect>
        <AppSelect v-model="form.travel_mode" label="Travel Mode" :show-blank="false">
          <option value="land">Land</option>
          <option value="air">Air</option>
          <option value="sea">Sea</option>
          <option value="mixed">Mixed</option>
        </AppSelect>
        <AppInput v-model="form.fund_cluster" label="Fund Cluster" />
        <AppInput v-model="form.origin" label="Origin" />
        <AppInput v-model="form.destination" label="Destination" :error="form.errors.destination" />
        <AppInput v-model="form.start_date" type="date" label="Start Date" />
        <AppInput v-model="form.end_date" type="date" label="End Date" />
        <AppInput v-model="form.passenger_count" type="number" min="1" label="Passengers" />
        <AppInput v-model="form.cash_advance_amount" type="number" min="0" step="0.01" :disabled="!form.requires_cash_advance" label="Cash Advance Amount" />
        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
          <input v-model="form.requires_cash_advance" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
          Requires cash advance
        </label>
        <div class="md:col-span-2">
          <AppTextarea v-model="form.purpose" :rows="4" label="Purpose" :error="form.errors.purpose" />
        </div>
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" :disabled="form.processing" @click="submit">Create Travel</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
