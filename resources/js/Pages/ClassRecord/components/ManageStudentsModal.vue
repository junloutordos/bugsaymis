<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show:            { type: Boolean, default: false },
  classRecordId:   { type: Number,  required: true },
  quarter:         { type: Number,  required: true },
  initialStudents: { type: Array,   default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const students = ref([])
const saving   = ref(false)

watch(() => props.show, (v) => {
  if (v) {
    students.value = props.initialStudents.map((s, i) => ({
      id:              s.id ?? null,
      sequence_number: s.sequence_number ?? (i + 1),
      family_name:     s.family_name ?? '',
      given_name:      s.given_name  ?? '',
      middle_initial:  s.middle_initial ?? '',
      sex:             s.sex ?? 'M',
      student_id:      s.student_id ?? null,
      _delete:         false,
    }))
  }
}, { immediate: true })

function addRow() {
  const nextSeq = students.value.filter(s => !s._delete).length + 1
  students.value.push({
    id: null, sequence_number: nextSeq,
    family_name: '', given_name: '', middle_initial: '', sex: 'M',
    student_id: null, _delete: false,
  })
}

function removeRow(idx) {
  if (students.value[idx].id) {
    students.value[idx]._delete = true
  } else {
    students.value.splice(idx, 1)
  }
  // Re-sequence
  let seq = 1
  students.value.filter(s => !s._delete).forEach(s => { s.sequence_number = seq++ })
}

async function save() {
  const rows = students.value.filter(s => !s._delete && s.family_name && s.given_name)
  if (!rows.length) {
    await Swal.fire('Validation', 'At least one student with name is required.', 'warning')
    return
  }

  saving.value = true
  try {
    await axios.post(
      route('class-records.students.upsert', { classRecord: props.classRecordId, q: props.quarter }),
      { students: rows }
    )
    emit('saved')
    emit('close')
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to save students.', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[85vh] flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
          <h2 class="text-base font-semibold text-slate-800">Manage Student Roster — Quarter {{ quarter }}</h2>
          <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
        </div>

        <div class="overflow-y-auto flex-1">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 sticky top-0">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-12">#</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Family Name</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Given Name</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-20">MI</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-20">Sex</th>
                <th class="px-3 py-2 w-10"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template v-for="(s, idx) in students" :key="idx">
                <tr v-if="!s._delete" class="hover:bg-slate-50/50">
                  <td class="px-3 py-1.5 text-xs text-slate-400">{{ s.sequence_number }}</td>
                  <td class="px-3 py-1.5">
                    <input v-model="s.family_name" type="text" placeholder="Family name"
                      class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="s.given_name" type="text" placeholder="Given name"
                      class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="s.middle_initial" type="text" maxlength="5" placeholder="MI"
                      class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                  </td>
                  <td class="px-3 py-1.5">
                    <select v-model="s.sex"
                      class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                      <option value="M">M</option>
                      <option value="F">F</option>
                    </select>
                  </td>
                  <td class="px-3 py-1.5 text-center">
                    <button @click="removeRow(idx)" class="p-1 rounded hover:bg-red-50 text-slate-400 hover:text-red-600">
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              </template>
              <tr>
                <td colspan="6" class="px-3 py-2">
                  <button @click="addRow"
                    class="inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                    <PlusIcon class="h-4 w-4" /> Add Student
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 flex gap-3 justify-end shrink-0">
          <button @click="$emit('close')"
            class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
          <button @click="save" :disabled="saving"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium">
            {{ saving ? 'Saving…' : 'Save Roster' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
