<script setup>
import { ref, computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppTable from "@/Components/AppTable.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import { PencilSquareIcon, TrashIcon, PlusIcon, ArrowRightIcon } from "@heroicons/vue/24/outline"
import FiscalYearFilter from "@/Components/FiscalYearFilter.vue"
import { useSubmit } from "@/Composables/useSubmit"
import { confirmDelete as confirmDeleteDialog } from "@/Composables/useConfirm.js"

const props = defineProps({
  committees: Array,
  users: Array,
  plans: Array,
  authUser: Object,
  fiscalYears: { type: Array, default: () => [] },
  selectedFiscalYear: { type: [String, Number], default: "" },
  currentFiscalYear: { type: Number, default: null },
})

const { isSubmitting, submit } = useSubmit()

// --- List ---
const searchQuery = ref("")
const currentPage = ref(1)
const perPage = 10

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return props.committees.filter(c => c.name?.toLowerCase().includes(q))
})
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))

// --- Modal ---
const showModal = ref(false)
const modalMode = ref("create")
const memberSearch = ref("")
const planSearch = ref("")

const emptySubCommittee = () => ({ id: null, name: '', head_id: '', member_ids: [], member_tasks: {}, memberSearch: '' })

const emptyForm = () => ({
  id: null,
  name: "",
  head_id: "",
  description: "",
  fiscal_year: props.currentFiscalYear ?? null,
  has_subcommittees: false,
  member_ids: [],
  member_tasks: {},
  plan_ids: [],
  sub_committees: [],
})

const form = ref(emptyForm())

const filteredUsers = computed(() => {
  const q = memberSearch.value.toLowerCase()
  if (!q) return props.users
  return props.users.filter(u => u.name.toLowerCase().includes(q))
})

const filteredSubUsers = (sub) => {
  const q = (sub.memberSearch || '').toLowerCase()
  if (!q) return props.users
  return props.users.filter(u => u.name.toLowerCase().includes(q))
}

const filteredPlans = computed(() => {
  const q = planSearch.value.toLowerCase()
  if (!q) return props.plans
  return props.plans.filter(p => p.success_indicator.toLowerCase().includes(q))
})

const openModal = (mode, committee = null) => {
  modalMode.value = mode
  showModal.value = true
  memberSearch.value = ''
  planSearch.value = ''
  if ((mode === "edit" || mode === "view") && committee) {
    const memberTasks = {}
    committee.members?.forEach(m => { memberTasks[m.id] = m.pivot?.task ?? "" })
    const hasSubs = (committee.sub_committees?.length ?? 0) > 0
    form.value = {
      id: committee.id,
      name: committee.name ?? "",
      head_id: committee.head_id ?? "",
      description: committee.description ?? "",
      fiscal_year: committee.fiscal_year ?? null,
      has_subcommittees: hasSubs,
      member_ids: hasSubs ? [] : (committee.members?.map(m => m.id) ?? []),
      member_tasks: hasSubs ? {} : memberTasks,
      plan_ids: committee.work_distribution_plans?.map(p => p.id) ?? [],
      sub_committees: hasSubs ? committee.sub_committees.map(sub => {
        const subTasks = {}
        sub.members?.forEach(m => { subTasks[m.id] = m.pivot?.task ?? "" })
        return {
          id: sub.id,
          name: sub.name,
          head_id: sub.head_id ?? '',
          member_ids: sub.members?.map(m => m.id) ?? [],
          member_tasks: subTasks,
          memberSearch: '',
        }
      }) : [],
    }
  } else {
    form.value = emptyForm()
  }
}

const closeModal = () => { showModal.value = false }

const toggleMember = (userId) => {
  const idx = form.value.member_ids.indexOf(userId)
  if (idx === -1) {
    form.value.member_ids.push(userId)
  } else {
    form.value.member_ids.splice(idx, 1)
    delete form.value.member_tasks[userId]
  }
}

const toggleSubMember = (subIdx, userId) => {
  const sub = form.value.sub_committees[subIdx]
  const idx = sub.member_ids.indexOf(userId)
  if (idx === -1) {
    sub.member_ids.push(userId)
  } else {
    sub.member_ids.splice(idx, 1)
    delete sub.member_tasks[userId]
  }
}

const togglePlan = (planId) => {
  const idx = form.value.plan_ids.indexOf(planId)
  if (idx === -1) form.value.plan_ids.push(planId)
  else form.value.plan_ids.splice(idx, 1)
}

const addSubCommittee = () => form.value.sub_committees.push(emptySubCommittee())
const removeSubCommittee = (idx) => form.value.sub_committees.splice(idx, 1)

const submitCommittee = () => {
  const payload = {
    name: form.value.name,
    head_id: form.value.head_id || null,
    description: form.value.description,
    fiscal_year: form.value.fiscal_year ? Number(form.value.fiscal_year) : null,
    has_subcommittees: form.value.has_subcommittees,
    plan_ids: form.value.plan_ids,
  }

  if (form.value.has_subcommittees) {
    payload.sub_committees = form.value.sub_committees.map(sub => ({
      id: sub.id || undefined,
      name: sub.name,
      head_id: sub.head_id || null,
      member_ids: sub.member_ids,
      member_tasks: sub.member_tasks,
    }))
  } else {
    payload.member_ids = form.value.member_ids
    payload.member_tasks = form.value.member_tasks
  }

  if (modalMode.value === "create") {
    submit.post(route("pm-committees.store"), payload, {
      onSuccess: () => closeModal(),
    })
  } else {
    submit.put(route("pm-committees.update", form.value.id), payload, {
      onSuccess: () => closeModal(),
    })
  }
}

const deleteCommittee = async (committee) => {
  const ok = await confirmDeleteDialog(`Delete "${committee.name}"? This cannot be undone.`)
  if (!ok) return
  submit.delete(route("pm-committees.destroy", committee.id))
}

const structureBadge = (committee) => committee.sub_committees?.length ? 'indigo' : 'slate'
</script>

<template>
  <Head title="Committees" />
  <AdminLayout title="Committees">
    <div class="space-y-5">

      <div v-if="$page.props.flash?.success" class="rounded-lg bg-success-50 border border-success-100 px-4 py-3 text-sm text-success-700">
        {{ $page.props.flash.success }}
      </div>

      <AppPageHeader title="Committees" subtitle="Manage performance committees and members.">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New Committee
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <AppInput v-model="searchQuery" placeholder="Search committees..." class="w-full sm:w-72" />
        <FiscalYearFilter :fiscal-years="fiscalYears" :selected="selectedFiscalYear" route-name="pm-committees.index" />
      </AppFilterBar>

      <AppTable :is-empty="paginated.length === 0" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Structure</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Head</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Members</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Plans</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </template>

        <template v-for="committee in paginated" :key="committee.id">
          <tr class="hover:bg-slate-50/60">
            <td class="px-4 py-3 font-medium text-slate-800">{{ committee.name }}</td>
            <td class="px-4 py-3">
              <AppBadge :color="structureBadge(committee)">
                {{ committee.sub_committees?.length ? `Main (${committee.sub_committees.length} sub)` : 'Simple' }}
              </AppBadge>
            </td>
            <td class="px-4 py-3 text-slate-700">{{ committee.head?.name ?? "—" }}</td>
            <td class="px-4 py-3 text-slate-700">{{ committee.members?.length ?? 0 }}</td>
            <td class="px-4 py-3 text-slate-700">{{ committee.work_distribution_plans?.length ?? 0 }}</td>
            <td class="px-4 py-3 text-center">
              <div class="flex items-center justify-center gap-1">
                <Link :href="route('pm-committees.show', committee.id)" title="View Performance"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                  <ArrowRightIcon class="w-4 h-4" />
                </Link>
                <AppIconButton label="Edit" @click="openModal('edit', committee)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click="deleteCommittee(committee)"><TrashIcon class="w-4 h-4" /></AppIconButton>
              </div>
            </td>
          </tr>
          <tr v-for="sub in committee.sub_committees" :key="'sub-' + sub.id"
            class="bg-slate-50/40 hover:bg-slate-50">
            <td class="px-4 py-2 pl-10 text-slate-600">
              <span class="text-slate-400 mr-1">└</span>
              {{ sub.name }}
              <span class="ml-1 text-xs italic text-slate-400">Sub-committee</span>
            </td>
            <td class="px-4 py-2"></td>
            <td class="px-4 py-2 text-slate-600 text-xs">{{ sub.head?.name ?? "—" }}</td>
            <td class="px-4 py-2 text-slate-600 text-xs">{{ sub.members?.length ?? 0 }}</td>
            <td class="px-4 py-2 text-slate-400 text-xs">—</td>
            <td class="px-4 py-2 text-center">
              <Link :href="route('pm-committees.show', sub.id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors mx-auto" title="View Performance">
                <ArrowRightIcon class="w-4 h-4" />
              </Link>
            </td>
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
      <AppModal :show="showModal" :title="modalMode === 'create' ? 'New Committee' : 'Edit Committee'" size="2xl" @close="closeModal">
        <form @submit.prevent="submitCommittee" class="space-y-4">
          <AppInput v-model="form.name" label="Name" required />

          <AppSelect v-model="form.head_id" :label="form.has_subcommittees ? 'Main Chairperson' : 'Committee Head'" placeholder="— None —">
            <option v-for="u in props.users" :key="u.id" :value="u.id">
              {{ u.name }}<span v-if="u.position"> ({{ u.position }})</span>
            </option>
          </AppSelect>

          <AppTextarea v-model="form.description" label="Description" :rows="2" />

          <AppSelect v-model="form.fiscal_year" label="Fiscal Year">
            <option :value="null">All years (unscoped)</option>
            <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
          </AppSelect>

          <!-- Structure Toggle -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Committee Structure</label>
            <div class="flex gap-2">
              <AppButton type="button" size="sm" :variant="!form.has_subcommittees ? 'primary' : 'secondary'" @click="form.has_subcommittees = false">Simple Committee</AppButton>
              <AppButton type="button" size="sm" :variant="form.has_subcommittees ? 'primary' : 'secondary'" @click="form.has_subcommittees = true">Main Committee (with Sub-committees)</AppButton>
            </div>
          </div>

          <!-- WDP Plans (always at main committee level) -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tagged Work Distribution Plans</label>
            <AppInput v-model="planSearch" placeholder="Search plans..." class="mb-2" />
            <div class="border border-slate-200 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
              <div v-for="p in filteredPlans" :key="p.id" class="flex items-start gap-2">
                <input type="checkbox" :value="p.id" :checked="form.plan_ids.includes(p.id)"
                  @change="togglePlan(p.id)" class="mt-0.5 rounded border-slate-300" />
                <span class="text-slate-700">{{ p.success_indicator }}
                  <span v-if="p.rated_by" class="text-slate-400 text-xs">({{ p.rated_by }})</span>
                </span>
              </div>
              <p v-if="!filteredPlans.length" class="text-slate-400 text-xs px-1">
                {{ planSearch ? 'No plans match your search.' : 'No plans available.' }}
              </p>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ form.plan_ids.length }} plan(s) selected</p>
          </div>

          <!-- Simple: Members -->
          <div v-if="!form.has_subcommittees">
            <label class="block text-xs font-medium text-slate-600 mb-1">Members</label>
            <AppInput v-model="memberSearch" placeholder="Search members..." class="mb-2" />
            <div class="border border-slate-200 rounded-lg p-2 max-h-52 overflow-y-auto space-y-2 text-sm">
              <div v-for="u in filteredUsers" :key="u.id" class="flex items-start gap-2">
                <input type="checkbox" :value="u.id" :checked="form.member_ids.includes(u.id)"
                  @change="toggleMember(u.id)" class="mt-1 rounded border-slate-300" />
                <div class="flex-1">
                  <span class="text-slate-700">{{ u.name }}<span v-if="u.position" class="text-slate-400"> ({{ u.position }})</span></span>
                  <input v-if="form.member_ids.includes(u.id)" v-model="form.member_tasks[u.id]"
                    type="text" placeholder="Task / Role..."
                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
              </div>
              <p v-if="!filteredUsers.length" class="text-slate-400 text-xs px-1">No members match.</p>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ form.member_ids.length }} member(s) selected</p>
          </div>

          <!-- Main: Sub-committees -->
          <div v-else>
            <div class="flex items-center justify-between mb-2">
              <label class="block text-xs font-medium text-slate-600">Sub-committees</label>
              <button type="button" @click="addSubCommittee"
                class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                <PlusIcon class="w-3 h-3" /> Add Sub-committee
              </button>
            </div>
            <p v-if="form.sub_committees.length === 0" class="text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg p-4 text-center">
              No sub-committees yet. Click "Add Sub-committee" to create one.
            </p>
            <div v-for="(sub, idx) in form.sub_committees" :key="idx"
              class="border border-slate-200 rounded-lg p-4 space-y-3 mb-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-600">Sub-committee {{ idx + 1 }}</span>
                <button type="button" @click="removeSubCommittee(idx)"
                  class="text-xs text-danger-600 hover:text-danger-700">Remove</button>
              </div>
              <AppInput v-model="sub.name" label="Name" required />
              <AppSelect v-model="sub.head_id" label="Head" placeholder="— None —">
                <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </AppSelect>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Members</label>
                <AppInput v-model="sub.memberSearch" placeholder="Search members..." class="mb-2" />
                <div class="border border-slate-100 rounded-lg p-2 max-h-36 overflow-y-auto space-y-1 text-sm">
                  <div v-for="u in filteredSubUsers(sub)" :key="u.id" class="flex items-start gap-2">
                    <input type="checkbox" :checked="sub.member_ids.includes(u.id)"
                      @change="toggleSubMember(idx, u.id)" class="mt-1 rounded border-slate-300" />
                    <div class="flex-1">
                      <span class="text-slate-700 text-xs">{{ u.name }}</span>
                      <input v-if="sub.member_ids.includes(u.id)" v-model="sub.member_tasks[u.id]"
                        type="text" placeholder="Task..."
                        class="mt-1 w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                  </div>
                </div>
                <p class="text-xs text-slate-400 mt-1">{{ sub.member_ids.length }} member(s)</p>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ isSubmitting ? 'Saving…' : 'Save' }}</AppButton>
          </div>
        </form>
      </AppModal>
    </div>
  </AdminLayout>
</template>
