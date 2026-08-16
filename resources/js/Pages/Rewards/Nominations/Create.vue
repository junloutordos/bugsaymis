<template>
  <AdminLayout title="Nominate Employee">
    <div class="mx-auto max-w-2xl space-y-6">
      <Link :href="route('rewards.nominations.index')" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        ← Back to Nominations
      </Link>
      <AppPageHeader title="Nominate Employee" />

      <AppCard :padded="false">
        <div class="px-5 py-4 border-b border-slate-100">
          <p class="text-sm text-slate-500">Fill in the details below to submit a nomination.</p>
        </div>
        <div class="p-5">
          <form @submit.prevent="submit" class="space-y-5">
            <AppSelect v-model="form.reward_type_id" label="Award / Recognition Type" required :error="form.errors.reward_type_id" placeholder="Select award type…">
              <option v-for="t in rewardTypes" :key="t.id" :value="t.id">
                {{ t.name }} ({{ t.frequency }})
              </option>
            </AppSelect>

            <AppSelect v-model="form.nominee_id" label="Nominee" required :error="form.errors.nominee_id" placeholder="Select employee…">
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </AppSelect>

            <!-- Period -->
            <div class="grid grid-cols-2 gap-4">
              <AppInput v-model="form.period" label="Period (e.g. Q1 2026)" placeholder="Q1 2026" />
              <AppInput v-model="form.team_name" label="Team Name (if team award)" placeholder="Optional" />
            </div>

            <AppTextarea v-model="form.justification" label="Justification" required :rows="5" :error="form.errors.justification"
              placeholder="Describe why this employee/team deserves the award…" />

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
              <AppButton as="link" variant="secondary" :href="route('rewards.nominations.index')">Cancel</AppButton>
              <AppButton type="submit" :loading="form.processing">Submit Nomination</AppButton>
            </div>
          </form>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppButton from '@/Components/AppButton.vue'

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

const pickedFiles = ref([])

const selectedType = computed(() =>
  props.rewardTypes.find(t => t.id == form.reward_type_id) ?? null
)

function handleFiles(e) {
  pickedFiles.value = Array.from(e.target.files)
}

function readFileAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

async function submit() {
  form.supporting_documents = await Promise.all(pickedFiles.value.map(readFileAsDataUrl))
  form.post(route('rewards.nominations.store'))
}
</script>
