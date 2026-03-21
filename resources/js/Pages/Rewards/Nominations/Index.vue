<template>
  <AdminLayout title="Nominations">
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Nominations</h1>
        <Link :href="route('rewards.nominations.create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + Nominate
        </Link>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
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
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Nominee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Award</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Period</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Nominated By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="n in nominations.data" :key="n.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">
                  <p class="font-medium text-slate-800">{{ n.nominee?.name }}</p>
                  <p v-if="n.team_name" class="text-xs text-slate-500">Team: {{ n.team_name }}</p>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ n.reward_type?.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-500">{{ n.period ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ n.nominator?.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span :class="statusClass(n.status)"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize">
                    {{ n.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-400">{{ formatDate(n.created_at) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <div class="flex gap-2">
                    <Link :href="route('rewards.nominations.show', n.id)"
                      class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm">
                      View
                    </Link>
                    <button v-if="n.status === 'pending'" @click="openScreen(n)"
                      class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm">
                      Screen
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!nominations.data.length">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No nominations found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="nominations.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <Link v-for="link in nominations.links" :key="link.label"
            :href="link.url ?? '#'"
            v-html="link.label"
            :class="[
              'rounded px-3 py-1 text-sm',
              link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100',
              !link.url ? 'pointer-events-none opacity-40' : '',
            ]" />
        </div>
      </div>
    </div>

    <!-- Screen Modal -->
    <Teleport to="body">
      <div v-if="screenModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Screen Nomination</h2>
            <button type="button" @click="screenModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <form @submit.prevent="submitScreen">
            <div class="px-6 py-5 space-y-4">
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
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <button type="button" @click="screenModal = false"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Submit
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

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

function statusClass(s) {
  return {
    pending: 'bg-amber-50 text-amber-700',
    screened: 'bg-emerald-50 text-emerald-700',
    evaluated: 'bg-blue-50 text-blue-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-600',
  }[s] ?? 'bg-slate-100 text-slate-600'
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
