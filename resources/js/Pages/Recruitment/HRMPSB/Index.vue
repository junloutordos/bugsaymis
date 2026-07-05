<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'

const props = defineProps({
  members: { type: Array, default: () => [] },
  results: { type: Array, default: () => [] },
  search:  { type: String, default: '' },
})

const page = usePage()

// ── Search users to add ────────────────────────────────────────────────────
const searchQuery = ref(props.search)

const doSearch = () => {
  router.get(route('recruitment.hrmpsb.index'), {
    search: searchQuery.value || undefined,
  }, { preserveState: true, replace: true })
}

// ── Assign ─────────────────────────────────────────────────────────────────
const assignForm = useForm({ user_id: null })

const assign = (user) => {
  assignForm.user_id = user.id
  assignForm.post(route('recruitment.hrmpsb.assign'), {
    onSuccess: () => {
      searchQuery.value = ''
    },
  })
}

// ── Remove ─────────────────────────────────────────────────────────────────
const removeMember = async (user) => {
  const confirmed = await confirmAction({
    title: `Remove ${user.name}?`,
    text:  'They will lose HRMPSB access immediately.',
    confirmText: 'Remove',
    icon: 'warning',
  })
  if (!confirmed) return

  router.delete(route('recruitment.hrmpsb.remove', user.id), {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Member removed.', timer: 1500, showConfirmButton: false }),
  })
}

const successMsg = computed(() => page.props.flash?.success)
</script>

<template>
  <Head title="HRMPSB Members" />
  <AdminLayout title="HRMPSB — Selection Board Members">
    <div class="max-w-4xl mx-auto space-y-6">

      <AppPageHeader title="HRMPSB — Selection Board Members" />

      <!-- Flash -->
      <div v-if="successMsg" class="px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">
        {{ successMsg }}
      </div>

      <!-- Info banner -->
      <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm text-blue-700">
        <strong>HRMPSB</strong> (Human Resource Merit Promotion and Selection Board) members have access to evaluate applicants,
        score criteria, participate in panel interviews, and contribute to ranking deliberations.
        The Administrator can assign any user to the HRMPSB. Members retain their existing system roles.
      </div>

      <!-- Current members card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-700">Current HRMPSB Members</h3>
          <AppBadge color="slate">{{ members.length }} member{{ members.length !== 1 ? 's' : '' }}</AppBadge>
        </div>

        <div class="p-5">
          <div v-if="members.length" class="divide-y divide-slate-100">
            <div v-for="m in members" :key="m.id"
                 class="flex items-center justify-between py-3">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">
                  {{ m.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                  <p class="text-sm font-semibold text-slate-800">{{ m.name }}</p>
                  <p class="text-xs text-slate-400">{{ m.email }} <span v-if="m.badge_id"> · Badge: {{ m.badge_id }}</span></p>
                  <p v-if="m.position" class="text-xs text-slate-500">{{ m.position }}</p>
                </div>
              </div>
              <AppButton size="sm" variant="danger" @click="removeMember(m)">Remove</AppButton>
            </div>
          </div>
          <EmptyState v-else title="No HRMPSB members assigned yet" />
        </div>
      </div>

      <!-- Add new member card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h3 class="text-sm font-semibold text-slate-700">Add Member</h3>
        </div>
        <div class="p-5">
          <div class="flex gap-3">
            <input v-model="searchQuery" @keyup.enter="doSearch"
                   type="text" placeholder="Search by name, email, or badge ID…"
                   class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <AppButton @click="doSearch">Search</AppButton>
          </div>

          <div v-if="results.length" class="mt-4 divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
            <div v-for="u in results" :key="u.id"
                 class="flex items-center justify-between p-3 bg-white hover:bg-slate-50/60">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-slate-100 text-slate-600 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">
                  {{ u.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ u.name }}</p>
                  <p class="text-xs text-slate-400">{{ u.email }}
                    <span v-if="u.badge_id"> · {{ u.badge_id }}</span>
                    <span v-if="u.position"> · {{ u.position }}</span>
                  </p>
                </div>
              </div>
              <AppButton size="sm" :disabled="assignForm.processing" @click="assign(u)">+ Add</AppButton>
            </div>
          </div>

          <EmptyState v-else-if="search && !results.length" class="mt-4" title="No matching users found outside the current HRMPSB membership" />
        </div>
      </div>

      <!-- Access summary card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">HRMPSB Access Summary</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-slate-600">
          <div class="flex items-center gap-2"><span class="text-success-600 font-bold">✓</span> View all job vacancies and applicant profiles</div>
          <div class="flex items-center gap-2"><span class="text-success-600 font-bold">✓</span> Score applicants on evaluation criteria</div>
          <div class="flex items-center gap-2"><span class="text-success-600 font-bold">✓</span> Participate as panel member in interviews</div>
          <div class="flex items-center gap-2"><span class="text-success-600 font-bold">✓</span> View and contribute to ranking computations</div>
          <div class="flex items-center gap-2"><span class="text-danger-500 font-bold">✗</span> Publish or manage job items</div>
          <div class="flex items-center gap-2"><span class="text-danger-500 font-bold">✗</span> Approve placements or access HR records</div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
