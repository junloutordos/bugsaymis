<template>
  <AdminLayout :title="'Nomination #' + nomination.id">
    <div class="mx-auto max-w-4xl space-y-6">
      <!-- Back + Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
          <Link :href="route('rewards.nominations.index')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">←</Link>
          <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ nomination.reward_type?.name }}</h1>
            <p class="text-sm text-slate-500">Nomination #{{ nomination.id }}</p>
          </div>
        </div>
        <span :class="[badgeBase, statusBadgeClass(nomination.status), 'capitalize']">{{ nomination.status }}</span>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Info -->
        <div class="space-y-4 lg:col-span-2">
          <!-- Details Card -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100">
              <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nomination Details</h2>
            </div>
            <div class="p-5">
              <dl class="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt class="text-slate-500 text-xs">Nominee</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominee?.name }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Nominated By</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominator?.name }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Period</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.period ?? '—' }}</dd>
                </div>
                <div v-if="nomination.team_name">
                  <dt class="text-slate-500 text-xs">Team</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.team_name }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Category</dt>
                  <dd class="font-medium text-slate-800 capitalize mt-0.5">{{ nomination.reward_type?.category }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Date Submitted</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ formatDate(nomination.created_at) }}</dd>
                </div>
              </dl>
              <div class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Justification</p>
                <p class="mt-1 text-sm text-slate-700 whitespace-pre-wrap">{{ nomination.justification }}</p>
              </div>
            </div>
          </div>

          <!-- Evaluations -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100">
              <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Evaluations</h2>
            </div>
            <div class="p-5">
              <div v-if="nomination.evaluations?.length" class="space-y-3">
                <div v-for="ev in nomination.evaluations" :key="ev.id"
                  class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm">
                  <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-800">{{ ev.evaluator?.name }}</span>
                    <div class="flex items-center gap-2">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700 capitalize">
                        {{ ev.evaluation_stage }}
                      </span>
                      <span class="text-base font-bold text-indigo-700">{{ ev.score }}/100</span>
                    </div>
                  </div>
                  <p v-if="ev.remarks" class="mt-1 text-slate-600">{{ ev.remarks }}</p>
                </div>
              </div>
              <p v-else class="text-sm text-slate-400">No evaluations yet.</p>
            </div>
          </div>

          <!-- Approvals -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100">
              <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Approvals</h2>
            </div>
            <div class="p-5">
              <div v-if="nomination.approvals?.length" class="space-y-3">
                <div v-for="ap in nomination.approvals" :key="ap.id"
                  class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="font-medium text-slate-800">{{ ap.approver?.name }}</p>
                      <p class="text-xs text-slate-500 capitalize">{{ ap.level.replace('_', ' ') }}</p>
                    </div>
                    <span :class="decisionClass(ap.decision)"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize">
                      {{ ap.decision ?? 'Pending' }}
                    </span>
                  </div>
                  <p v-if="ap.remarks" class="mt-1 text-slate-600">{{ ap.remarks }}</p>
                  <p v-if="ap.decided_at" class="mt-0.5 text-xs text-slate-400">
                    {{ formatDate(ap.decided_at) }}
                  </p>
                </div>
              </div>
              <p v-else class="text-sm text-slate-400">No approval decisions yet.</p>
            </div>
          </div>

          <!-- Award -->
          <div v-if="nomination.reward" class="bg-white rounded-xl border border-slate-100 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100">
              <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Award Recorded</h2>
            </div>
            <div class="p-5">
              <dl class="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt class="text-slate-500 text-xs">Award Date</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ formatDate(nomination.reward.award_date) }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Incentive</dt>
                  <dd class="font-medium text-slate-800 capitalize mt-0.5">{{ nomination.reward.incentive_type.replace('_', ' ') }}</dd>
                </div>
                <div v-if="nomination.reward.incentive_value">
                  <dt class="text-slate-500 text-xs">Value</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.reward.incentive_value }}</dd>
                </div>
                <div v-if="nomination.reward.remarks">
                  <dt class="text-slate-500 text-xs">Remarks</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.reward.remarks }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Record Award (if approved and no award yet) -->
          <div v-if="nomination.status === 'approved' && !nomination.reward"
            class="bg-white rounded-xl border border-emerald-200 shadow-sm">
            <div class="px-5 py-4 border-b border-emerald-100">
              <h2 class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Record Award</h2>
            </div>
            <div class="p-5">
              <form @submit.prevent="submitAward" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Award Date</label>
                    <input type="date" v-model="awardForm.award_date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Incentive Type</label>
                    <select v-model="awardForm.incentive_type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                      <option value="cash">Cash</option>
                      <option value="leave_credits">Leave Credits</option>
                      <option value="certificate">Certificate</option>
                      <option value="plaque">Plaque</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Value (optional)</label>
                  <input type="number" v-model="awardForm.incentive_value" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" step="0.01" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                  <textarea v-model="awardForm.remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="2" />
                </div>
                <button type="submit"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                  Record Award
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Sidebar: Activity Log -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm h-fit">
          <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Activity Log</h2>
          </div>
          <div class="p-5">
            <div v-if="nomination.logs?.length" class="space-y-3">
              <div v-for="log in nomination.logs" :key="log.id" class="border-l-2 border-indigo-200 pl-3">
                <p class="text-xs font-semibold text-indigo-700 capitalize">{{ log.action.replace(/_/g, ' ') }}</p>
                <p class="text-xs text-slate-600">{{ log.actor?.name }}</p>
                <p v-if="log.notes" class="text-xs text-slate-500 italic">{{ log.notes }}</p>
                <p class="text-xs text-slate-400">{{ formatDate(log.acted_at) }}</p>
              </div>
            </div>
            <p v-else class="text-xs text-slate-400">No activity yet.</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'

const props = defineProps({ nomination: Object })

const awardForm = useForm({
  award_date: '',
  incentive_type: 'certificate',
  incentive_value: '',
  remarks: '',
})

function submitAward() {
  awardForm.post(route('rewards.awards.store', props.nomination.id))
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}


function decisionClass(d) {
  return {
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-600',
    deferred: 'bg-amber-50 text-amber-700',
  }[d] ?? 'bg-slate-100 text-slate-600'
}
</script>
