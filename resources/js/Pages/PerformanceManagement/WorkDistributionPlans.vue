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
    <div>
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">Work Distribution Plan</h1>
        <button @click="openModal('create')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New WDP
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input v-model="searchQuery" type="text" placeholder="Search plans..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm" />

        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Performance Indicator</th>
                <th class="px-4 py-3 text-left">Success Indicator</th>
                <th class="px-4 py-3 text-left">Office/Unit Involved</th>
                <th class="px-4 py-3 text-left">Rated By</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="plan in filteredPlans" :key="plan.id">
                <td class="px-4 py-3">{{ plan.id }}</td>
                <td class="px-4 py-3">{{ plan.performance_indicator?.description ?? "—" }}</td>
                <td class="px-4 py-3">{{ plan.success_indicator ?? "—" }}</td>
                <td class="px-4 py-3">{{ plan.office_involved ?? "—" }}</td>
                <td class="px-4 py-3">{{ plan.rated_by ?? "—" }}</td>
                <td class="px-4 py-3 text-center space-x-2">
                  <button @click="openModal('view', plan)" class="text-blue-600"><EyeIcon class="w-5 h-5" /></button>
                  <button @click="openModal('edit', plan)" class="text-yellow-600"><PencilSquareIcon class="w-5 h-5" /></button>
                  <button @click="deletePlan(plan)" class="text-red-600"><TrashIcon class="w-5 h-5" /></button>
                </td>
              </tr>
              <tr v-if="filteredPlans.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-gray-500">No plans found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage===1" class="px-3 py-1 bg-gray-200 rounded">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage===totalPages" class="px-3 py-1 bg-gray-200 rounded">Next</button>
        </div>
      </div>

      <!-- MODAL -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-xl p-6 relative max-h-[90vh] overflow-y-auto">
          <button class="absolute top-3 right-3 text-gray-500" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode==='create' ? 'New WDP' : modalMode==='edit' ? 'Edit WDP' : 'View WDP' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view'" class="space-y-2">
            <p><strong>Performance Indicator:</strong> {{ selectedPlan.performance_indicator?.description }}</p>
            <p><strong>Success Indicator:</strong> {{ selectedPlan.success_indicator }}</p>
            <p><strong>Office/Unit Involved:</strong> {{ selectedPlan.office_involved ?? "—" }}</p>
            <p><strong>Rated By:</strong> {{ selectedPlan.rated_by ?? "—" }}</p>
          </div>

          <!-- FORM -->
          <form v-else @submit.prevent="submitPlan" class="space-y-4">
            <div>
              <label class="font-medium">Performance Indicator</label>
              <select v-model="form.performance_indicator_id" class="w-full mt-1 rounded-lg border-gray-300" required>
                <option value="">-- Select Performance Indicator --</option>
                <option v-for="i in props.indicators" :key="i.id" :value="i.id">{{ i.description }}</option>
              </select>
            </div>

            <div>
              <label class="font-medium">Success Indicator</label>
              <textarea v-model="form.success_indicator" rows="3" class="w-full rounded-lg border-gray-300"></textarea>
            </div>

            <div>
              <label class="font-medium">Rated By</label>
              <select v-model="form.rated_by" class="w-full mt-1 rounded-lg border-gray-300">
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
              <label class="font-medium block mb-1">Office/Unit/Committee Involved</label>

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
                  placeholder="Search or type to add a party... (Enter for free text)"
                  class="w-full rounded-lg border-gray-300 text-sm"
                  @keydown.enter.prevent="handleAddFreeText"
                  @focus="tagInputFocused = true"
                  @blur="setTimeout(() => { tagInputFocused = false }, 150)"
                />

                <!-- Dropdown -->
                <div v-if="showDropdown"
                  class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto text-sm">

                  <!-- All Offices option -->
                  <div v-if="!form.involved.some(t => t.type === 'all')"
                    class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b">All</div>
                  <div v-if="!form.involved.some(t => t.type === 'all')"
                    @click="selectTag({ type: 'all', label: 'All Offices' })"
                    class="px-3 py-2 cursor-pointer hover:bg-blue-50 text-blue-700 font-medium">
                    All Offices
                  </div>

                  <!-- Offices -->
                  <template v-if="tagDropdownOptions.offices.length">
                    <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b border-t">Offices</div>
                    <div v-for="opt in tagDropdownOptions.offices" :key="'o'+opt.id"
                      @click="selectTag(opt)"
                      class="px-3 py-2 cursor-pointer hover:bg-green-50">
                      {{ opt.label }}
                    </div>
                  </template>

                  <!-- Committees -->
                  <template v-if="tagDropdownOptions.committees.length">
                    <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b border-t">Committees</div>
                    <div v-for="opt in tagDropdownOptions.committees" :key="'c'+opt.id"
                      @click="selectTag(opt)"
                      class="px-3 py-2 cursor-pointer hover:bg-purple-50">
                      {{ opt.label }}
                    </div>
                  </template>

                  <!-- Special Assignments -->
                  <template v-if="tagDropdownOptions.assignments.length">
                    <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b border-t">Special Assignments</div>
                    <div v-for="opt in tagDropdownOptions.assignments" :key="'a'+opt.id"
                      @click="selectTag(opt)"
                      class="px-3 py-2 cursor-pointer hover:bg-orange-50">
                      {{ opt.label }}
                    </div>
                  </template>

                  <!-- Free text hint -->
                  <div v-if="tagSearch.trim()" class="px-3 py-2 text-gray-400 border-t text-xs italic cursor-pointer hover:bg-gray-50"
                    @click="handleAddFreeText">
                    Press Enter or click to add "{{ tagSearch }}" as free text
                  </div>

                  <div v-if="!tagDropdownOptions.offices.length && !tagDropdownOptions.committees.length && !tagDropdownOptions.assignments.length && !tagSearch.trim()"
                    class="px-3 py-2 text-gray-400 text-xs">
                    All options selected
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
