<script setup>
import { computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-5">
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
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Level</label>
            <select v-model="form.offense_level"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">—</option>
              <option value="1">Level 1</option>
              <option value="2">Level 2</option>
              <option value="3">Level 3</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Nature of the Offense (if not in catalog)</label>
          <input v-model="form.nature_of_offense" type="text" placeholder="Consult Code of Conduct"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                 :class="{ 'border-red-400': form.errors.nature_of_offense }" />
          <p v-if="form.errors.nature_of_offense" class="text-red-500 text-xs mt-1">{{ form.errors.nature_of_offense }}</p>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date of Incident</label>
            <input v-model="form.incident_date" type="date"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Time of Incident</label>
            <input v-model="form.incident_time" type="text" placeholder="e.g. 10:30 AM"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Place</label>
            <input v-model="form.place" type="text"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Other Witnesses</label>
          <input v-model="form.other_witnesses" type="text"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Narrative Report <span class="text-red-500">*</span></label>
          <textarea v-model="form.narrative" rows="6" placeholder="Be specific and detailed in recounting the incident…"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                    :class="{ 'border-red-400': form.errors.narrative }"></textarea>
          <p v-if="form.errors.narrative" class="text-red-500 text-xs mt-1">{{ form.errors.narrative }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Attachments (enumerate pictures/documents, if any)</label>
          <textarea v-model="form.attachments_note" rows="2"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"></textarea>
          <p class="text-xs text-slate-400 mt-1">You can upload the actual files after filing, from the case page.</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Interventions Done</label>
          <textarea v-model="form.interventions_done" rows="2"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"></textarea>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" v-model="form.is_bullying" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
          This incident involves bullying
        </label>

        <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
          <Link :href="route('discipline.cases.index')"
                class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</Link>
          <button @click="submit" :disabled="form.processing"
                  class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-6 py-2 rounded-lg text-sm font-medium">
            {{ form.processing ? 'Filing…' : 'File Report' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
