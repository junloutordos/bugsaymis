<script setup>
import { computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppButton from '@/Components/AppButton.vue'
import StudentSearchInput from '@/Components/StudentSearchInput.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ offenses: Array })

const form = useForm({
  student_id: null,
  offense_id: '',
  offense_level: '',
  nature_of_offense: '',
  incident_date: '',
  incident_time: '',
  place: '',
  other_witnesses: '',
  narrative: '',
  attachments_note: '',
  interventions_done: '',
  is_bullying: false,
})

const offensesByLevel = computed(() => {
  const g = { 1: [], 2: [], 3: [] }
  for (const o of props.offenses) (g[o.level] ||= []).push(o)
  return g
})

function onOffenseChange() {
  const o = props.offenses.find(x => String(x.id) === String(form.offense_id))
  if (o) form.offense_level = o.level
}

function submit() {
  form.post(route('discipline.cases.store'), { preserveScroll: true })
}
</script>

<template>
  <Head title="File Anecdotal Report" />
  <AdminLayout title="File Anecdotal Report">
    <div class="max-w-3xl mx-auto space-y-5">
      <Link :href="route('discipline.cases.index')" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700">
        <ArrowLeftIcon class="w-4 h-4" /> Back to cases
      </Link>

      <div>
        <h1 class="text-xl font-semibold text-slate-800">Anecdotal Report</h1>
        <p class="text-sm text-slate-500">File a report against a student. The Discipline Office will receive and process it.</p>
      </div>

      <AppCard>
        <form @submit.prevent="submit" class="space-y-5">
          <StudentSearchInput
            v-model="form.student_id"
            search-route-name="discipline.cases.students.search"
            label="Filed Against (Student)"
            :error="form.errors.student_id"
          />

          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Offense (from catalog)</label>
              <select v-model="form.offense_id" @change="onOffenseChange"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">— Select offense —</option>
                <optgroup v-for="lvl in [1,2,3]" :key="lvl" :label="`Level ${lvl}`">
                  <option v-for="o in offensesByLevel[lvl]" :key="o.id" :value="o.id">{{ o.title }}</option>
                </optgroup>
              </select>
            </div>
            <AppSelect v-model="form.offense_level" label="Level" :show-blank="true" placeholder="—">
              <option value="1">Level 1</option>
              <option value="2">Level 2</option>
              <option value="3">Level 3</option>
            </AppSelect>
          </div>

          <AppInput
            v-model="form.nature_of_offense"
            label="Nature of the Offense (if not in catalog)"
            placeholder="Consult Code of Conduct"
            :error="form.errors.nature_of_offense"
          />

          <div class="grid sm:grid-cols-3 gap-4">
            <AppInput v-model="form.incident_date" type="date" label="Date of Incident" />
            <AppInput v-model="form.incident_time" type="text" label="Time of Incident" placeholder="e.g. 10:30 AM" />
            <AppInput v-model="form.place" label="Place" />
          </div>

          <AppInput v-model="form.other_witnesses" label="Other Witnesses" />

          <AppTextarea
            v-model="form.narrative"
            :rows="6"
            label="Narrative Report"
            required
            placeholder="Be specific and detailed in recounting the incident…"
            :error="form.errors.narrative"
          />

          <div>
            <AppTextarea
              v-model="form.attachments_note"
              :rows="2"
              label="Attachments (enumerate pictures/documents, if any)"
            />
            <p class="text-xs text-slate-400 mt-1">You can upload the actual files after filing, from the case page.</p>
          </div>

          <AppTextarea
            v-model="form.interventions_done"
            :rows="2"
            label="Interventions Done"
          />

          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" v-model="form.is_bullying" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
            This incident involves bullying
          </label>

          <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <AppButton as="link" variant="secondary" :href="route('discipline.cases.index')">Cancel</AppButton>
            <AppButton type="submit" :loading="form.processing">
              {{ form.processing ? 'Filing…' : 'File Report' }}
            </AppButton>
          </div>
        </form>
      </AppCard>
    </div>
  </AdminLayout>
</template>
