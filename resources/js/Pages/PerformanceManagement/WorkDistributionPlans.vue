<script setup>
import { computed, ref } from "vue"
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppTable from "@/Components/AppTable.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon, XMarkIcon } from "@heroicons/vue/24/outline"
import FiscalYearFilter from "@/Components/FiscalYearFilter.vue"
import { useWorkDistributionPlans } from "@/Composables/useWorkDistributionPlans.js"

const props = defineProps({
  plans: Array,
  indicators: Array,
  offices: Array,
  committees: Array,
  assignments: Array,
  fiscalYears: { type: Array, default: () => [] },
  selectedFiscalYear: { type: [String, Number], default: "" },
  currentFiscalYear: { type: Number, default: null },
})

const {
  plansList,
  showModal, modalMode, selectedPlan,
  searchQuery, currentPage, totalPages, filteredPlans,
  applyFilters, clearFilters,
  form, tagSearch,
  openModal, closeModal,
  addTag, removeTag, addFreeTextTag,
  submitPlan, deletePlan,
} = useWorkDistributionPlans(props)

// Dropdown options for the tag selector (filtered by tagSearch)
const tagDropdownOptions = computed(() => {
  const q = tagSearch.value.toLowerCase()
  const selectedIds = {
    offices: form.value.involved.filter(t => t.type === 'office').map(t => t.id),
    committees: form.value.involved.filter(t => t.type === 'committee').map(t => t.id),
    assignments: form.value.involved.filter(t => t.type === 'assignment').map(t => t.id),
  }
  const hasAll = form.value.involved.some(t => t.type === 'all')

  const offices = (props.offices || [])
    .filter(o => !selectedIds.offices.includes(o.id) && o.name.toLowerCase().includes(q))
    .map(o => ({ type: 'office', id: o.id, label: o.name }))

  const committees = (props.committees || [])
    .filter(c => !selectedIds.committees.includes(c.id) && c.name.toLowerCase().includes(q))
    .map(c => ({ type: 'committee', id: c.id, label: c.name }))

  const assignments = (props.assignments || [])
    .filter(a => !selectedIds.assignments.includes(a.id) && a.name.toLowerCase().includes(q))
    .map(a => ({ type: 'assignment', id: a.id, label: a.name }))

  return { hasAll, offices, committees, assignments }
})

const tagInputFocused = ref(false)
const showDropdown = computed(() => tagInputFocused.value || tagSearch.value.length > 0)

const selectTag = (tag) => {
  addTag(tag)
  tagSearch.value = ''
  tagInputFocused.value = false
}

const handleAddFreeText = () => {
  addFreeTextTag()
  tagInputFocused.value = false
}

const tagTypeColor = (type) => {
  if (type === 'all') return 'blue'
  if (type === 'office') return 'green'
  if (type === 'committee') return 'purple'
  if (type === 'assignment') return 'orange'
  return 'slate'
}

const tagTypeLabel = (type) => {
  if (type === 'office') return 'Office'
  if (type === 'committee') return 'Committee'
  if (type === 'assignment') return 'Assignment'
  if (type === 'all') return 'All'
  return 'Other'
}

const modalTitle = computed(() => {
  if (modalMode.value === 'create') return 'New WDP'
  if (modalMode.value === 'edit') return 'Edit WDP'
  return 'View WDP'
})
</script>

<template>
  <Head title="Work Distribution Plan" />
  <AdminLayout title="Work Distribution Plan">
    <div class="space-y-5">

      <AppPageHeader title="Work Distribution Plan" subtitle="Manage performance indicators and success indicators across offices, committees, and assignments.">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New WDP
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <AppInput
          v-model="searchQuery"
          placeholder="Search plans..."
          class="flex-1 min-w-52"
          @keydown.enter.prevent="applyFilters"
        />
        <FiscalYearFilter :fiscal-years="fiscalYears" :selected="selectedFiscalYear" route-name="workdistribution.index" />
        <template #actions>
          <AppButton @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredPlans.length === 0" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Performance Indicator</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Success Indicator</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office/Unit Involved</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rated By</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="plan in filteredPlans" :key="plan.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ plan.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ plan.performance_indicator?.description ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ plan.success_indicator ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ plan.office_involved ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ plan.rated_by ?? "—" }}</td>
          <td class="px-4 py-3 text-center">
            <div class="flex justify-center gap-1">
              <AppIconButton label="View" @click="openModal('view', plan)"><EyeIcon class="w-4 h-4" /></AppIconButton>
              <AppIconButton label="Edit" variant="warning" @click="openModal('edit', plan)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deletePlan(plan)"><TrashIcon class="w-4 h-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No plans found" />
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
      <AppModal :show="showModal" :title="modalTitle" size="xl" @close="closeModal">
        <!-- VIEW MODE -->
        <div v-if="modalMode==='view'" class="space-y-3">
          <div class="flex flex-col gap-1 text-sm">
            <span class="text-xs text-slate-500">Performance Indicator</span>
            <span class="text-slate-800 font-medium">{{ selectedPlan.performance_indicator?.description }}</span>
          </div>
          <div class="flex flex-col gap-1 text-sm">
            <span class="text-xs text-slate-500">Success Indicator</span>
            <span class="text-slate-800">{{ selectedPlan.success_indicator }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Office/Unit Involved</span>
            <span class="text-slate-800">{{ selectedPlan.office_involved ?? "—" }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Rated By</span>
            <span class="text-slate-800">{{ selectedPlan.rated_by ?? "—" }}</span>
          </div>
          <div v-if="selectedPlan.load_source" class="flex justify-between text-sm">
            <span class="text-slate-500">Load Source</span>
            <span class="text-slate-800 capitalize">{{ selectedPlan.load_source }}</span>
          </div>
        </div>

        <!-- FORM -->
        <form v-else @submit.prevent="submitPlan" class="space-y-4">
          <AppSelect v-model="form.performance_indicator_id" label="Performance Indicator" placeholder="-- Select Performance Indicator --" required>
            <option v-for="i in props.indicators" :key="i.id" :value="i.id">{{ i.description }}</option>
          </AppSelect>

          <AppTextarea v-model="form.success_indicator" label="Success Indicator" :rows="3" />

          <AppSelect v-model="form.rated_by" label="Rated By" placeholder="-- Select Rater --">
            <option value="Division Chief">Division Chief</option>
            <option value="Unit Head">Unit Head</option>
            <option value="Academic Unit Head">Academic Unit Head</option>
            <option value="Committee Head">Committee Head</option>
            <option value="Coordinator">Coordinator</option>
            <option value="Others">Others</option>
          </AppSelect>

          <AppSelect
            v-if="form.rated_by === 'Academic Unit Head'"
            v-model="form.load_source"
            label="Load Source (faculty framework)"
            placeholder="-- Not linked to Faculty Loading --"
          >
            <option value="teaching">Teaching load</option>
            <option value="research">Research load</option>
            <option value="admin">Administrative designation</option>
            <option value="cocurricular">Co-curricular designation</option>
            <option value="committee">Committee work</option>
          </AppSelect>

          <AppSelect v-model="form.fiscal_year" label="Fiscal Year">
            <option :value="null">All years (unscoped)</option>
            <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
          </AppSelect>

          <!-- Tag-based Office/Unit Involved selector -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Office/Unit/Committee Involved</label>

            <!-- Selected tags -->
            <div v-if="form.involved.length" class="flex flex-wrap gap-1 mb-2">
              <AppBadge v-for="(tag, idx) in form.involved" :key="idx" :color="tagTypeColor(tag.type)">
                <span class="opacity-60">{{ tagTypeLabel(tag.type) }}:</span>&nbsp;{{ tag.label }}
                <button type="button" @click="removeTag(idx)" class="ml-0.5 hover:opacity-70">
                  <XMarkIcon class="w-3 h-3" />
                </button>
              </AppBadge>
            </div>

            <!-- Search input + dropdown -->
            <div class="relative">
              <AppInput
                v-model="tagSearch"
                placeholder="Search or type to add a party… (Enter for free text)"
                @keydown.enter.prevent="handleAddFreeText"
                @focus="tagInputFocused = true"
                @blur="setTimeout(() => { tagInputFocused = false }, 150)"
              />

              <!-- Dropdown -->
              <div v-if="showDropdown"
                class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-y-auto text-sm">

                <div v-if="!form.involved.some(t => t.type === 'all')"
                  class="px-3 py-1.5 text-xs font-semibold text-slate-500 bg-slate-50 border-b">All</div>
                <div v-if="!form.involved.some(t => t.type === 'all')"
                  @click="selectTag({ type: 'all', label: 'All Offices' })"
                  class="px-3 py-2 cursor-pointer hover:bg-indigo-50 text-indigo-700 font-medium">
                  All Offices
                </div>

                <template v-if="tagDropdownOptions.offices.length">
                  <div class="px-3 py-1.5 text-xs font-semibold text-slate-500 bg-slate-50 border-b border-t">Offices</div>
                  <div v-for="opt in tagDropdownOptions.offices" :key="'o'+opt.id"
                    @click="selectTag(opt)"
                    class="px-3 py-2 cursor-pointer hover:bg-success-50 text-slate-700">
                    {{ opt.label }}
                  </div>
                </template>

                <template v-if="tagDropdownOptions.committees.length">
                  <div class="px-3 py-1.5 text-xs font-semibold text-slate-500 bg-slate-50 border-b border-t">Committees</div>
                  <div v-for="opt in tagDropdownOptions.committees" :key="'c'+opt.id"
                    @click="selectTag(opt)"
                    class="px-3 py-2 cursor-pointer hover:bg-violet-50 text-slate-700">
                    {{ opt.label }}
                  </div>
                </template>

                <template v-if="tagDropdownOptions.assignments.length">
                  <div class="px-3 py-1.5 text-xs font-semibold text-slate-500 bg-slate-50 border-b border-t">Special Assignments</div>
                  <div v-for="opt in tagDropdownOptions.assignments" :key="'a'+opt.id"
                    @click="selectTag(opt)"
                    class="px-3 py-2 cursor-pointer hover:bg-warning-50 text-slate-700">
                    {{ opt.label }}
                  </div>
                </template>

                <div v-if="tagSearch.trim()" class="px-3 py-2 text-slate-400 border-t text-xs italic cursor-pointer hover:bg-slate-50"
                  @click="handleAddFreeText">
                  Press Enter or click to add "{{ tagSearch }}" as free text
                </div>

                <div v-if="!tagDropdownOptions.offices.length && !tagDropdownOptions.committees.length && !tagDropdownOptions.assignments.length && !tagSearch.trim()"
                  class="px-3 py-2 text-slate-400 text-xs">
                  All options selected
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit">Save</AppButton>
          </div>
        </form>

        <template v-if="modalMode==='view'" #footer>
          <AppButton variant="secondary" @click="closeModal">Close</AppButton>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>
