<template>
  <Head :title="`Review SALN — ${saln.user?.name} (${saln.year})`" />
  <AdminLayout :title="`Review SALN — ${saln.year}`">
    <div class="max-w-4xl mx-auto space-y-5">

      <!-- Back -->
      <Link :href="route('saln.review.index')"
        class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" />Back to Review Queue
      </Link>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error"
        class="bg-danger-50 border border-danger-100 text-danger-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.error }}
      </div>

      <!-- Header -->
      <AppPageHeader
        :title="`Review SALN — ${saln.year}`"
        :subtitle="`${saln.user?.name} · ${saln.user?.employee_id ?? saln.user?.email} · As of ${fmtDate(saln.as_of_date)}`">
        <template #actions>
          <AppBadge :color="statusBadge(saln.status)">{{ saln.status_label }}</AppBadge>
          <AppButton v-if="saln.status === 'under_review'" variant="danger" @click="showReturn = true">
            <ArrowUturnLeftIcon class="h-4 w-4" />Return
          </AppButton>
          <AppButton v-if="saln.status === 'under_review'" variant="success" @click="showApprove = true">
            <CheckBadgeIcon class="h-4 w-4" />Approve
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Net Worth Summary Bar -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <p class="text-xs text-slate-400 uppercase tracking-wide">Real Properties</p>
          <p class="text-base font-semibold text-slate-700 mt-1">{{ fmtMoney(saln.total_real_properties) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <p class="text-xs text-slate-400 uppercase tracking-wide">Personal Properties</p>
          <p class="text-base font-semibold text-slate-700 mt-1">{{ fmtMoney(saln.total_personal_properties) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <p class="text-xs text-slate-400 uppercase tracking-wide">Total Liabilities</p>
          <p class="text-base font-semibold text-danger-600 mt-1">{{ fmtMoney(saln.total_liabilities) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <p class="text-xs text-slate-400 uppercase tracking-wide">Net Worth</p>
          <p class="text-base font-bold mt-1" :class="saln.net_worth >= 0 ? 'text-success-700' : 'text-danger-600'">
            {{ fmtMoney(saln.net_worth) }}
          </p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="flex overflow-x-auto border-b border-slate-100 px-2 pt-2 gap-1">
          <button v-for="tab in tabs" :key="tab.id" type="button" @click="activeTab = tab.id"
            class="shrink-0 px-4 py-2 text-sm font-medium rounded-t-lg border border-transparent transition-colors"
            :class="activeTab === tab.id
              ? 'bg-white border-slate-200 border-b-white text-indigo-600 -mb-px'
              : 'text-slate-500 hover:text-slate-700'">
            {{ tab.label }}
            <span v-if="tab.count > 0"
              class="ml-1.5 inline-flex items-center justify-center w-5 h-5 text-[10px] rounded-full"
              :class="activeTab === tab.id ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-500'">
              {{ tab.count }}
            </span>
          </button>
        </div>

        <div class="p-5">

          <!-- Personal Info -->
          <div v-if="activeTab === 'personal'" class="space-y-3">
            <dl class="space-y-3">
              <InfoRow label="Filer" :value="saln.user?.name" />
              <InfoRow label="SALN Year" :value="saln.year" />
              <InfoRow label="As of Date" :value="fmtDate(saln.as_of_date)" />
              <template v-if="saln.spouse_name">
                <div class="border-t border-slate-100 pt-3 mt-3">
                  <p class="text-xs font-semibold text-slate-600 mb-2 uppercase tracking-wide">Spouse</p>
                </div>
                <InfoRow label="Spouse Name" :value="saln.spouse_name" />
                <InfoRow label="Spouse Position" :value="saln.spouse_position" />
                <InfoRow label="Spouse Office" :value="saln.spouse_office" />
                <InfoRow label="Gov't ID" :value="saln.spouse_government_id" />
              </template>
              <template v-else>
                <InfoRow label="Spouse" value="None declared" />
              </template>
            </dl>
          </div>

          <!-- Real Properties -->
          <div v-if="activeTab === 'real'">
            <AppTable :card="false" :is-empty="saln.real_properties.length === 0" :skeleton-cols="4">
              <template #head>
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Location</th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Fair Market Value</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Owner</th>
                </tr>
              </template>
              <tr v-for="p in saln.real_properties" :key="p.id">
                <td class="px-4 py-3 text-slate-700">{{ p.kind }}</td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ p.exact_location }}</td>
                <td class="px-4 py-3 text-right font-medium text-slate-700">{{ fmtMoney(p.current_fair_market_value) }}</td>
                <td class="px-4 py-3 capitalize text-slate-500">{{ p.owner }}</td>
              </tr>
              <template #empty>
                <p class="text-sm text-slate-400">No real properties declared.</p>
              </template>
              <template #footer>
                <div class="px-4 py-2.5 flex items-center justify-end gap-3 text-sm">
                  <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</span>
                  <span class="font-bold text-slate-800">{{ fmtMoney(saln.total_real_properties) }}</span>
                </div>
              </template>
              <template #mobileCard>
                <div v-for="p in saln.real_properties" :key="p.id" class="p-4 space-y-1">
                  <p class="text-sm font-medium text-slate-800">{{ p.kind }}</p>
                  <p class="text-xs text-slate-500">{{ p.exact_location }}</p>
                  <div class="flex items-center justify-between text-xs text-slate-600 pt-1">
                    <span class="capitalize">Owner: {{ p.owner }}</span>
                    <span class="font-semibold text-slate-800">{{ fmtMoney(p.current_fair_market_value) }}</span>
                  </div>
                </div>
              </template>
            </AppTable>
          </div>

          <!-- Personal Properties -->
          <div v-if="activeTab === 'personal_prop'">
            <AppTable :card="false" :is-empty="saln.personal_properties.length === 0" :skeleton-cols="4">
              <template #head>
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Acquisition Cost</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Owner</th>
                </tr>
              </template>
              <tr v-for="p in saln.personal_properties" :key="p.id">
                <td class="px-4 py-3 text-slate-700">{{ p.description }}</td>
                <td class="px-4 py-3 text-slate-500 capitalize text-xs">{{ p.category?.replace(/_/g, ' ') }}</td>
                <td class="px-4 py-3 text-right font-medium text-slate-700">{{ fmtMoney(p.acquisition_cost) }}</td>
                <td class="px-4 py-3 capitalize text-slate-500">{{ p.owner }}</td>
              </tr>
              <template #empty>
                <p class="text-sm text-slate-400">No personal properties declared.</p>
              </template>
              <template #footer>
                <div class="px-4 py-2.5 flex items-center justify-end gap-3 text-sm">
                  <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</span>
                  <span class="font-bold text-slate-800">{{ fmtMoney(saln.total_personal_properties) }}</span>
                </div>
              </template>
              <template #mobileCard>
                <div v-for="p in saln.personal_properties" :key="p.id" class="p-4 space-y-1">
                  <p class="text-sm font-medium text-slate-800">{{ p.description }}</p>
                  <p class="text-xs text-slate-500 capitalize">{{ p.category?.replace(/_/g, ' ') }}</p>
                  <div class="flex items-center justify-between text-xs text-slate-600 pt-1">
                    <span class="capitalize">Owner: {{ p.owner }}</span>
                    <span class="font-semibold text-slate-800">{{ fmtMoney(p.acquisition_cost) }}</span>
                  </div>
                </div>
              </template>
            </AppTable>
          </div>

          <!-- Liabilities -->
          <div v-if="activeTab === 'liabilities'">
            <AppTable :card="false" :is-empty="saln.liabilities.length === 0" :skeleton-cols="3">
              <template #head>
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Nature</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Creditor</th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Outstanding Balance</th>
                </tr>
              </template>
              <tr v-for="l in saln.liabilities" :key="l.id">
                <td class="px-4 py-3 text-slate-700 capitalize">{{ l.nature_label ?? l.nature?.replace(/_/g, ' ') }}</td>
                <td class="px-4 py-3 text-slate-500">{{ l.creditor_name }}</td>
                <td class="px-4 py-3 text-right font-medium text-danger-600">{{ fmtMoney(l.outstanding_balance) }}</td>
              </tr>
              <template #empty>
                <p class="text-sm text-slate-400">No liabilities declared.</p>
              </template>
              <template #footer>
                <div class="px-4 py-2.5 flex items-center justify-end gap-3 text-sm">
                  <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</span>
                  <span class="font-bold text-danger-700">{{ fmtMoney(saln.total_liabilities) }}</span>
                </div>
              </template>
              <template #mobileCard>
                <div v-for="l in saln.liabilities" :key="l.id" class="p-4 space-y-1">
                  <p class="text-sm font-medium text-slate-800 capitalize">{{ l.nature_label ?? l.nature?.replace(/_/g, ' ') }}</p>
                  <p class="text-xs text-slate-500">{{ l.creditor_name }}</p>
                  <p class="text-xs font-semibold text-danger-600 pt-1">{{ fmtMoney(l.outstanding_balance) }}</p>
                </div>
              </template>
            </AppTable>
          </div>

          <!-- Business Interests -->
          <div v-if="activeTab === 'business'">
            <div v-if="saln.business_interests.length === 0" class="py-8 text-center text-sm text-slate-400">
              No business interests declared.
            </div>
            <div v-else class="space-y-3">
              <div v-for="b in saln.business_interests" :key="b.id"
                class="border border-slate-100 rounded-lg p-4 space-y-1.5">
                <p class="font-medium text-slate-800">{{ b.entity_name }}</p>
                <p class="text-xs text-slate-500">{{ b.business_address }}</p>
                <p class="text-xs text-slate-500">{{ b.nature_of_business }}</p>
                <div class="flex gap-4 text-xs text-slate-600 pt-1">
                  <span>Cost: {{ fmtMoney(b.acquisition_cost) }}</span>
                  <span>FMV: {{ fmtMoney(b.present_fair_market_value) }}</span>
                  <span class="capitalize">Owner: {{ b.owner }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Relatives -->
          <div v-if="activeTab === 'relatives'">
            <AppTable :card="false" :is-empty="saln.relatives.length === 0" :skeleton-cols="4">
              <template #head>
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Relationship</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Agency</th>
                </tr>
              </template>
              <tr v-for="r in saln.relatives" :key="r.id">
                <td class="px-4 py-3 font-medium text-slate-700">{{ r.name }}</td>
                <td class="px-4 py-3 text-slate-500">{{ r.relationship }}</td>
                <td class="px-4 py-3 text-slate-500">{{ r.position ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ r.agency_office }}</td>
              </tr>
              <template #empty>
                <p class="text-sm text-slate-400">No relatives in government declared.</p>
              </template>
              <template #mobileCard>
                <div v-for="r in saln.relatives" :key="r.id" class="p-4 space-y-1">
                  <p class="text-sm font-medium text-slate-800">{{ r.name }}</p>
                  <p class="text-xs text-slate-500">{{ r.relationship }}<span v-if="r.position"> · {{ r.position }}</span></p>
                  <p class="text-xs text-slate-400">{{ r.agency_office }}</p>
                </div>
              </template>
            </AppTable>
          </div>

          <!-- Audit Trail -->
          <div v-if="activeTab === 'audit'" class="space-y-3">
            <div v-if="!saln.reviews || saln.reviews.length === 0" class="py-8 text-center text-sm text-slate-400">
              No audit entries yet.
            </div>
            <ol v-else class="relative border-l border-slate-200 ml-2 space-y-5">
              <li v-for="log in saln.reviews" :key="log.id" class="ml-5">
                <div class="absolute -left-2.5 mt-1 flex items-center justify-center w-5 h-5 rounded-full ring-4 ring-white"
                  :class="auditDotColor(log.action)">
                  <span class="text-[8px] text-white font-bold">{{ log.action[0].toUpperCase() }}</span>
                </div>
                <div class="bg-slate-50 rounded-lg px-4 py-3">
                  <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium text-slate-700">{{ log.action_label ?? log.action }}</p>
                    <span class="text-xs text-slate-400">{{ fmtDate(log.created_at) }}</span>
                  </div>
                  <p class="text-xs text-slate-500 mt-0.5">by {{ log.actor?.name ?? 'System' }}</p>
                  <p v-if="log.remarks" class="text-xs text-slate-600 mt-1 bg-white rounded p-2 border border-slate-100">
                    {{ log.remarks }}
                  </p>
                </div>
              </li>
            </ol>
          </div>

        </div>
      </div>

    </div>

    <!-- Approve confirmation -->
    <ConfirmModal
      :show="showApprove"
      title="Approve SALN"
      :message="`Approve the ${saln.year} SALN of ${saln.user?.name}? This will allow HR to mark it as filed.`"
      confirmLabel="Approve"
      :processing="approveForm.processing"
      :showRemarks="true"
      remarksPlaceholder="Optional remarks or observations…"
      @cancel="showApprove = false"
      @confirm="(remarks) => doApprove(remarks)" />

    <!-- Return confirmation -->
    <ConfirmModal
      :show="showReturn"
      title="Return SALN"
      :message="`Return the ${saln.year} SALN of ${saln.user?.name} for corrections?`"
      variant="danger"
      confirmLabel="Return"
      :processing="returnForm.processing"
      :showRemarks="true"
      :remarksRequired="true"
      remarksPlaceholder="Explain what needs to be corrected (required)…"
      @cancel="showReturn = false"
      @confirm="(remarks) => doReturn(remarks)" />

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import ConfirmModal from '../Partials/ConfirmModal.vue'
import InfoRow from '../Partials/InfoRow.vue'
import {
  ChevronLeftIcon, CheckCircleIcon, ExclamationCircleIcon,
  CheckBadgeIcon, ArrowUturnLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ saln: Object })

const activeTab = ref('personal')
const showApprove = ref(false)
const showReturn = ref(false)

const tabs = computed(() => [
  { id: 'personal', label: 'Personal Info', count: 0 },
  { id: 'real', label: 'Real Properties', count: props.saln.real_properties?.length ?? 0 },
  { id: 'personal_prop', label: 'Personal Properties', count: props.saln.personal_properties?.length ?? 0 },
  { id: 'liabilities', label: 'Liabilities', count: props.saln.liabilities?.length ?? 0 },
  { id: 'business', label: 'Business Interests', count: props.saln.business_interests?.length ?? 0 },
  { id: 'relatives', label: 'Relatives in Gov\'t', count: props.saln.relatives?.length ?? 0 },
  { id: 'audit', label: 'Audit Trail', count: props.saln.reviews?.length ?? 0 },
])

const approveForm = useForm({ remarks: '' })
const returnForm = useForm({ remarks: '' })

function doApprove(remarks) {
  approveForm.remarks = remarks
  approveForm.post(route('saln.review.approve', props.saln.id), {
    onSuccess: () => { showApprove.value = false },
  })
}

function doReturn(remarks) {
  returnForm.remarks = remarks
  returnForm.post(route('saln.review.return', props.saln.id), {
    onSuccess: () => { showReturn.value = false },
  })
}

const statusBadge = (s) => ({
  draft:        'slate',
  submitted:    'blue',
  under_review: 'amber',
  approved:     'green',
  returned:     'red',
  filed:        'indigo',
}[s] ?? 'slate')

const auditDotColor = (action) => ({
  created:   'bg-slate-400',
  submitted: 'bg-blue-500',
  reviewed:  'bg-warning-500',
  approved:  'bg-success-500',
  returned:  'bg-danger-500',
  filed:     'bg-indigo-500',
  updated:   'bg-slate-400',
  reopened:  'bg-purple-500',
}[action] ?? 'bg-slate-400')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
