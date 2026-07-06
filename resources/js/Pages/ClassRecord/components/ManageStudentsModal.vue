<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'
import { PlusIcon, TrashIcon, ArrowDownTrayIcon, ArrowUpTrayIcon, BoltIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show:            { type: Boolean, default: false },
  classRecordId:   { type: Number,  required: true },
  quarter:         { type: Number,  required: true },
  initialStudents: { type: Array,   default: () => [] },
  subjectType:     { type: String,  default: null },   // 'elective' | 'lecture' | etc.
  sectionId:       { type: Number,  default: null },
})

const emit = defineEmits(['close', 'saved'])

const modalTitle = computed(() => `Manage Student Roster — Quarter ${props.quarter}`)

const students       = ref([])
const saving         = ref(false)
const csvInput       = ref(null)
const csvErrors      = ref([])
const autoPopulating = ref(false)

watch(() => props.show, (v) => {
  if (v) {
    csvErrors.value = []
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
  let seq = 1
  students.value.filter(s => !s._delete).forEach(s => { s.sequence_number = seq++ })
}

// ── Auto-populate from enrollment ─────────────────────────────────────────────
async function autoPopulate() {
  const confirm = await Swal.fire({
    title:             'Auto-populate from Enrollment?',
    text:              'This will replace the current roster with all enrolled students in this section. Continue?',
    icon:              'question',
    showCancelButton:  true,
    confirmButtonText: 'Yes, populate',
    confirmButtonColor: '#4f46e5',
  })
  if (!confirm.isConfirmed) return

  autoPopulating.value = true
  try {
    const { data } = await axios.get(
      route('class-records.students.from-enrollment', { classRecord: props.classRecordId, q: props.quarter })
    )
    // Preserve existing DB IDs by matching sequence_number where possible
    const existingById = Object.fromEntries(
      props.initialStudents.map(s => [s.sequence_number, s.id])
    )
    students.value = (data.students ?? []).map(row => ({
      ...row,
      id: existingById[row.sequence_number] ?? null,
    }))
    csvErrors.value = []
    Swal.fire({ icon: 'success', title: `${data.count} student(s) loaded.`, timer: 1200, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to load from enrollment.', 'error')
  } finally {
    autoPopulating.value = false
  }
}

// ── CSV Import ─────────────────────────────────────────────────────────────────
function onCsvSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  csvErrors.value = []

  const reader = new FileReader()
  reader.onload = (ev) => {
    const lines = ev.target.result.split(/\r?\n/).filter(l => l.trim())
    const parsed = []
    const errs   = []

    lines.forEach((line, idx) => {
      if (idx === 0) return  // skip header
      const cols = line.split(',').map(c => c.trim().replace(/^"|"$/g, ''))
      const [seqRaw, familyName, givenName, middleInitial, sex] = cols
      const seq    = parseInt(seqRaw)
      const rowNum = idx + 1

      if (isNaN(seq) || seq < 1)                        errs.push(`Row ${rowNum}: Invalid sequence number.`)
      if (!familyName?.trim())                           errs.push(`Row ${rowNum}: Family name is required.`)
      if (!givenName?.trim())                            errs.push(`Row ${rowNum}: Given name is required.`)
      if (!['M', 'F'].includes(sex?.trim().toUpperCase())) errs.push(`Row ${rowNum}: Sex must be M or F.`)

      parsed.push({
        id:              null,
        sequence_number: isNaN(seq) ? idx : seq,
        family_name:     (familyName ?? '').toUpperCase().trim(),
        given_name:      (givenName  ?? '').toUpperCase().trim(),
        middle_initial:  (middleInitial ?? '').toUpperCase().trim(),
        sex:             (sex ?? '').toUpperCase().trim(),
        student_id:      null,
        _delete:         false,
      })
    })

    csvErrors.value = errs

    if (!errs.length) {
      // Replace roster with CSV rows; keep any existing DB students that share a sequence_number
      const existing = Object.fromEntries(
        props.initialStudents.map(s => [s.sequence_number, s.id])
      )
      students.value = parsed.map(row => ({
        ...row,
        id: existing[row.sequence_number] ?? null,
      }))
    }
  }
  reader.readAsText(file)
  e.target.value = ''
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
  <AppModal :show="show" :title="modalTitle" size="3xl" body-class="" @close="$emit('close')">

        <!-- Toolbar -->
        <div class="px-6 py-3 border-b border-slate-100 bg-slate-50 flex flex-wrap items-center gap-2 shrink-0">

          <!-- Elective badge -->
          <span v-if="subjectType === 'elective'"
                class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-700 px-2.5 py-1 text-xs font-medium mr-auto">
            ⚡ Elective subject — add students individually
          </span>
          <span v-else class="text-xs text-slate-500 mr-auto">Add students one-by-one, import CSV, or auto-populate from enrollment.</span>

          <!-- Auto-populate (non-elective + has section) -->
          <button v-if="subjectType !== 'elective' && sectionId"
                  @click="autoPopulate"
                  :disabled="autoPopulating"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-300 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-medium transition-colors">
            <BoltIcon class="h-3.5 w-3.5" />
            {{ autoPopulating ? 'Loading…' : 'Auto-populate from Enrollment' }}
          </button>

          <a :href="route('class-records.students.template', { classRecord: classRecordId, q: quarter })"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 text-xs font-medium transition-colors">
            <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Template
          </a>
          <button @click="csvInput?.click()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 text-xs font-medium transition-colors">
            <ArrowUpTrayIcon class="h-3.5 w-3.5" /> CSV
          </button>
          <input ref="csvInput" type="file" accept=".csv" class="hidden" @change="onCsvSelected" />
        </div>

        <!-- CSV errors -->
        <div v-if="csvErrors.length"
          class="mx-6 mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700 space-y-1 shrink-0">
          <p class="font-semibold">Fix these errors before saving:</p>
          <p v-for="e in csvErrors" :key="e">{{ e }}</p>
        </div>

        <!-- Roster table -->
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

    <template #footer>
      <AppButton variant="secondary" @click="$emit('close')">Cancel</AppButton>
      <AppButton :loading="saving" :disabled="saving || csvErrors.length > 0" @click="save">
        {{ saving ? 'Saving…' : 'Save Roster' }}
      </AppButton>
    </template>
  </AppModal>
</template>
