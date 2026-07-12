<template>
  <Head title="My SALN" />
  <AdminLayout title="My SALN">
    <div class="space-y-5">

      <AppPageHeader
        :title="viewedUser ? viewedUser.name + ' — SALN History' : 'My SALN'"
        subtitle="Statement of Assets, Liabilities and Net Worth"
      >
        <template #actions>
          <AppButton v-if="!viewedUser" as="link" :href="route('saln.create')">
            <PlusIcon class="h-4 w-4" />
            File New SALN
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.info" class="bg-blue-50 border border-blue-200 text-blue-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <InformationCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.info }}
      </div>

      <!-- Empty state -->
      <div v-if="records.length === 0" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <EmptyState title="No SALN records yet" subtitle="File your first SALN to get started." :icon="DocumentTextIcon">
          <AppButton v-if="!viewedUser" as="link" :href="route('saln.create')" class="mt-4">
            <PlusIcon class="h-4 w-4" /> File SALN
          </AppButton>
        </EmptyState>
      </div>

      <!-- Records grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="rec in records" :key="rec.id"
          class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-5 flex flex-col gap-3 hover:shadow-md transition-shadow">

          <!-- Year + status -->
          <div class="flex items-start justify-between">
            <div>
              <p class="text-2xl font-bold text-slate-800">{{ rec.year }}</p>
              <p class="text-xs text-slate-400 mt-0.5">As of {{ fmtDate(rec.as_of_date) }}</p>
            </div>
            <AppBadge :color="statusBadge(rec.status)">{{ rec.status_label }}</AppBadge>
          </div>

          <!-- Financial summary -->
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="bg-slate-50 rounded-lg p-2">
              <p class="text-[10px] text-slate-400 uppercase tracking-wide">Assets</p>
              <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ fmtMoney(rec.total_assets) }}</p>
            </div>
            <div class="bg-slate-50 rounded-lg p-2">
              <p class="text-[10px] text-slate-400 uppercase tracking-wide">Liabilities</p>
              <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ fmtMoney(rec.total_liabilities) }}</p>
            </div>
            <div class="rounded-lg p-2" :class="rec.net_worth >= 0 ? 'bg-emerald-50' : 'bg-red-50'">
              <p class="text-[10px] text-slate-400 uppercase tracking-wide">Net Worth</p>
              <p class="text-sm font-semibold mt-0.5" :class="rec.net_worth >= 0 ? 'text-emerald-700' : 'text-red-600'">
                {{ fmtMoney(rec.net_worth) }}
              </p>
            </div>
          </div>

          <!-- Counts -->
          <div class="flex flex-wrap gap-1.5 text-[11px] text-slate-500">
            <span class="bg-slate-100 rounded px-1.5 py-0.5">{{ rec.real_properties_count }} real prop.</span>
            <span class="bg-slate-100 rounded px-1.5 py-0.5">{{ rec.personal_properties_count }} personal prop.</span>
            <span class="bg-slate-100 rounded px-1.5 py-0.5">{{ rec.liabilities_count }} liabilities</span>
          </div>

          <!-- Timestamps -->
          <p v-if="rec.submitted_at" class="text-xs text-slate-400">
            Submitted {{ fmtDate(rec.submitted_at) }}
          </p>
          <p v-if="rec.filed_at" class="text-xs text-indigo-500 font-medium">
            ✓ Filed {{ fmtDate(rec.filed_at) }}
          </p>

          <!-- Action -->
          <div class="flex gap-2 pt-1">
            <AppButton as="link" :href="route('saln.show', rec.id)" variant="secondary" size="sm" class="flex-1 justify-center">
              {{ rec.is_editable ? 'Edit / View' : 'View' }}
            </AppButton>
            <AppButton v-if="rec.status === 'approved' || rec.status === 'filed'"
              as="a" :href="route('saln.pdf', rec.id)" target="_blank"
              variant="secondary" size="sm" title="Export PDF">
              <DocumentArrowDownIcon class="h-4 w-4" />
            </AppButton>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  PlusIcon, DocumentTextIcon, DocumentArrowDownIcon,
  CheckCircleIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline'

defineProps({ records: Array, viewedUser: Object, filters: Object, canManage: Boolean })

const statusBadge = (s) => ({
  draft:        'slate',
  submitted:    'blue',
  under_review: 'amber',
  approved:     'green',
  returned:     'red',
  filed:        'indigo',
}[s] ?? 'slate')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
