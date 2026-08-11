<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  myNominations: { type: Array, default: () => [] },
  mySubstitutions: { type: Array, default: () => [] },
  forMyApproval: { type: Array, default: () => [] },
})

const tab = ref(props.forMyApproval.length ? 'approval' : 'mine')

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'
}

function approve(substitution) {
  router.post(route('hr.substitutions.approve', substitution.id), {}, { preserveScroll: true })
}

function reject(substitution) {
  const reason = window.prompt('Reason for rejecting this nomination:')
  if (!reason) return
  router.post(route('hr.substitutions.reject', substitution.id), { reason }, { preserveScroll: true })
}

function revoke(substitution) {
  const reason = window.prompt('Reason for revoking this substitution (optional):') || ''
  router.post(route('hr.substitutions.revoke', substitution.id), { reason }, { preserveScroll: true })
}

function actAs(substitution) {
  router.post(route('hr.substitutions.act-as.start', substitution.id))
}
</script>

<template>
  <Head title="Substitutions" />
  <AdminLayout title="Substitutions">
    <div class="max-w-5xl mx-auto p-6 space-y-6">
      <h1 class="text-xl font-semibold text-slate-800">Substitutions</h1>

      <div class="flex gap-2 border-b border-slate-200">
        <button
          v-for="t in [
            { key: 'mine', label: `My Nominations (${myNominations.length})` },
            { key: 'approval', label: `For My Approval (${forMyApproval.length})` },
            { key: 'substituting', label: `My Substitutions (${mySubstitutions.length})` },
          ]"
          :key="t.key"
          @click="tab = t.key"
          :class="[
            'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
            tab === t.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700',
          ]"
        >
          {{ t.label }}
        </button>
      </div>

      <div v-if="tab === 'mine'" class="space-y-3">
        <div v-if="!myNominations.length" class="text-sm text-slate-400 py-8 text-center">No nominations filed yet.</div>
        <div v-for="s in myNominations" :key="s.id" class="rounded-lg border border-slate-200 bg-white p-4 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ s.substitute?.name }}</p>
            <p class="text-xs text-slate-500">{{ fmtDate(s.start_date) }} – {{ fmtDate(s.end_date) }} · Status: {{ s.status.replace('_', ' ') }}</p>
          </div>
        </div>
      </div>

      <div v-else-if="tab === 'approval'" class="space-y-3">
        <div v-if="!forMyApproval.length" class="text-sm text-slate-400 py-8 text-center">Nothing pending your approval.</div>
        <div v-for="s in forMyApproval" :key="s.id" class="rounded-lg border border-slate-200 bg-white p-4 flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ s.original_user?.name }} → {{ s.substitute?.name }}</p>
            <p class="text-xs text-slate-500">{{ fmtDate(s.start_date) }} – {{ fmtDate(s.end_date) }}</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <button @click="approve(s)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Approve</button>
            <button @click="reject(s)" class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-50">Reject</button>
          </div>
        </div>
      </div>

      <div v-else class="space-y-3">
        <div v-if="!mySubstitutions.length" class="text-sm text-slate-400 py-8 text-center">You are not covering for anyone.</div>
        <div v-for="s in mySubstitutions" :key="s.id" class="rounded-lg border border-slate-200 bg-white p-4 flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ s.original_user?.name }}</p>
            <p class="text-xs text-slate-500">{{ fmtDate(s.start_date) }} – {{ fmtDate(s.end_date) }} · Status: {{ s.status }}</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <button v-if="s.can_act_as" @click="actAs(s)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Act as {{ s.original_user?.name }}</button>
            <button v-if="s.status === 'approved'" @click="revoke(s)" class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-50">Revoke</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
