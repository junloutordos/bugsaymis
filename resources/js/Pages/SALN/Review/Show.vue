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
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error"
        class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.error }}
      </div>

      <!-- Header card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <p class="text-2xl font-bold text-slate-800">{{ saln.year }}</p>
              <span :class="statusBadge(saln.status)"
                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold">
                {{ saln.status_label }}
              </span>
            </div>
            <p class="text-sm text-slate-600 font-medium">{{ saln.user?.name }}</p>
            <p class="text-xs text-slate-400">{{ saln.user?.employee_id ?? saln.user?.email }}</p>
            <p class="text-xs text-slate-400 mt-0.5">As of {{ fmtDate(saln.as_of_date) }}</p>
          </div>

          <!-- Action buttons -->
          <div v-if="saln.status === 'under_review'" class="flex gap-2 shrink-0">
            <button type="button" @click="showReturn = true"
              class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
              <ArrowUturnLeftIcon class="h-4 w-4" />Return
            </button>
            <button type="button" @click="showApprove = true"
              class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors">
              <CheckBadgeIcon class="h-4 w-4" />Approve
            </button>
          </div>
        </div>

        <!-- Net worth summary -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 pt-5 border-t border-slate-100">
          <div class="text-center">
            <p class="text-xs text-slate-400 uppercase tracking-wide">Real Properties</p>
            <p class="text-base font-semibold text-slate-700 mt-1">{{ fmtMoney(saln.total_real_properties) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-slate-400 uppercase tracking-wide">Personal Properties</p>
            <p class="text-base font-semibold text-slate-700 mt-1">{{ fmtMoney(saln.total_personal_properties) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-slate-400 uppercase tracking-wide">Total Liabilities</p>
            <p class="text-base font-semibold text-red-600 mt-1">{{ fmtMoney(saln.total_liabilities) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-slate-400 uppercase tracking-wide">Net Worth</p>
            <p class="text-base font-bold mt-1" :class="saln.net_worth >= 0 ? 'text-emerald-700' : 'text-red-600'">
              {{ fmtMoney(saln.net_worth) }}
            </p>
          </div>
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
          <div v-if="activeTab === 'real'" class="space-y-3">
            <div v-if="saln.real_properties.length === 0" class="py-8 text-center text-sm text-slate-400">
              No real properties declared.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Description</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Location</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500">Fair Market Value</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Owner</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="p in saln.real_properties" :key="p.id">
                    <td class="px-4 py-3 text-slate-700">{{ p.kind }}</td>
                    <td class="px-4 py-3 text-slate-500 text-xs">{{ p.exact_location }}</td>
                    <td class="px-4 py-3 text-right font-medium text-slate-700">{{ fmtMoney(p.current_fair_market_value) }}</td>
                    <td class="px-4 py-3 capitalize text-slate-500">{{ p.owner }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-slate-50">
                    <td colspan="2" class="px-4 py-2 text-xs font-semibold text-slate-500 text-right">Total</td>
                    <td class="px-4 py-2 text-right font-bold text-slate-800">{{ fmtMoney(saln.total_real_properties) }}</td>
                    <td />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- Personal Properties -->
          <div v-if="activeTab === 'personal_prop'" class="space-y-3">
            <div v-if="saln.personal_properties.length === 0" class="py-8 text-center text-sm text-slate-400">
              No personal properties declared.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Description</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Category</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500">Acquisition Cost</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Owner</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="p in saln.personal_properties" :key="p.id">
                    <td class="px-4 py-3 text-slate-700">{{ p.description }}</td>
                    <td class="px-4 py-3 text-slate-500 capitalize text-xs">{{ p.category?.replace(/_/g, ' ') }}</td>
                    <td class="px-4 py-3 text-right font-medium text-slate-700">{{ fmtMoney(p.acquisition_cost) }}</td>
                    <td class="px-4 py-3 capitalize text-slate-500">{{ p.owner }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-slate-50">
                    <td colspan="2" class="px-4 py-2 text-xs font-semibold text-slate-500 text-right">Total</td>
                    <td class="px-4 py-2 text-right font-bold text-slate-800">{{ fmtMoney(saln.total_personal_properties) }}</td>
                    <td />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- Liabilities -->
          <div v-if="activeTab === 'liabilities'">
            <div v-if="saln.liabilities.length === 0" class="py-8 text-center text-sm text-slate-400">
              No liabilities declared.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Nature</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Creditor</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500">Outstanding Balance</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="l in saln.liabilities" :key="l.id">
                    <td class="px-4 py-3 text-slate-700 capitalize">{{ l.nature_label ?? l.nature?.replace(/_/g, ' ') }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ l.creditor_name }}</td>
                    <td class="px-4 py-3 text-right font-medium text-red-600">{{ fmtMoney(l.outstanding_balance) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-slate-50">
                    <td colspan="2" class="px-4 py-2 text-xs font-semibold text-slate-500 text-right">Total</td>
                    <td class="px-4 py-2 text-right font-bold text-red-700">{{ fmtMoney(saln.total_liabilities) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
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
            <div v-if="saln.relatives.length === 0" class="py-8 text-center text-sm text-slate-400">
              No relatives in government declared.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Relationship</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Position</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Agency</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="r in saln.relatives" :key="r.id">
                    <td class="px-4 py-3 font-medium text-slate-700">{{ r.name }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ r.relationship }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ r.position ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-500 text-xs">{{ r.agency_office }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
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
  submitted:    'bg-blue-100 text-blue-700',
  under_review: 'bg-amber-100 text-amber-700',
  approved:     'bg-emerald-100 text-emerald-700',
  returned:     'bg-red-100 text-red-600',
}[s] ?? 'bg-slate-100 text-slate-600')

const auditDotColor = (action) => ({
  created:   'bg-slate-400',
  submitted: 'bg-blue-500',
  reviewed:  'bg-amber-500',
  approved:  'bg-emerald-500',
  returned:  'bg-red-500',
  filed:     'bg-indigo-500',
  updated:   'bg-slate-400',
  reopened:  'bg-purple-500',
}[action] ?? 'bg-slate-400')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
