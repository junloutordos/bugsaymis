<template>
  <AdminLayout title="My Recognitions">
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">My Recognitions</h1>
        <Link :href="route('rewards.nominations.create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + Nominate Someone
        </Link>
      </div>

      <div v-if="nominations.length" class="space-y-4">
        <div v-for="n in nominations" :key="n.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2">
                <p class="font-semibold text-slate-800">{{ n.reward_type?.name }}</p>
                <span :class="statusClass(n.status)"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize">
                  {{ n.status }}
                </span>
              </div>
              <p class="mt-0.5 text-sm text-slate-500">
                <span v-if="isNominee(n)">You were nominated</span>
                <span v-else>You nominated <strong class="text-slate-700">{{ n.nominee?.name }}</strong></span>
                <span v-if="n.period"> · {{ n.period }}</span>
              </p>
              <p class="mt-2 text-sm text-slate-600 line-clamp-2">{{ n.justification }}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-xs text-slate-400">{{ formatDate(n.created_at) }}</p>
              <Link :href="route('rewards.nominations.show', n.id)"
                class="mt-1 block text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                View Details →
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="py-16 text-center">
          <p class="text-slate-400 text-sm">No nominations yet.</p>
          <Link :href="route('rewards.nominations.create')"
            class="mt-3 inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
            Nominate an employee →
          </Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ nominations: Array })
const page = usePage()

function isNominee(n) {
  return n.nominee_id === page.props.auth.user.id
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
</script>
