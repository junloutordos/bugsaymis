<script setup>
import { computed, ref } from "vue"
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon, XMarkIcon } from "@heroicons/vue/24/outline"
import { useWorkDistributionPlans } from "@/Composables/useWorkDistributionPlans.js"

const props = defineProps({
  plans: Array,
  indicators: Array,
  offices: Array,
  committees: Array,
  assignments: Array,
})

const {
  plansList,
  showModal, modalMode, selectedPlan,
  searchQuery, currentPage, totalPages, filteredPlans,
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
  if (type === 'all') return 'bg-blue-100 text-blue-800'
  if (type === 'office') return 'bg-green-100 text-green-800'
  if (type === 'committee') return 'bg-purple-100 text-purple-800'
  if (type === 'assignment') return 'bg-orange-100 text-orange-800'
  return 'bg-gray-100 text-gray-700'
}

const tagTypeLabel = (type) => {
  if (type === 'office') return 'Office'
  if (type === 'committee') return 'Committee'
  if (type === 'assignment') return 'Assignment'
  if (type === 'all') return 'All'
  return 'Other'
}
</script>

<template>
  <Head title="Work Distribution Plan" />
  <AdminLayout title="Work Distribution Plan">
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Work Distribution Plan</h1>
        <button @click="openModal('create')" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> New WDP
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap items-center gap-3">
        <input v-model="searchQuery" type="text" placeholder="Search plans..."
          class="flex-1 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Performance Indicator</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Success Indicator</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office/Unit Involved</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rated By</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="plan in filteredPlans" :key="plan.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ plan.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ plan.performance_indicator?.description ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ plan.success_indicator ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ plan.office_involved ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ plan.rated_by ?? "—" }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1">
                    <button @click="openModal('view', plan)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"><EyeIcon class="w-4 h-4" /></button>
                    <button @click="openModal('edit', plan)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-amber-700 transition-colors"><PencilSquareIcon class="w-4 h-4" /></button>
                    <button @click="deletePlan(plan)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors"><TrashIcon class="w-4 h-4" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredPlans.length === 0">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">No plans found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
                <PaginationControl
          :current-page="currentPage"
          :total-pages="totalPages"
          @prev="currentPage--"
          @next="currentPage++"
          @page="currentPage = $event"
        />
      </div>

      <!-- MODAL -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode==='create' ? 'New WDP' : modalMode==='edit' ? 'Edit WDP' : 'View WDP' }}
            </h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors" @click="closeModal">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view'" class="px-6 py-5 space-y-3">
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
          </div>

          <!-- FORM -->
          <form v-else @submit.prevent="submitPlan">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Performance Indicator</label>
                <select v-model="form.performance_indicator_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                  <option value="">-- Select Performance Indicator --</option>
                  <option v-for="i in props.indicators" :key="i.id" :value="i.id">{{ i.description }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Success Indicator</label>
                <textarea v-model="form.success_indicator" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Rated By</label>
                <select v-model="form.rated_by" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="">-- Select Rater --</option>
                  <option value="Division Chief">Division Chief</option>
                  <option value="Unit Head">Unit Head</option>
                  <option value="Committee Head">Committee Head</option>
                  <option value="Coordinator">Coordinator</option>
                  <option value="Others">Others</option>
                </select>
              </div>

              <!-- Tag-based Office/Unit Involved selector -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Office/Unit/Committee Involved</label>

                <!-- Selected tags -->
                <div v-if="form.involved.length" class="flex flex-wrap gap-1 mb-2">
                  <span v-for="(tag, idx) in form.involved" :key="idx"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="tagTypeColor(tag.type)">
                    <span class="opacity-60">{{ tagTypeLabel(tag.type) }}:</span>
                    {{ tag.label }}
                    <button type="button" @click="removeTag(idx)" class="ml-0.5 hover:opacity-70">
                      <XMarkIcon class="w-3 h-3" />
                    </button>
                  </span>
                </div>

                <!-- Search input + dropdown -->
                <div class="relative">
                  <input
                    v-model="tagSearch"
                    type="text"
                    placeholder="Search or type to add a party… (Enter for free text)"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
                        class="px-3 py-2 cursor-pointer hover:bg-emerald-50 text-slate-700">
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
                        class="px-3 py-2 cursor-pointer hover:bg-amber-50 text-slate-700">
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
            </div>

            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <button type="button" @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
              <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Save</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
