<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline";
import { useOutcomes } from "@/Composables/useOutcomes.js";

const props = defineProps({
  outcomes: Array,
});

const {
  outcomesList,
  showModal,
  modalMode,
  selectedOutcome,
  searchQuery,
  currentPage,
  totalPages,
  filteredOutcomes,
  form,
  openModal,
  closeModal,
  submitOutcome,
  deleteOutcome,
} = useOutcomes(props);

// Dropdown options
const outcomeTypes = ["Strategic Functions", "Core Functions", "Support Functions"];
</script>

<template>
  <Head title="Agency Organizational Outcomes" />
  <AdminLayout title="Agency Organizational Outcome Management">
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Organizational Outcomes</h1>
        <button @click="openModal('create')" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> New Outcome
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search outcomes..."
          class="flex-1 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Outcome</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Sub-Outcome</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Created At</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="outcome in filteredOutcomes" :key="outcome.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ outcome.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ outcome.outcome }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ outcome.sub_outcome ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ outcome.function_type ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(outcome.created_at).toLocaleDateString() }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="openModal('view', outcome)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                      <EyeIcon class="w-4 h-4" />
                    </button>
                    <button @click="openModal('edit', outcome)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-amber-700 transition-colors">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click="deleteOutcome(outcome)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredOutcomes.length===0">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">No outcomes found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-1">
            <button @click="currentPage--" :disabled="currentPage===1" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">Prev</button>
            <button @click="currentPage++" :disabled="currentPage===totalPages" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">Next</button>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode==='create' ? 'New Outcome' : modalMode==='edit' ? 'Edit Outcome' : 'View Outcome' }}
            </h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors" @click="closeModal">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedOutcome" class="px-6 py-5 space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-slate-500">Outcome</span>
              <span class="font-medium text-slate-800">{{ selectedOutcome.outcome }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-slate-500">Sub-Outcome</span>
              <span class="font-medium text-slate-800">{{ selectedOutcome.sub_outcome ?? '—' }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-slate-500">Type</span>
              <span class="font-medium text-slate-800">{{ selectedOutcome.function_type ?? '—' }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-slate-500">Created At</span>
              <span class="font-medium text-slate-800">{{ new Date(selectedOutcome.created_at).toLocaleString() }}</span>
            </div>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitOutcome">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Outcome</label>
                <input v-model="form.outcome" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" required />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sub-Outcome</label>
                <input v-model="form.sub_outcome" type="text" placeholder="Optional" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                <select v-model="form.function_type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                  <option value="" disabled>Select type</option>
                  <option v-for="type in outcomeTypes" :key="type" :value="type">{{ type }}</option>
                </select>
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
