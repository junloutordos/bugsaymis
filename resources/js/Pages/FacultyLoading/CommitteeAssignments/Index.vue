<template>
  <Head title="Committee Assignments" />
  <AdminLayout title="Committee Assignments">
    <div class="space-y-5">

      <AppPageHeader title="Committee Assignments" subtitle="Per BOT Resolution 2025-05-80: every faculty must have ≥1 active committee per term">
        <template #actions>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> Assign Committee
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Filters + Search -->
      <AppFilterBar>
        <div class="w-56">
          <AppInput v-model="search" type="text" placeholder="Search faculty or committee…" />
        </div>
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <select v-model="filters.faculty_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All Faculty</option>
          <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="displayed.length === 0" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Faculty</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Committee</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Role</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Units</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="a in displayed" :key="a.id" class="hover:bg-slate-50/50">
          <td class="px-4 py-3 font-medium text-slate-800">{{ a.faculty?.name ?? '—' }}</td>
          <td class="px-4 py-3">
            <p v-if="getParentName(a)" class="text-xs text-indigo-500 font-medium">
              {{ getParentName(a) }} ›
            </p>
            <p class="text-slate-800">{{ a.committee_name }}</p>
            <p v-if="a.committee?.code" class="text-xs text-slate-400 font-mono">{{ a.committee.code }}</p>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="roleBadge(a.role)">
              <StarIcon v-if="a.is_chairperson" class="h-3 w-3" />
              {{ roleLabel(a.role) }}
            </AppBadge>
          </td>
          <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ a.load_units }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadge(a.status)">{{ a.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-1">
              <AppButton v-if="a.committee_id" as="link" variant="ghost" size="sm" title="View detail"
                :href="route('faculty-loading.committee-assignments.show', a.committee_id)">
                <ArrowRightIcon class="h-4 w-4" />
              </AppButton>
              <AppIconButton label="Edit" @click="openForm(a)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton label="Remove" variant="danger" @click="remove(a)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="a in displayed" :key="a.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ a.faculty?.name ?? '—' }}</p>
                <p v-if="getParentName(a)" class="text-xs text-indigo-500 font-medium">{{ getParentName(a) }} ›</p>
                <p class="text-sm text-slate-700">{{ a.committee_name }}</p>
              </div>
              <AppBadge :color="statusBadge(a.status)">{{ a.status }}</AppBadge>
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="roleBadge(a.role)">
                <StarIcon v-if="a.is_chairperson" class="h-3 w-3" />
                {{ roleLabel(a.role) }}
              </AppBadge>
              <span class="text-xs text-slate-500">{{ a.load_units }} unit(s)</span>
            </div>
            <div class="flex items-center gap-1 pt-1">
              <AppButton v-if="a.committee_id" as="link" size="sm"
                :href="route('faculty-loading.committee-assignments.show', a.committee_id)">View</AppButton>
              <AppIconButton label="Edit" @click="openForm(a)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton label="Remove" variant="danger" @click="remove(a)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No committee assignments found" :icon="UserGroupIcon" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="page"
            :total-pages="totalPages"
            :total="filtered.length"
            @prev="page--"
            @next="page++"
            @page="page = $event"
          />
        </template>
      </AppTable>

    </div>

    <!-- Create / Edit Modal -->
    <AppModal :show="modal" :title="`${form.id ? 'Edit' : 'Assign'} Committee`" size="lg" @close="modal = false">
      <div class="grid grid-cols-2 gap-3">
        <!-- Faculty + term (create only) -->
        <template v-if="!form.id">
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty *</label>
            <select v-model="form.user_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select faculty...</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term *</label>
            <select v-model="form.academic_term_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select term...</option>
              <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year *</label>
            <select v-model="form.school_year_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select SY...</option>
              <option v-for="t in terms" :key="'sy-' + t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>
        </template>

        <!-- Level 1: Top-level committee picker -->
        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Committee</label>
          <select v-model="selectedParentId" @change="onParentCommitteeChange"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">— Custom / not in catalog —</option>
            <option v-for="c in committees" :key="c.id" :value="c.id">
              {{ c.name }}{{ c.sub_committees?.length ? ' (Main)' : '' }}{{ c.code ? ' (' + c.code + ')' : '' }}
            </option>
          </select>
        </div>

        <!-- Level 2: Sub-committee picker (only when parent has sub-committees) -->
        <div v-if="selectedParent?.sub_committees?.length" class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Sub-committee *</label>
          <select v-model="form.committee_id" @change="onSubCommitteeChange"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">— Select sub-committee —</option>
            <option v-for="s in selectedParent.sub_committees" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>

        <div class="col-span-2">
          <AppInput v-model="form.committee_name" label="Committee Name" required />
        </div>

        <AppSelect :model-value="form.role" label="Role" required :show-blank="false"
          @update:model-value="v => { form.role = v; onRoleChange() }">
          <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
        </AppSelect>

        <AppInput v-model.number="form.load_units" type="number" step="0.25" min="0" max="5" label="Load Units" required />

        <div v-if="form.id" class="col-span-2">
          <AppSelect v-model="form.status" label="Status" :show-blank="false">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </AppSelect>
        </div>

        <div class="col-span-2">
          <AppTextarea v-model="form.remarks" label="Remarks" :rows="2" />
        </div>

        <!-- Tagged WDP Plans with search -->
        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Tagged Work Distribution Plans</label>
          <AppInput v-model="planSearch" type="text" placeholder="Search plans..." />
          <div class="mt-2 border border-slate-200 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
            <label v-for="p in filteredPlans" :key="p.id" class="flex items-start gap-2 cursor-pointer hover:bg-slate-50 px-1 py-0.5 rounded">
              <input type="checkbox" :checked="form.plan_ids.includes(p.id)" @change="togglePlan(p.id)"
                class="mt-0.5 rounded border-slate-300 text-indigo-600" />
              <span class="text-slate-700 leading-snug">{{ p.success_indicator }}
                <span v-if="p.rated_by" class="text-slate-400 text-xs">({{ p.rated_by }})</span>
              </span>
            </label>
            <p v-if="!filteredPlans.length" class="text-slate-400 text-xs px-1">
              {{ planSearch ? 'No plans match your search.' : 'No plans available.' }}
            </p>
          </div>
          <p class="text-xs text-slate-400 mt-1">{{ form.plan_ids.length }} plan(s) selected</p>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Save' }}</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  ArrowRightIcon, CheckCircleIcon,
  PencilIcon, PlusIcon, StarIcon, TrashIcon, UserGroupIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  assignments: { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  committees:  { type: Array,  default: () => [] },
  plans:       { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
})

const PER_PAGE = 15
const roles = [
  { value: 'member',      label: 'Member' },
  { value: 'secretary',   label: 'Secretary' },
  { value: 'co_chair',    label: 'Co-Chairperson' },
  { value: 'chairperson', label: 'Chairperson' },
]

// ── Filters + search + pagination ─────────────────────────────────────────
const search = ref('')
const page   = ref(1)

const filters = reactive({
  term_id:    props.filters.term_id    ?? props.currentTerm?.id ?? null,
  faculty_id: props.filters.faculty_id ?? null,
})

watch(search, () => { page.value = 1 })

function applyFilters() {
  router.get(route('faculty-loading.committee-assignments.index'), filters, { preserveState: true })
}

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return props.assignments
  return props.assignments.filter(a =>
    a.faculty?.name?.toLowerCase().includes(q) ||
    a.committee_name?.toLowerCase().includes(q)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const s = (page.value - 1) * PER_PAGE
  return filtered.value.slice(s, s + PER_PAGE)
})

// ── Parent name helper for hierarchy display ───────────────────────────────
function getParentName(assignment) {
  const parentId = assignment.committee?.parent_committee_id
  if (!parentId) return null
  return props.committees.find(c => c.id === parentId)?.name ?? null
}

// ── WDP plan search ────────────────────────────────────────────────────────
const planSearch = ref('')
const filteredPlans = computed(() => {
  if (!planSearch.value) return props.plans
  const q = planSearch.value.toLowerCase()
  return props.plans.filter(p => p.success_indicator.toLowerCase().includes(q))
})

// ── Committee cascading picker ─────────────────────────────────────────────
const selectedParentId = ref(null)
const selectedParent = computed(() => props.committees.find(c => c.id === selectedParentId.value) ?? null)

function findCommitteeById(id) {
  if (!id) return null
  // Top-level committees
  const top = props.committees.find(c => c.id === id)
  if (top) return top
  // Sub-committees
  for (const parent of props.committees) {
    const sub = parent.sub_committees?.find(s => s.id === id)
    if (sub) return sub
  }
  return null
}

// ── Modal form ─────────────────────────────────────────────────────────────
const modal = ref(false)
const form  = useForm({
  id: null, user_id: null, school_year_id: null, academic_term_id: null,
  committee_id: null, committee_name: '', role: 'member',
  load_units: 0.5, status: 'active', remarks: '', plan_ids: [],
})

function openForm(a = null) {
  planSearch.value = ''
  if (a) {
    const parentId = a.committee?.parent_committee_id ?? a.committee?.id ?? null
    selectedParentId.value = parentId

    const committee = findCommitteeById(a.committee_id)
    Object.assign(form, {
      id: a.id, user_id: null, school_year_id: null, academic_term_id: null,
      committee_id: a.committee_id, committee_name: a.committee_name,
      role: a.role, load_units: a.load_units, status: a.status,
      remarks: a.remarks ?? '', plan_ids: committee?.plan_ids ? [...committee.plan_ids] : [],
    })
  } else {
    selectedParentId.value = null
    form.reset()
    form.id = null
    form.role = 'member'
    form.load_units = 0.5
    form.status = 'active'
    form.plan_ids = []
    form.academic_term_id = filters.term_id ?? null
  }
  modal.value = true
}

function onParentCommitteeChange() {
  const parent = selectedParent.value
  if (!parent) {
    form.committee_id   = null
    form.committee_name = ''
    form.plan_ids       = []
    return
  }
  if (!parent.sub_committees?.length) {
    // Simple committee — assign directly
    form.committee_id   = parent.id
    form.committee_name = parent.name
    form.plan_ids       = [...(parent.plan_ids ?? [])]
    onRoleChange()
  } else {
    // Main committee — reset until sub-committee is selected
    form.committee_id   = null
    form.committee_name = ''
    form.plan_ids       = []
  }
}

function onSubCommitteeChange() {
  const sub = selectedParent.value?.sub_committees?.find(s => s.id === form.committee_id)
  if (sub) {
    form.committee_name = sub.name
    form.plan_ids       = []
    onRoleChange()
  }
}

function onRoleChange() {
  const c = findCommitteeById(form.committee_id)
  if (!c) return
  const isChair = ['chairperson', 'co_chair'].includes(form.role)
  form.load_units = isChair ? c.chairperson_load_units : c.member_load_units
}

function togglePlan(id) {
  const idx = form.plan_ids.indexOf(id)
  if (idx === -1) form.plan_ids.push(id)
  else form.plan_ids.splice(idx, 1)
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.committee-assignments.update', form.id), {
      onSuccess: () => { modal.value = false },
    })
  } else {
    form.post(route('faculty-loading.committee-assignments.store'), {
      onSuccess: () => { modal.value = false },
    })
  }
}

async function remove(a) {
  if (! await confirmDelete(`Remove "${a.committee_name}" assignment for ${a.faculty?.name}?`)) return
  useForm({}).delete(route('faculty-loading.committee-assignments.destroy', a.id))
}

// ── Badge helpers ──────────────────────────────────────────────────────────
function roleBadge(role) {
  return {
    chairperson: 'amber',
    co_chair:    'amber',
    secretary:   'blue',
    member:      'slate',
  }[role] ?? 'slate'
}

function roleLabel(role) { return roles.find(r => r.value === role)?.label ?? role }

function statusBadge(status) {
  return {
    active:   'green',
    inactive: 'slate',
  }[status] ?? 'slate'
}
</script>
