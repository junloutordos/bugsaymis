<template>
  <AdminLayout title="Nominate Employee">
    <div class="mx-auto max-w-2xl space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
          <Link :href="route('rewards.nominations.index')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">←</Link>
          <h1 class="text-xl font-semibold text-slate-800">Nominate Employee</h1>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <p class="text-sm text-slate-500">Fill in the details below to submit a nomination.</p>
        </div>
        <div class="p-5">
          <form @submit.prevent="submit" class="space-y-5" enctype="multipart/form-data">
            <!-- Reward Type -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Award / Recognition Type <span class="text-red-500">*</span></label>
              <select v-model="form.reward_type_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required>
                <option value="">Select award type…</option>
                <option v-for="t in rewardTypes" :key="t.id" :value="t.id">
                  {{ t.name }} ({{ t.frequency }})
                </option>
              </select>
              <p v-if="form.errors.reward_type_id" class="mt-1 text-xs text-red-500">{{ form.errors.reward_type_id }}</p>
            </div>

            <!-- Nominee -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Nominee <span class="text-red-500">*</span></label>
              <select v-model="form.nominee_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required>
                <option value="">Select employee…</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
              </select>
              <p v-if="form.errors.nominee_id" class="mt-1 text-xs text-red-500">{{ form.errors.nominee_id }}</p>
            </div>

            <!-- Period -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Period (e.g. Q1 2026)</label>
                <input v-model="form.period" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" placeholder="Q1 2026" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Team Name (if team award)</label>
                <input v-model="form.team_name" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" placeholder="Optional" />
              </div>
            </div>

            <!-- Justification -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Justification <span class="text-red-500">*</span></label>
              <textarea v-model="form.justification" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="5"
                placeholder="Describe why this employee/team deserves the award…" required />
              <p v-if="form.errors.justification" class="mt-1 text-xs text-red-500">{{ form.errors.justification }}</p>
            </div>

            <!-- Supporting Documents -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Supporting Documents</label>
              <input type="file" multiple accept=".pdf,.doc,.docx,.jpg,.png"
                @change="handleFiles" class="block w-full text-sm text-slate-600" />
              <p class="mt-1 text-xs text-slate-400">PDF, Word, JPG, PNG — max 5MB each</p>
            </div>

            <!-- Criteria reminder -->
            <div v-if="selectedType" class="rounded-lg bg-indigo-50 border border-indigo-100 p-3 text-sm text-indigo-700">
              <strong>Criteria:</strong> {{ selectedType.criteria ?? 'No specific criteria listed.' }}
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <Link :href="route('rewards.nominations.index')"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Cancel
              </Link>
              <button type="submit" :disabled="form.processing"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
                Submit Nomination
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  rewardTypes: Array,
  employees: Array,
})

const form = useForm({
  reward_type_id: '',
  nominee_id: '',
  period: '',
  team_name: '',
  justification: '',
  supporting_documents: [],
})

const selectedType = computed(() =>
  props.rewardTypes.find(t => t.id == form.reward_type_id) ?? null
)

function handleFiles(e) {
  form.supporting_documents = Array.from(e.target.files)
}

function submit() {
  form.post(route('rewards.nominations.store'), {
    forceFormData: true,
  })
}
</script>
