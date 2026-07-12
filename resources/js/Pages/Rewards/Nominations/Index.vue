<template>
  <AdminLayout title="Nominations">
    <div class="space-y-6">
      <AppPageHeader title="Nominations">
        <template #actions>
          <AppButton as="link" :href="route('rewards.nominations.create')">+ Nominate</AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="filters.status" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-40">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="screened">Screened</option>
          <option value="evaluated">Evaluated</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <select v-model="filters.reward_type_id" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-48">
          <option value="">All Types</option>
          <option v-for="t in rewardTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <input v-model="filters.period" @change="applyFilters" placeholder="Period (e.g. Q1 2026)"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-44" />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!nominations.data.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Nominee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Award</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Period</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Nominated By</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="n in nominations.data" :key="n.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">
            <p class="font-medium text-slate-800">{{ n.nominee?.name }}</p>
            <p v-if="n.team_name" class="text-xs text-slate-500">Team: {{ n.team_name }}</p>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ n.reward_type?.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-500">{{ n.period ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ n.nominator?.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusColor(n.status)" class="capitalize">{{ n.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-xs text-slate-400">{{ formatDate(n.created_at) }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <div class="flex gap-2">
              <AppButton as="link" size="sm" variant="secondary" :href="route('rewards.nominations.show', n.id)">View</AppButton>
              <AppButton v-if="n.status === 'pending'" size="sm" @click="openScreen(n)">Screen</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="n in nominations.data" :key="n.id" class="p-4 space-y-1">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ n.nominee?.name }}</p>
                <p v-if="n.team_name" class="text-xs text-slate-500">Team: {{ n.team_name }}</p>
              </div>
              <AppBadge :color="statusColor(n.status)" class="capitalize">{{ n.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ n.reward_type?.name }} &middot; {{ n.period ?? '—' }}</p>
            <p class="text-xs text-slate-400">By {{ n.nominator?.name }} &middot; {{ formatDate(n.created_at) }}</p>
            <div class="flex gap-2 pt-1">
              <AppButton as="link" size="sm" variant="secondary" :href="route('rewards.nominations.show', n.id)">View</AppButton>
              <AppButton v-if="n.status === 'pending'" size="sm" @click="openScreen(n)">Screen</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No nominations found" />
        </template>

        <template #footer>
          <PaginationControl :links="nominations.links" :total="nominations.total" />
        </template>
      </AppTable>
    </div>

    <!-- Screen Modal -->
    <AppModal :show="screenModal" title="Screen Nomination" size="md" @close="screenModal = false">
      <form @submit.prevent="submitScreen" id="screen-nomination-form">
        <div class="space-y-4">
          <p class="text-sm text-slate-600">
            Nominee: <strong class="text-slate-800">{{ screenTarget?.nominee?.name }}</strong><br>
            Award: <strong class="text-slate-800">{{ screenTarget?.reward_type?.name }}</strong>
          </p>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Decision</label>
            <select v-model="screenForm.decision" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="pass">Pass (Proceed to Evaluation)</option>
              <option value="fail">Fail (Reject)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="screenForm.remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="3" />
          </div>
        </div>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="screenModal = false">Cancel</AppButton>
        <AppButton type="submit" form="screen-nomination-form">Submit</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  nominations: Object,
  rewardTypes: Array,
  filters: Object,
})

const filters = reactive({ ...props.filters })

function applyFilters() {
  router.get(route('rewards.nominations.index'), filters, { preserveState: true, replace: true })
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function statusColor(s) {
  return {
    pending: 'amber',
    screened: 'green',
    evaluated: 'blue',
    approved: 'green',
    rejected: 'red',
  }[s] ?? 'slate'
}


const screenModal = ref(false)
const screenTarget = ref(null)
const screenForm = useForm({ decision: 'pass', remarks: '' })

function openScreen(n) {
  screenTarget.value = n
  screenForm.reset()
  screenModal.value = true
}

function submitScreen() {
  screenForm.post(route('rewards.nominations.screen', screenTarget.value.id), {
    onSuccess: () => { screenModal.value = false },
  })
}
</script>
