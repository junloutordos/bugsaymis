<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppModal from '@/Components/AppModal.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
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

function statusBadgeColor(status) {
  return {
    cleared: 'green',
    waived: 'blue',
    not_applicable: 'slate',
    hold: 'amber',
    returned: 'red',
    pending: 'slate',
  }[status] ?? 'slate'
}
</script>

<template>
  <Head title="Clearance Signatory Queue" />

  <AdminLayout title="Clearance Signatory Queue">
    <div class="space-y-6">
      <AppPageHeader title="Signatory Queue" subtitle="Student Clearance">
        <template #actions>
          <AppSelect v-model="selectedStatus" @change="filter" :show-blank="false">
            <option value="pending">Pending</option>
            <option value="hold">With accountability</option>
            <option value="returned">Returned</option>
            <option value="cleared">Cleared</option>
            <option value="waived">Waived</option>
            <option value="not_applicable">Not applicable</option>
          </AppSelect>
          <AppButton as="link" variant="secondary" :href="route('student-clearance.index')">Dashboard</AppButton>
        </template>
      </AppPageHeader>

      <AppCard :padded="false">
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
              <p v-if="item.blocker_summary" class="mt-2 text-sm text-warning-700">{{ item.blocker_summary }}</p>
              <p v-else-if="item.accountability" class="mt-2 text-sm text-warning-700">{{ item.accountability }}</p>
              <p v-if="item.remarks" class="mt-1 text-sm text-slate-500">{{ item.remarks }}</p>
            </div>
            <div>
              <AppBadge :color="statusBadgeColor(item.status)">{{ statusLabel(item.status) }}</AppBadge>
              <p class="mt-2 text-xs text-slate-500">{{ item.period_title }}</p>
            </div>
            <div class="flex flex-wrap gap-2 lg:justify-end">
              <AppButton size="sm" variant="success" @click="openAction(item, 'cleared')">
                <CheckIcon class="h-4 w-4" />
                Clear
              </AppButton>
              <AppButton size="sm" variant="warning" @click="openAction(item, 'hold')">
                <ExclamationTriangleIcon class="h-4 w-4" />
                Hold
              </AppButton>
              <AppButton size="sm" variant="secondary" @click="openAction(item, 'returned')">
                <XMarkIcon class="h-4 w-4" />
                Return
              </AppButton>
              <AppButton size="sm" variant="secondary" @click="openAction(item, 'not_applicable')">
                <NoSymbolIcon class="h-4 w-4" />
                N/A
              </AppButton>
            </div>
          </div>
          <div v-if="items.length === 0" class="px-5 py-4">
            <EmptyState title="No clearance items in this queue" />
          </div>
        </div>
      </AppCard>

      <AppModal :show="!!activeItem" :title="statusLabel(form.status)" :subtitle="activeItem ? `${activeItem.requirement_label} / ${activeItem.student_name}` : ''" @close="closeAction">
        <div class="space-y-4">
          <AppTextarea v-model="form.accountability" label="Accountability" :rows="3" />
          <AppTextarea v-model="form.remarks" label="Remarks" :rows="3" />
        </div>

        <template #footer>
          <AppButton variant="secondary" @click="closeAction">Cancel</AppButton>
          <AppButton :loading="form.processing" @click="submitAction">Save</AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
