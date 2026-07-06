<template>
  <Head title="Designations" />
  <AdminLayout title="Designations">
    <div class="space-y-5">

      <AppPageHeader title="Designations" subtitle="Manage designations and assign faculty holders per term">
        <template #actions>
          <select v-model="selectedTermId" @change="applyTermFilter"
            class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </select>
          <AppButton variant="secondary" @click="openCatForm()">
            <TagIcon class="h-4 w-4" /> New Category
          </AppButton>
          <AppButton @click="openDesigForm(null, activeCategory)">
            <PlusIcon class="h-4 w-4" /> New Designation
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.errors.error }}
      </div>

      <!-- Empty state -->
      <AppCard v-if="categories.length === 0">
        <EmptyState title="No categories yet" subtitle="Add one to get started." />
      </AppCard>

      <!-- Tabbed layout -->
      <AppCard v-else :padded="false">
        <AppTabs :tabs="tabs" v-model="activeTabId">
          <template v-for="cat in categories" :key="cat.id">
            <div v-show="activeTabId === String(cat.id)">

              <!-- Panel toolbar -->
              <div class="flex items-center justify-between px-5 py-2.5 bg-slate-50/60 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                  <span class="font-mono text-xs text-indigo-700 font-bold bg-indigo-50 px-2 py-0.5 rounded">{{ cat.code }}</span>
                  <span v-if="cat.description" class="text-xs text-slate-400">{{ cat.description }}</span>
                  <AppBadge :color="cat.is_active ? 'green' : 'slate'">{{ cat.is_active ? 'Active' : 'Inactive' }}</AppBadge>
                </div>
                <div class="flex items-center gap-1">
                  <AppButton size="sm" variant="ghost" @click="openDesigForm(null, cat)">
                    <PlusIcon class="h-3.5 w-3.5" /> Add Designation
                  </AppButton>
                  <AppIconButton label="Edit category" @click="openCatForm(cat)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                  <AppIconButton label="Delete category" variant="danger" @click="deleteCat(cat)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                </div>
              </div>

              <!-- Designations table -->
              <AppTable :card="false" :is-empty="cat.designations.length === 0" :skeleton-cols="7">
                <template #head>
                  <tr>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Code</th>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                    <th class="px-5 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Units</th>
                    <th class="px-5 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Max</th>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Current Holders</th>
                    <th class="px-5 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-2"></th>
                  </tr>
                </template>

                <tr v-for="d in cat.designations" :key="d.id" class="hover:bg-slate-50/50">
                  <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ d.code }}</td>
                  <td class="px-5 py-3">
                    <div class="font-medium text-slate-800">{{ d.name }}</div>
                    <div v-if="d.requires_unit" class="text-[10px] text-amber-600 font-medium mt-0.5">Unit-scoped</div>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="font-semibold text-slate-700 block">
                      <template v-if="isManagedExternally(d) && d.load_units === 0">
                        {{ d.holders.reduce((s, h) => s + h.load_units, 0) }}
                      </template>
                      <template v-else>{{ d.load_units }}</template>
                    </span>
                    <AppBadge :color="assignmentTypeBadge(d.assignment_type)">{{ d.assignment_type }}</AppBadge>
                  </td>
                  <td class="px-5 py-3 text-center text-slate-500">{{ d.max_holders ?? '∞' }}</td>

                  <!-- Current Holders -->
                  <td class="px-5 py-3">
                    <div v-if="d.holders.length" class="flex flex-wrap gap-1.5">
                      <span v-for="h in d.holders" :key="h.assignment_id"
                        class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ h.user_name }}<span class="text-indigo-400 font-normal">&nbsp;({{ h.load_units }}u)</span>
                        <button v-if="!isManagedExternally(d)" @click="revokeHolder(h)" class="hover:text-danger-600 transition-colors ml-0.5 rounded-full p-0.5 hover:bg-danger-50" title="Remove">
                          <XMarkIcon class="h-3 w-3" />
                        </button>
                      </span>
                    </div>
                    <span v-else class="text-xs text-slate-400 italic">Unassigned</span>
                    <p v-if="isAuhDesignation(d)" class="text-[10px] text-amber-600 mt-1">Managed via Academic Units</p>
                    <p v-else-if="isSectionDesignation(d)" class="text-[10px] text-amber-600 mt-1">Managed via Sections</p>
                    <p v-else-if="isResearchDesignation(d)" class="text-[10px] text-amber-600 mt-1">Managed via Research Advisories</p>
                  </td>

                  <!-- Status -->
                  <td class="px-5 py-3 text-center">
                    <AppBadge :color="d.is_active ? 'green' : 'slate'">{{ d.is_active ? 'Active' : 'Inactive' }}</AppBadge>
                  </td>

                  <!-- Actions -->
                  <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <a v-if="isAuhDesignation(d)"
                        :href="route('faculty-loading.academic-units.index')"
                        class="p-1.5 rounded text-slate-400 hover:text-amber-600 transition-colors"
                        title="Manage in Academic Units">
                        <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                      </a>
                      <a v-else-if="isSectionDesignation(d)"
                        :href="route('faculty-loading.sections.index')"
                        class="p-1.5 rounded text-slate-400 hover:text-amber-600 transition-colors"
                        title="Manage in Sections">
                        <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                      </a>
                      <a v-else-if="isResearchDesignation(d)"
                        :href="route('faculty-loading.research-advisories.index')"
                        class="p-1.5 rounded text-slate-400 hover:text-amber-600 transition-colors"
                        title="Manage in Research Advisories">
                        <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                      </a>
                      <AppIconButton v-else :label="atCapacity(d) ? 'At capacity' : 'Assign faculty'" variant="success" :disabled="atCapacity(d)" @click="openAssignModal(d)">
                        <UserPlusIcon class="h-4 w-4" />
                      </AppIconButton>
                      <AppIconButton label="Edit designation" @click="openDesigForm(d, cat)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                      <AppIconButton label="Delete designation" variant="danger" @click="deleteDesig(d)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                    </div>
                  </td>
                </tr>

                <template #empty>
                  <p class="text-xs text-slate-400 italic">No designations in this category yet.</p>
                </template>

                <template #mobileCard>
                  <div v-for="d in cat.designations" :key="d.id" class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                      <div>
                        <p class="font-medium text-slate-800">{{ d.name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ d.code }}</p>
                        <p v-if="d.requires_unit" class="text-[10px] text-amber-600 font-medium mt-0.5">Unit-scoped</p>
                      </div>
                      <AppBadge :color="d.is_active ? 'green' : 'slate'">{{ d.is_active ? 'Active' : 'Inactive' }}</AppBadge>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                      <span class="font-semibold text-slate-700">
                        <template v-if="isManagedExternally(d) && d.load_units === 0">{{ d.holders.reduce((s, h) => s + h.load_units, 0) }}</template>
                        <template v-else>{{ d.load_units }}</template>
                        units
                      </span>
                      <AppBadge :color="assignmentTypeBadge(d.assignment_type)">{{ d.assignment_type }}</AppBadge>
                      <span v-if="d.max_holders !== null">Max {{ d.max_holders }}</span>
                    </div>
                    <div v-if="d.holders.length" class="flex flex-wrap gap-1.5">
                      <span v-for="h in d.holders" :key="h.assignment_id"
                        class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ h.user_name }}<span class="text-indigo-400 font-normal">&nbsp;({{ h.load_units }}u)</span>
                        <button v-if="!isManagedExternally(d)" @click="revokeHolder(h)" class="hover:text-danger-600 transition-colors ml-0.5 rounded-full p-0.5 hover:bg-danger-50" title="Remove">
                          <XMarkIcon class="h-3 w-3" />
                        </button>
                      </span>
                    </div>
                    <span v-else class="text-xs text-slate-400 italic">Unassigned</span>
                    <p v-if="isAuhDesignation(d)" class="text-[10px] text-amber-600">Managed via Academic Units</p>
                    <p v-else-if="isSectionDesignation(d)" class="text-[10px] text-amber-600">Managed via Sections</p>
                    <p v-else-if="isResearchDesignation(d)" class="text-[10px] text-amber-600">Managed via Research Advisories</p>
                    <div class="flex items-center justify-end gap-1 pt-1">
                      <a v-if="isAuhDesignation(d)" :href="route('faculty-loading.academic-units.index')" class="p-1.5 rounded text-slate-400 hover:text-amber-600" title="Manage in Academic Units"><ArrowTopRightOnSquareIcon class="h-4 w-4" /></a>
                      <a v-else-if="isSectionDesignation(d)" :href="route('faculty-loading.sections.index')" class="p-1.5 rounded text-slate-400 hover:text-amber-600" title="Manage in Sections"><ArrowTopRightOnSquareIcon class="h-4 w-4" /></a>
                      <a v-else-if="isResearchDesignation(d)" :href="route('faculty-loading.research-advisories.index')" class="p-1.5 rounded text-slate-400 hover:text-amber-600" title="Manage in Research Advisories"><ArrowTopRightOnSquareIcon class="h-4 w-4" /></a>
                      <AppIconButton v-else :label="atCapacity(d) ? 'At capacity' : 'Assign faculty'" variant="success" :disabled="atCapacity(d)" @click="openAssignModal(d)"><UserPlusIcon class="h-4 w-4" /></AppIconButton>
                      <AppIconButton label="Edit designation" @click="openDesigForm(d, cat)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                      <AppIconButton label="Delete designation" variant="danger" @click="deleteDesig(d)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                    </div>
                  </div>
                </template>
              </AppTable>

            </div>
          </template>
        </AppTabs>
      </AppCard>

    </div>

    <!-- ── Category Modal ───────────────────────────────────────────────────── -->
    <AppModal :show="catModal" :title="`${catForm.id ? 'Edit' : 'New'} Category`" size="sm" @close="catModal = false">
      <div class="space-y-3">
        <AppInput v-model="catForm.code" label="Code" required placeholder="e.g. ACADEMIC" :error="catForm.errors.code" />
        <AppInput v-model="catForm.name" label="Name" required placeholder="e.g. Academic" />
        <AppTextarea v-model="catForm.description" rows="2" label="Description" />
        <div class="flex items-center gap-2">
          <input v-model="catForm.is_active" type="checkbox" id="cat-active" class="rounded text-indigo-600" />
          <label for="cat-active" class="text-sm text-slate-600">Active</label>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="catModal = false">Cancel</AppButton>
        <AppButton :loading="catForm.processing" @click="saveCat">{{ catForm.id ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>

    <!-- ── Designation Modal ────────────────────────────────────────────────── -->
    <AppModal :show="desigModal" :title="`${desigForm.id ? 'Edit' : 'New'} Designation`" @close="desigModal = false">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Category <span class="text-red-500">*</span></label>
          <select v-model="desigForm.designation_category_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="desigForm.code" label="Code" required placeholder="e.g. DEPT_HEAD_SCI" :error="desigForm.errors.code" />
          <AppInput v-model.number="desigForm.load_units" type="number" min="0" step="0.5" label="Load Units" required />
        </div>
        <div>
          <AppSelect v-model="desigForm.assignment_type" label="Load Type" required :show-blank="false">
            <option value="admin">Admin</option>
            <option value="teaching">Teaching</option>
            <option value="research">Research</option>
            <option value="cocurricular">Co-curricular</option>
            <option value="committee">Committee</option>
          </AppSelect>
          <p class="text-[11px] text-slate-400 mt-1">Determines which column this load counts toward in faculty totals.</p>
        </div>
        <AppInput v-model="desigForm.name" label="Name" required placeholder="e.g. Department Head – Science" />
        <AppTextarea v-model="desigForm.description" rows="2" label="Description" />
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model.number="desigForm.max_holders" type="number" min="1" placeholder="Unlimited" label="Max Holders" />
          <div class="space-y-1 pt-1">
            <div class="flex items-center gap-2 pt-4">
              <input v-model="desigForm.requires_unit" type="checkbox" id="desig-unit" class="rounded text-indigo-600" />
              <label for="desig-unit" class="text-sm text-slate-600">Requires unit scope</label>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="desigForm.is_active" type="checkbox" id="desig-active" class="rounded text-indigo-600" />
              <label for="desig-active" class="text-sm text-slate-600">Active</label>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="desigModal = false">Cancel</AppButton>
        <AppButton :loading="desigForm.processing" @click="saveDesig">{{ desigForm.id ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>

    <!-- ── Assign Faculty Modal ─────────────────────────────────────────────── -->
    <AppModal :show="assignModal" title="Assign Faculty" :subtitle="assignSubtitle" @close="assignModal = false">
      <div class="space-y-4">
        <!-- Current holders from any module -->
        <div v-if="selectedDesig?.holders.length" class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2.5 space-y-1.5">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Currently Assigned</p>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="h in selectedDesig.holders" :key="h.assignment_id"
              class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
              {{ h.user_name }}<span class="text-indigo-400 font-normal">&nbsp;({{ h.load_units }}u)</span>
              <button v-if="!isManagedExternally(selectedDesig)" @click="revokeHolder(h); assignModal = false"
                class="hover:text-danger-600 transition-colors ml-0.5 rounded-full p-0.5 hover:bg-danger-50" title="Remove">
                <XMarkIcon class="h-3 w-3" />
              </button>
            </span>
          </div>
        </div>

        <!-- At capacity warning -->
        <div v-if="atCapacity(selectedDesig)"
          class="rounded-lg bg-warning-50 border border-warning-100 px-3 py-2 text-xs text-warning-700">
          This designation has reached its maximum of {{ selectedDesig.max_holders }} holder(s).
          Remove an existing holder to assign someone new.
        </div>

        <template v-else>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term <span class="text-red-500">*</span></label>
            <select v-model="assignForm.academic_term_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select term...</option>
              <option v-for="t in terms" :key="t.id" :value="t.id">
                {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty <span class="text-red-500">*</span></label>
            <select v-model="assignForm.user_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select faculty...</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Load Units
              <span class="text-slate-400 font-normal ml-1">(default: {{ selectedDesig?.load_units }})</span>
            </label>
            <input v-model.number="assignForm.override_units" type="number" step="0.5" min="0"
              :placeholder="`${selectedDesig?.load_units}`"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <p class="text-xs text-slate-400 mt-1">Leave blank to use the designation's default units.</p>
          </div>
        </template>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="assignModal = false">Cancel</AppButton>
        <AppButton v-if="!atCapacity(selectedDesig)" variant="success"
          :disabled="assignForm.processing || !assignForm.user_id || !assignForm.academic_term_id" @click="saveAssign">
          Assign
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTabs from '@/Components/AppTabs.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppSelect from '@/Components/AppSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  ArrowTopRightOnSquareIcon, CheckCircleIcon, ExclamationCircleIcon, PencilIcon, PlusIcon,
  TagIcon, TrashIcon, UserPlusIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  categories:  { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
})

// ── Active tab ────────────────────────────────────────────────────────────────

const activeTabId = ref(props.categories[0]?.id != null ? String(props.categories[0].id) : null)
const activeCategory = computed(() => props.categories.find(c => String(c.id) === activeTabId.value) ?? null)
const tabs = computed(() => props.categories.map(c => ({ key: String(c.id), label: `${c.name} (${c.designations.length})` })))

// ── Helpers ───────────────────────────────────────────────────────────────────

function isAuhDesignation(d) {
  return d.code.startsWith('AUH-')
}

function isSectionDesignation(d) {
  return !!d.section_id
}

function isResearchDesignation(d) {
  return d.code.startsWith('RES-')
}

function isManagedExternally(d) {
  return isAuhDesignation(d) || isSectionDesignation(d) || isResearchDesignation(d)
}

function atCapacity(d) {
  return !!d && d.max_holders !== null && d.holders.length >= d.max_holders
}

function assignmentTypeBadge(type) {
  return {
    admin:        'slate',
    teaching:     'blue',
    research:     'purple',
    cocurricular: 'green',
    committee:    'orange',
  }[type] ?? 'slate'
}

// ── Term filter ───────────────────────────────────────────────────────────────

const selectedTermId = ref(props.filters.term_id ? Number(props.filters.term_id) : (props.currentTerm?.id ?? null))

function applyTermFilter() {
  router.get(route('faculty-loading.designations.index'), { term_id: selectedTermId.value }, { preserveState: true })
}

// ── Category CRUD ─────────────────────────────────────────────────────────────

const catModal = ref(false)
const catForm  = useForm({ id: null, code: '', name: '', description: '', sort_order: 0, is_active: true })

function openCatForm(cat = null) {
  if (cat) Object.assign(catForm, { id: cat.id, code: cat.code, name: cat.name, description: cat.description ?? '', sort_order: cat.sort_order, is_active: cat.is_active })
  else { catForm.reset(); catForm.id = null; catForm.is_active = true }
  catModal.value = true
}

function saveCat() {
  if (catForm.id) {
    catForm.put(route('faculty-loading.designations.categories.update', catForm.id), { onSuccess: () => { catModal.value = false } })
  } else {
    catForm.post(route('faculty-loading.designations.categories.store'), { onSuccess: () => { catModal.value = false } })
  }
}

async function deleteCat(cat) {
  if (! await confirmDelete(`Delete category "${cat.name}"?`)) return
  useForm({}).delete(route('faculty-loading.designations.categories.destroy', cat.id))
}

// ── Designation CRUD ──────────────────────────────────────────────────────────

const desigModal = ref(false)
const desigForm  = useForm({ id: null, designation_category_id: null, code: '', name: '', description: '', load_units: 0, assignment_type: 'admin', requires_unit: false, max_holders: null, sort_order: 0, is_active: true })

function openDesigForm(d = null, cat = null) {
  if (d) {
    Object.assign(desigForm, { id: d.id, designation_category_id: d.designation_category_id ?? cat?.id, code: d.code, name: d.name, description: d.description ?? '', load_units: d.load_units, assignment_type: d.assignment_type ?? 'admin', requires_unit: d.requires_unit, max_holders: d.max_holders, sort_order: d.sort_order, is_active: d.is_active })
  } else {
    desigForm.reset(); desigForm.id = null; desigForm.is_active = true; desigForm.load_units = 0; desigForm.assignment_type = 'admin'
    desigForm.designation_category_id = cat?.id ?? null
  }
  desigModal.value = true
}

function saveDesig() {
  if (desigForm.id) {
    desigForm.put(route('faculty-loading.designations.update', desigForm.id), { onSuccess: () => { desigModal.value = false } })
  } else {
    desigForm.post(route('faculty-loading.designations.store'), { onSuccess: () => { desigModal.value = false } })
  }
}

async function deleteDesig(d) {
  if (! await confirmDelete(`Delete designation "${d.name}"?`)) return
  useForm({}).delete(route('faculty-loading.designations.destroy', d.id))
}

// ── Assign faculty ────────────────────────────────────────────────────────────

const assignModal   = ref(false)
const selectedDesig = ref(null)
const assignForm    = useForm({ user_id: null, academic_term_id: null, override_units: null })
const assignSubtitle = computed(() => selectedDesig.value ? `${selectedDesig.value.name} — ${selectedDesig.value.load_units} units` : null)

function openAssignModal(d) {
  selectedDesig.value = d
  assignForm.reset()
  assignForm.user_id          = null
  assignForm.academic_term_id = props.currentTerm?.id ?? null
  assignForm.override_units   = null
  assignModal.value = true
}

function saveAssign() {
  assignForm.post(route('faculty-loading.designations.assign-direct', selectedDesig.value.id), {
    onSuccess: () => { assignModal.value = false },
  })
}

// ── Revoke a holder ───────────────────────────────────────────────────────────

async function revokeHolder(holder) {
  if (! await confirmDelete(`Remove "${holder.user_name}" from this designation?`)) return
  useForm({}).delete(route('faculty-loading.designations.revoke-direct', holder.assignment_id))
}
</script>
