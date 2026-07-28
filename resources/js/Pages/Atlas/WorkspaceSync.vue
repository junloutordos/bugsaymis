<script setup>
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { ArrowPathIcon, CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  proposals: Object,
  activeType: String,
  counts: Object,
})

const page = usePage()
const canManage = () => (page.props.auth?.user?.permissions ?? []).includes('atlas.workspace-sync.manage')

const scanning = ref(false)
const actingId = ref(null)

function switchTab(type) {
  router.get(route('atlas.workspace-sync.index'), { type }, { preserveState: true, preserveScroll: true })
}

function runScan() {
  scanning.value = true
  router.post(route('atlas.workspace-sync.scan'), {}, {
    preserveScroll: true,
    onFinish: () => { scanning.value = false },
  })
}

function approve(proposal) {
  actingId.value = proposal.id
  router.post(route('atlas.workspace-sync.approve', proposal.id), {}, {
    preserveScroll: true,
    onFinish: () => { actingId.value = null },
  })
}

function reject(proposal) {
  actingId.value = proposal.id
  router.post(route('atlas.workspace-sync.reject', proposal.id), {}, {
    preserveScroll: true,
    onFinish: () => { actingId.value = null },
  })
}

function confidenceColor(confidence) {
  return confidence === 'high' ? 'green' : 'amber'
}
</script>

<template>
  <Head title="Workspace Sync" />
  <AdminLayout title="Workspace Sync">
    <AppPageHeader
      title="Google Workspace Sync"
      subtitle="Proposed official-email corrections, matched against the live @crc.pshs.edu.ph directory. Nothing is written until you approve it."
    >
      <template #actions>
        <AppButton variant="primary" :loading="scanning" @click="runScan">
          <ArrowPathIcon class="w-4 h-4" />
          Run Scan
        </AppButton>
      </template>
    </AppPageHeader>

    <div class="mb-4 flex gap-2 border-b border-slate-200">
      <button
        type="button"
        :class="[
          'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
          activeType === 'student' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700',
        ]"
        @click="switchTab('student')"
      >
        Students ({{ counts.student }})
      </button>
      <button
        type="button"
        :class="[
          'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
          activeType === 'employee' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700',
        ]"
        @click="switchTab('employee')"
      >
        Employees ({{ counts.employee }})
      </button>
    </div>

    <AppCard>
      <div v-if="proposals.data.length === 0" class="py-12 text-center text-sm text-slate-500">
        No pending proposals for this tab. Run a scan to check for corrections.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
              <th class="px-4 py-2">Name</th>
              <th class="px-4 py-2">Current Email</th>
              <th class="px-4 py-2">Proposed Email</th>
              <th class="px-4 py-2">Org Unit</th>
              <th class="px-4 py-2">Confidence</th>
              <th v-if="canManage()" class="px-4 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="p in proposals.data" :key="p.id">
              <td class="px-4 py-2.5 font-medium text-slate-800">{{ p.subject_name }}</td>
              <td class="px-4 py-2.5 text-slate-500">{{ p.current_email || '—' }}</td>
              <td class="px-4 py-2.5 text-slate-800">{{ p.proposed_email }}</td>
              <td class="px-4 py-2.5 text-slate-500">{{ p.org_unit_path || '—' }}</td>
              <td class="px-4 py-2.5">
                <AppBadge :color="confidenceColor(p.confidence)">{{ p.confidence }}</AppBadge>
              </td>
              <td v-if="canManage()" class="px-4 py-2.5 text-right">
                <div class="flex justify-end gap-2">
                  <AppButton variant="success" size="sm" :loading="actingId === p.id" @click="approve(p)">
                    <CheckIcon class="w-4 h-4" />
                    Approve
                  </AppButton>
                  <AppButton variant="secondary" size="sm" :loading="actingId === p.id" @click="reject(p)">
                    <XMarkIcon class="w-4 h-4" />
                    Reject
                  </AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4">
        <PaginationControl :links="proposals.links" :total="proposals.total" />
      </div>
    </AppCard>
  </AdminLayout>
</template>
