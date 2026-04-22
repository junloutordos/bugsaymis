<template>
  <Head title="My SALN" />
  <AdminLayout title="My SALN">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">
            {{ viewedUser ? viewedUser.name + ' — SALN History' : 'My SALN' }}
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">Statement of Assets, Liabilities and Net Worth</p>
        </div>
        <Link v-if="!viewedUser" :href="route('saln.create')"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" />
          File New SALN
        </Link>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.info" class="bg-blue-50 border border-blue-200 text-blue-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <InformationCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.info }}
      </div>

      <!-- Empty state -->
      <div v-if="records.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <DocumentTextIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No SALN records yet</p>
        <p class="text-xs text-slate-400 mt-1">File your first SALN to get started.</p>
        <Link v-if="!viewedUser" :href="route('saln.create')"
          class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium">
          <PlusIcon class="h-4 w-4" /> File SALN
        </Link>
      </div>

      <!-- Records grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="rec in records" :key="rec.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 flex flex-col gap-3 hover:shadow-md transition-shadow">

          <!-- Year + status -->
          <div class="flex items-start justify-between">
            <div>
              <p class="text-2xl font-bold text-slate-800">{{ rec.year }}</p>
              <p class="text-xs text-slate-400 mt-0.5">As of {{ fmtDate(rec.as_of_date) }}</p>
            </div>
            <span :class="statusBadge(rec.status)" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold">
              {{ rec.status_label }}
            </span>
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
            <Link :href="route('saln.show', rec.id)"
              class="flex-1 text-center py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
              {{ rec.is_editable ? 'Edit / View' : 'View' }}
            </Link>
            <a v-if="rec.status === 'approved' || rec.status === 'filed'"
              :href="route('saln.pdf', rec.id)"
              target="_blank"
              class="px-3 py-2 text-sm text-slate-500 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
              title="Export PDF">
              <DocumentArrowDownIcon class="h-4 w-4" />
            </a>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  PlusIcon, DocumentTextIcon, DocumentArrowDownIcon,
  CheckCircleIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline'

defineProps({ records: Array, viewedUser: Object, filters: Object, canManage: Boolean })

const statusBadge = (s) => ({
  draft:        'bg-slate-100 text-slate-600',
  submitted:    'bg-blue-100 text-blue-700',
  under_review: 'bg-amber-100 text-amber-700',
  approved:     'bg-emerald-100 text-emerald-700',
  returned:     'bg-red-100 text-red-600',
  filed:        'bg-indigo-100 text-indigo-700',
}[s] ?? 'bg-slate-100 text-slate-600')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
