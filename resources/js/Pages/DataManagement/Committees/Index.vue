<script setup>
import { ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppTable from "@/Components/AppTable.vue"
import AppModal from "@/Components/AppModal.vue"
import AppBadge from "@/Components/AppBadge.vue"
import EmptyState from "@/Components/EmptyState.vue"
import {
  EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon,
  ArrowPathIcon, ChevronDownIcon, CheckCircleIcon,
} from "@heroicons/vue/24/outline"
import { useCommittees } from "@/Composables/useCommittees.js"

const props = defineProps({
  committees: Array,
  users: Array,
  terms: Array,
})

const {
  form, showModal, modalMode, selectedCommittee,
  searchQuery, currentPage, totalPages, filteredCommittees,
  applyFilters, clearFilters,
  memberSearch, filteredUsers, filteredSubUsers,
  openModal, closeModal,
  toggleMember, addSubCommittee, removeSubCommittee, toggleSubMember,
  submitCommittee, deleteCommittee,
} = useCommittees(props)

// ── Sync to Term ──────────────────────────────────────────────────────────────
const syncingId   = ref(null)
const syncTermId  = ref(null)
const syncLoading = ref(false)

function openSyncPanel(committee) {
  syncingId.value  = committee.id
  const current    = props.terms?.find(t => t.is_current)
  syncTermId.value = current?.id ?? props.terms?.[0]?.id ?? null
}

function closeSyncPanel() {
  syncingId.value  = null
  syncTermId.value = null
}

function doSync(committee) {
  const term = props.terms?.find(t => t.id === syncTermId.value)
  if (!term) return
  syncLoading.value = true
  router.post(
    route("committees.sync-to-term", committee.id),
    { academic_term_id: term.id, school_year_id: term.school_year_id },
    {
      onFinish: () => { syncLoading.value = false; closeSyncPanel() },
    }
  )
}
</script>

<template>
  <Head title="Committees" />
  <AdminLayout title="Committees">
    <div class="space-y-5">

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="w-4 h-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <AppPageHeader title="Committees" subtitle="Manage committees and their members">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New Committee
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <input v-model="searchQuery" type="text" placeholder="Search committees..."
          @keydown.enter.prevent="applyFilters"
          class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <template #actions>
          <AppButton size="sm" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredCommittees.length" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Structure</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Head</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Members</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </template>

        <template v-for="committee in filteredCommittees" :key="committee.id">
          <!-- Main row -->
          <tr class="hover:bg-indigo-50/40">
            <td class="px-4 py-3 font-medium text-slate-800">{{ committee.name }}</td>
            <td class="px-4 py-3">
              <AppBadge v-if="committee.sub_committees?.length" color="indigo">Main ({{ committee.sub_committees.length }} sub)</AppBadge>
              <AppBadge v-else color="slate">Simple</AppBadge>
            </td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ committee.head?.name ?? "—" }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ committee.members?.length ?? 0 }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1 flex-wrap">
                <AppIconButton label="View" @click="openModal('view', committee)">
                  <EyeIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Edit" @click="openModal('edit', committee)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click="deleteCommittee(committee)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="terms?.length" label="Sync to Faculty Loading term" variant="success" @click="openSyncPanel(committee)">
                  <ArrowPathIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
              <!-- Sync panel -->
              <div v-if="syncingId === committee.id" class="mt-2 flex items-center gap-2 flex-wrap">
                <select v-model="syncTermId"
                  class="text-xs border border-slate-200 rounded-lg px-2 py-1 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                  <option v-for="t in terms" :key="t.id" :value="t.id">
                    {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
                  </option>
                </select>
                <AppButton size="sm" variant="success" :disabled="syncLoading" @click="doSync(committee)">
                  {{ syncLoading ? 'Syncing…' : 'Sync' }}
                </AppButton>
                <button @click="closeSyncPanel()" class="text-xs text-slate-500 hover:text-slate-700 px-1">Cancel</button>
              </div>
            </td>
          </tr>
          <!-- Sub-committee rows (indented) -->
          <tr v-for="sub in committee.sub_committees" :key="'sub-' + sub.id"
            class="bg-indigo-50/30 hover:bg-indigo-50/50">
            <td class="px-4 py-2 pl-10 text-sm text-indigo-700">
              <span class="text-slate-300 mr-1">└</span> {{ sub.name }}
            </td>
            <td class="px-4 py-2">
              <span class="text-xs text-indigo-400 italic">Sub-committee</span>
            </td>
            <td class="px-4 py-2 text-xs text-slate-600">{{ sub.head?.name ?? "—" }}</td>
            <td class="px-4 py-2 text-xs text-slate-600">{{ sub.members?.length ?? 0 }}</td>
            <td class="px-4 py-2"></td>
          </tr>
        </template>

        <template #empty>
          <EmptyState title="No committees found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

      <!-- MODAL -->
      <AppModal
        :show="showModal"
        :title="modalMode === 'create' ? 'New Committee' : modalMode === 'edit' ? 'Edit Committee' : 'View Committee'"
        size="2xl"
        @close="closeModal"
      >
        <!-- VIEW MODE -->
        <div v-if="modalMode === 'view'" class="space-y-4 text-sm text-slate-700">
          <p><span class="font-medium text-slate-600">Name:</span> {{ selectedCommittee?.name }}</p>
          <p>
            <span class="font-medium text-slate-600">Structure:</span>
            <span v-if="selectedCommittee?.sub_committees?.length" class="ml-1 text-indigo-600 font-medium">Main Committee (with sub-committees)</span>
            <span v-else class="ml-1">Simple Committee</span>
          </p>
          <p><span class="font-medium text-slate-600">Head / Chairperson:</span> {{ selectedCommittee?.head?.name ?? "—" }}</p>
          <p><span class="font-medium text-slate-600">Description:</span> {{ selectedCommittee?.description ?? "—" }}</p>

          <template v-if="selectedCommittee?.sub_committees?.length">
            <div>
              <p class="font-medium text-slate-600 mb-2">Sub-committees:</p>
              <div class="space-y-3">
                <div v-for="sub in selectedCommittee.sub_committees" :key="sub.id"
                  class="border border-slate-100 rounded-lg p-3 bg-slate-50">
                  <p class="font-medium text-slate-700">{{ sub.name }}</p>
                  <p class="text-xs text-slate-500">Head: {{ sub.head?.name ?? "—" }}</p>
                  <ul v-if="sub.members?.length" class="mt-1 space-y-0.5">
                    <li v-for="m in sub.members" :key="m.id" class="text-xs text-slate-600">
                      {{ m.name }}<span v-if="m.pivot?.task" class="text-slate-400"> — {{ m.pivot.task }}</span>
                    </li>
                  </ul>
                  <p v-else class="text-xs text-slate-400 mt-1">No members.</p>
                </div>
              </div>
            </div>
          </template>
          <template v-else>
            <div>
              <p class="font-medium text-slate-600 mb-1">Members:</p>
              <ul v-if="selectedCommittee?.members?.length" class="space-y-1">
                <li v-for="m in selectedCommittee.members" :key="m.id" class="text-sm text-slate-700">
                  {{ m.name }}<span v-if="m.pivot?.task" class="text-slate-400"> — {{ m.pivot.task }}</span>
                </li>
              </ul>
              <p v-else class="text-slate-400 text-sm">No members.</p>
            </div>
          </template>
        </div>

        <!-- FORM -->
        <form v-else @submit.prevent="submitCommittee" class="space-y-4">
          <!-- Name -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-danger-600">*</span></label>
            <input v-model="form.name" type="text" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>

          <!-- Structure Toggle -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Committee Structure</label>
            <div class="flex gap-2">
              <button type="button"
                @click="form.has_subcommittees = false"
                :class="!form.has_subcommittees
                  ? 'bg-indigo-600 text-white border-indigo-600'
                  : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                class="flex-1 border rounded-lg px-3 py-2 text-xs font-medium transition-colors">
                Simple Committee
              </button>
              <button type="button"
                @click="form.has_subcommittees = true"
                :class="form.has_subcommittees
                  ? 'bg-indigo-600 text-white border-indigo-600'
                  : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                class="flex-1 border rounded-lg px-3 py-2 text-xs font-medium transition-colors">
                Main Committee (with Sub-committees)
              </button>
            </div>
          </div>

          <!-- Chairperson / Head -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              {{ form.has_subcommittees ? 'Main Chairperson' : 'Committee Head' }}
            </label>
            <select v-model="form.head_id"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="">— None —</option>
              <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}<span v-if="u.position"> ({{ u.position }})</span></option>
            </select>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <textarea v-model="form.description" rows="2"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
          </div>

          <!-- SIMPLE: Members list with search -->
          <div v-if="!form.has_subcommittees">
            <label class="block text-xs font-medium text-slate-600 mb-1">Members</label>
            <input v-model="memberSearch" type="text" placeholder="Search members..."
              class="w-full mb-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <div class="border border-slate-200 rounded-lg p-2 max-h-52 overflow-y-auto space-y-2 text-sm">
              <div v-for="u in filteredUsers" :key="u.id" class="flex items-start gap-2">
                <input type="checkbox" :value="u.id" :checked="form.member_ids.includes(u.id)"
                  @change="toggleMember(u.id)" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <div class="flex-1 min-w-0">
                  <span class="text-slate-700">{{ u.name }}<span v-if="u.position" class="text-slate-400"> ({{ u.position }})</span></span>
                  <input v-if="form.member_ids.includes(u.id)" v-model="form.member_tasks[u.id]"
                    type="text" placeholder="Task / Role..."
                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
              </div>
              <p v-if="filteredUsers.length === 0" class="text-xs text-slate-400 text-center py-2">No users match your search.</p>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ form.member_ids.length }} member(s) selected</p>
          </div>

          <!-- MAIN: Sub-committee builder -->
          <div v-else>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs font-medium text-slate-600">Sub-committees</label>
              <button type="button" @click="addSubCommittee"
                class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                <PlusIcon class="w-3.5 h-3.5" /> Add Sub-committee
              </button>
            </div>

            <p v-if="form.sub_committees.length === 0" class="text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg p-4 text-center">
              No sub-committees yet. Click "Add Sub-committee" to create one.
            </p>

            <div class="space-y-3">
              <div v-for="(sub, idx) in form.sub_committees" :key="idx"
                class="border border-slate-200 rounded-lg p-4 space-y-3 bg-slate-50/50">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Sub-committee {{ idx + 1 }}</span>
                  <button type="button" @click="removeSubCommittee(idx)"
                    class="text-xs text-danger-600 hover:text-danger-700">Remove</button>
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-danger-600">*</span></label>
                  <input v-model="sub.name" type="text" required
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Sub-committee Head</label>
                  <select v-model="sub.head_id"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                    <option value="">— None —</option>
                    <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Members</label>
                  <input v-model="sub.memberSearch" type="text" placeholder="Search members..."
                    class="w-full mb-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  <div class="border border-slate-200 rounded-lg p-2 max-h-36 overflow-y-auto space-y-1.5 text-sm bg-white">
                    <div v-for="u in filteredSubUsers(sub)" :key="u.id" class="flex items-start gap-2">
                      <input type="checkbox" :value="u.id" :checked="sub.member_ids.includes(u.id)"
                        @change="toggleSubMember(idx, u.id)" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                      <div class="flex-1 min-w-0">
                        <span class="text-slate-700 text-xs">{{ u.name }}</span>
                        <input v-if="sub.member_ids.includes(u.id)" v-model="sub.member_tasks[u.id]"
                          type="text" placeholder="Task..."
                          class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                      </div>
                    </div>
                    <p v-if="filteredSubUsers(sub).length === 0" class="text-xs text-slate-400 text-center py-1">No users match.</p>
                  </div>
                  <p class="text-xs text-slate-400 mt-1">{{ sub.member_ids.length }} selected</p>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit">Save</AppButton>
          </div>
        </form>
      </AppModal>

    </div>
  </AdminLayout>
</template>
