<template>
  <AdminLayout title="My Recognitions">
    <div class="space-y-6">
      <AppPageHeader title="My Recognitions">
        <template #actions>
          <AppButton as="link" :href="route('rewards.nominations.create')">+ Nominate Someone</AppButton>
        </template>
      </AppPageHeader>

      <div v-if="nominations.length" class="space-y-4">
        <div v-for="n in nominations" :key="n.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2">
                <p class="font-semibold text-slate-800">{{ n.reward_type?.name }}</p>
                <AppBadge :color="statusColor(n.status)" class="capitalize">{{ n.status }}</AppBadge>
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
        <EmptyState title="No nominations yet">
          <Link :href="route('rewards.nominations.create')"
            class="mt-3 inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
            Nominate an employee →
          </Link>
        </EmptyState>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({ nominations: Array })
const page = usePage()

function isNominee(n) {
  return n.nominee_id === page.props.auth.user.id
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
</script>
