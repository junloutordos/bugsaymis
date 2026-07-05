<template>
  <Head :title="`${employee.name} — SALN History`" />
  <AdminLayout :title="`${employee.name} — SALN History`">
    <div class="max-w-4xl mx-auto space-y-5">

      <!-- Back -->
      <Link :href="route('saln.hr.index')"
        class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" />Back to All Records
      </Link>

      <!-- Employee card -->
      <AppCard>
        <div class="flex items-center gap-4">
          <div class="flex-1">
            <h1 class="text-lg font-semibold text-slate-800">{{ employee.name }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">
              {{ employee.employee_id ?? employee.email }}
              <span v-if="employee.position"> · {{ employee.position }}</span>
            </p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-2xl font-bold text-slate-800">{{ records.length }}</p>
            <p class="text-xs text-slate-400">SALN Records</p>
          </div>
        </div>
      </AppCard>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Empty -->
      <div v-if="records.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <EmptyState
          title="No SALN records found"
          subtitle="This employee has not filed any SALN yet."
          :icon="DocumentTextIcon" />
      </div>

      <!-- Timeline -->
      <div v-else class="space-y-3">
        <div v-for="rec in records" :key="rec.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 flex flex-col sm:flex-row sm:items-center gap-4">

          <!-- Year + status -->
          <div class="shrink-0 text-center sm:text-left sm:w-20">
            <p class="text-3xl font-bold text-slate-800">{{ rec.year }}</p>
            <AppBadge :color="statusBadge(rec.status)" class="mt-1">{{ rec.status_label }}</AppBadge>
          </div>

          <!-- Financials -->
          <div class="flex-1 grid grid-cols-3 gap-3 text-center">
            <div>
              <p class="text-[10px] text-slate-400 uppercase tracking-wide">Assets</p>
              <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ fmtMoney(rec.total_assets) }}</p>
            </div>
            <div>
              <p class="text-[10px] text-slate-400 uppercase tracking-wide">Liabilities</p>
              <p class="text-sm font-semibold text-danger-600 mt-0.5">{{ fmtMoney(rec.total_liabilities) }}</p>
            </div>
            <div>
              <p class="text-[10px] text-slate-400 uppercase tracking-wide">Net Worth</p>
              <p class="text-sm font-bold mt-0.5" :class="rec.net_worth >= 0 ? 'text-success-700' : 'text-danger-600'">
                {{ fmtMoney(rec.net_worth) }}
              </p>
            </div>
          </div>

          <!-- Dates + actions -->
          <div class="shrink-0 flex flex-col gap-1.5 items-end">
            <p v-if="rec.filed_at" class="text-xs text-indigo-500 font-medium">
              ✓ Filed {{ fmtDate(rec.filed_at) }}
            </p>
            <p v-else-if="rec.approved_at" class="text-xs text-success-600 font-medium">
              ✓ Approved {{ fmtDate(rec.approved_at) }}
            </p>
            <p v-else-if="rec.submitted_at" class="text-xs text-blue-600">
              Submitted {{ fmtDate(rec.submitted_at) }}
            </p>
            <div class="flex gap-1.5 mt-1">
              <AppButton as="link" :href="route('saln.show', rec.id)" variant="secondary" size="sm">
                <EyeIcon class="h-3.5 w-3.5" />View
              </AppButton>
              <AppButton v-if="rec.status === 'approved'" variant="success" size="sm" @click="doFile(rec)">
                <ArchiveBoxArrowDownIcon class="h-3.5 w-3.5" />File
              </AppButton>
              <AppButton v-if="rec.status === 'approved' || rec.status === 'filed'"
                as="a" :href="route('saln.pdf', rec.id)" target="_blank" variant="secondary" size="sm">
                <DocumentArrowDownIcon class="h-3.5 w-3.5" />PDF
              </AppButton>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- File confirm -->
    <ConfirmModal
      :show="showFile !== null"
      title="Mark SALN as Filed"
      :message="`Mark the ${showFile?.year} SALN as officially filed?`"
      confirmLabel="Mark as Filed"
      :processing="fileForm.processing"
      :showRemarks="true"
      remarksPlaceholder="Optional filing remarks…"
      @cancel="showFile = null"
      @confirm="(remarks) => submitFile(remarks)" />

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import ConfirmModal from '../Partials/ConfirmModal.vue'
import {
  ChevronLeftIcon, CheckCircleIcon, DocumentTextIcon,
  EyeIcon, DocumentArrowDownIcon, ArchiveBoxArrowDownIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  employee: Object,
  records: Array,
})

const showFile = ref(null)
const fileForm = useForm({ remarks: '' })

function doFile(rec) { showFile.value = rec }

function submitFile(remarks) {
  fileForm.remarks = remarks
  fileForm.post(route('saln.hr.file', showFile.value.id), {
    onSuccess: () => { showFile.value = null },
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

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const fmtMoney = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(v ?? 0)
</script>
