<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CheckIcon, ClipboardDocumentCheckIcon, ExclamationTriangleIcon, NoSymbolIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  filters: Object,
  items: Array,
})

const selectedStatus = ref(props.filters?.status ?? 'pending')
const activeItem = ref(null)
const form = useForm({
  status: 'cleared',
  remarks: '',
  accountability: '',
})

function filter() {
  router.get(route('student-clearance.queue'), { status: selectedStatus.value }, { preserveState: true })
}

function openAction(item, status) {
  activeItem.value = item
  form.status = status
  form.remarks = item.remarks ?? ''
  form.accountability = item.accountability ?? ''
}

function closeAction() {
  activeItem.value = null
  form.reset()
}

function submitAction() {
  form.put(route('student-clearance.items.update', activeItem.value.id), {
    preserveScroll: true,
    onSuccess: closeAction,
  })
}

function statusClass(status) {
  return {
    cleared: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    waived: 'bg-blue-50 text-blue-700 border-blue-200',
    not_applicable: 'bg-slate-50 text-slate-600 border-slate-200',
    hold: 'bg-amber-50 text-amber-700 border-amber-200',
    returned: 'bg-red-50 text-red-700 border-red-200',
    pending: 'bg-slate-50 text-slate-600 border-slate-200',
  }[status] ?? 'bg-slate-50 text-slate-600 border-slate-200'
}

function statusLabel(status) {
  return {
    not_applicable: 'Not applicable',
    hold: 'With accountability',
  }[status] ?? String(status ?? 'pending').replaceAll('_', ' ')
}
</script>

<template>
  <Head title="Clearance Signatory Queue" />

  <AdminLayout title="Clearance Signatory Queue">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Student Clearance</p>
          <h1 class="text-2xl font-semibold text-slate-900">Signatory Queue</h1>
        </div>
        <div class="flex gap-2">
          <select v-model="selectedStatus" @change="filter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="pending">Pending</option>
            <option value="hold">With accountability</option>
            <option value="returned">Returned</option>
            <option value="cleared">Cleared</option>
            <option value="waived">Waived</option>
            <option value="not_applicable">Not applicable</option>
          </select>
          <Link :href="route('student-clearance.index')" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Dashboard
          </Link>
        </div>
      </div>

      <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
          <div v-for="item in items" :key="item.id" class="grid gap-4 px-5 py-4 lg:grid-cols-[1.4fr_1fr_auto] lg:items-center">
            <div>
              <div class="flex items-center gap-2">
                <ClipboardDocumentCheckIcon class="h-5 w-5 text-slate-400" />
                <p class="font-medium text-slate-900">{{ item.requirement_label }}</p>
              </div>
              <p class="mt-1 text-sm text-slate-500">
                {{ item.student_name }} / Grade {{ item.grade_level }} {{ item.section_name }}
              </p>
              <p v-if="item.blocker_summary" class="mt-2 text-sm text-amber-700">{{ item.blocker_summary }}</p>
              <p v-else-if="item.accountability" class="mt-2 text-sm text-amber-700">{{ item.accountability }}</p>
              <p v-if="item.remarks" class="mt-1 text-sm text-slate-500">{{ item.remarks }}</p>
            </div>
            <div>
              <span class="rounded-full border px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(item.status)">
                {{ statusLabel(item.status) }}
              </span>
              <p class="mt-2 text-xs text-slate-500">{{ item.period_title }}</p>
            </div>
            <div class="flex flex-wrap gap-2 lg:justify-end">
              <button @click="openAction(item, 'cleared')" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-700">
                <CheckIcon class="h-4 w-4" />
                Clear
              </button>
              <button @click="openAction(item, 'hold')" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-2 text-xs font-medium text-white hover:bg-amber-700">
                <ExclamationTriangleIcon class="h-4 w-4" />
                Hold
              </button>
              <button @click="openAction(item, 'returned')" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                <XMarkIcon class="h-4 w-4" />
                Return
              </button>
              <button @click="openAction(item, 'not_applicable')" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                <NoSymbolIcon class="h-4 w-4" />
                N/A
              </button>
            </div>
          </div>
          <div v-if="items.length === 0" class="px-5 py-10 text-center text-sm text-slate-500">
            No clearance items in this queue.
          </div>
        </div>
      </section>

      <div v-if="activeItem" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
        <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
          <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">{{ statusLabel(form.status) }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ activeItem.requirement_label }} / {{ activeItem.student_name }}</p>
          </div>
          <div class="space-y-4 p-5">
            <div>
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Accountability</label>
              <textarea v-model="form.accountability" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div>
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</label>
              <textarea v-model="form.remarks" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button @click="closeAction" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button @click="submitAction" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Save</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
