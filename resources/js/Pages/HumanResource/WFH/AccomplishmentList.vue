<template>
  <div>
    <!-- Empty state -->
    <EmptyState v-if="!items.length" title="No accomplishments recorded yet." />

    <!-- List -->
    <ul v-else class="space-y-3">
      <li
        v-for="item in items"
        :key="item.id"
        class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-2"
      >
        <!-- View mode -->
        <div v-if="editingId !== item.id" class="flex items-start justify-between gap-3">
          <!-- Content -->
          <div class="flex-1 min-w-0 space-y-1">
            <div class="flex items-start gap-2 flex-wrap">
              <p class="text-sm text-slate-800 flex-1">{{ item.description || item.title }}</p>
              <AppBadge v-if="item.time_from || item.time_to" color="indigo">
                🕐 {{ item.time_from ? fmtTime(item.time_from) : '—' }} – {{ item.time_to ? fmtTime(item.time_to) : '—' }}
              </AppBadge>
            </div>

            <!-- Proof badge -->
            <div v-if="item.proof_type" class="mt-1">
              <a
                v-if="item.proof_type === 'photo' && item.google_drive_link"
                :href="item.google_drive_link"
                target="_blank"
                class="inline-flex items-center gap-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
              >
                <img :src="driveThumb(item.google_drive_link)"
                     class="w-10 h-10 rounded object-cover border border-indigo-200"
                     referrerpolicy="no-referrer"
                     alt="proof" />
                {{ item.file_name ?? 'View Photo' }}
              </a>

              <a
                v-else-if="item.proof_type === 'link' && item.proof_link"
                :href="item.proof_link"
                target="_blank"
                class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium break-all"
              >
                🔗 View Link
              </a>
            </div>

            <p class="text-xs text-slate-300 mt-1">{{ formatDate(item.created_at) }}</p>
          </div>

          <!-- Action buttons -->
          <div class="flex items-center gap-1 shrink-0">
            <AppIconButton label="Edit accomplishment" size="sm" @click="startEdit(item)">
              <PencilSquareIcon class="h-4 w-4" />
            </AppIconButton>
            <AppIconButton
              label="Delete accomplishment"
              variant="danger"
              size="sm"
              :disabled="deletingId === item.id"
              @click="confirmDelete(item)"
            >
              <TrashIcon class="h-4 w-4" />
            </AppIconButton>
          </div>
        </div>

        <!-- Edit mode -->
        <div v-else class="space-y-3">
          <h5 class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">Editing</h5>

          <!-- Time range -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Time From</label>
              <input v-model="editForm.time_from" type="time"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Time To</label>
              <input v-model="editForm.time_to" type="time"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                :class="{ 'border-danger-400': editErrors.time_to }" />
              <p v-if="editErrors.time_to" class="text-danger-600 text-xs mt-1">{{ editErrors.time_to }}</p>
            </div>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Accomplishment <span class="text-danger-600">*</span></label>
            <textarea
              v-model="editForm.description"
              rows="3"
              maxlength="2000"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full resize-none"
              :class="{ 'border-danger-400': editErrors.description }"
            />
            <p v-if="editErrors.description" class="text-danger-600 text-xs mt-1">{{ editErrors.description }}</p>
          </div>

          <!-- Actions -->
          <div class="flex gap-2">
            <AppButton variant="secondary" block class="flex-1" @click="cancelEdit">Cancel</AppButton>
            <AppButton block class="flex-1" :disabled="savingId === item.id" @click="submitEdit(item)">
              {{ savingId === item.id ? 'Saving…' : 'Save' }}
            </AppButton>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

// ── Props & Emits ─────────────────────────────────────────────────────────────
const props = defineProps({
  items: {
    type:    Array,
    default: () => [],
  },
})

const emit = defineEmits(['deleted', 'updated'])

// ── State ─────────────────────────────────────────────────────────────────────
const deletingId = ref(null)
const editingId  = ref(null)
const savingId   = ref(null)
const editErrors = ref({})
const editForm   = reactive({ description: '', time_from: '', time_to: '' })

// ── Edit ──────────────────────────────────────────────────────────────────────
function startEdit(item) {
  editingId.value       = item.id
  editForm.description  = item.description || item.title || ''
  editForm.time_from    = item.time_from ?? ''
  editForm.time_to      = item.time_to ?? ''
  editErrors.value      = {}
}

function cancelEdit() {
  editingId.value = null
  editErrors.value = {}
}

async function submitEdit(item) {
  editErrors.value = {}

  if (!editForm.description.trim()) {
    editErrors.value = { description: 'Accomplishment is required.' }
    return
  }

  savingId.value = item.id

  try {
    const { data } = await axios.put(route('hr.wfh.accomplishments.update', item.id), {
      description: editForm.description.trim(),
      time_from:   editForm.time_from || null,
      time_to:     editForm.time_to   || null,
    })

    emit('updated', data.accomplishment)
    editingId.value = null
    Swal.fire({ icon: 'success', title: 'Updated.', timer: 1200, showConfirmButton: false })
  } catch (err) {
    const serverErrors = err.response?.data?.errors
    if (serverErrors) {
      editErrors.value = Object.fromEntries(
        Object.entries(serverErrors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    } else {
      Swal.fire('Error', err.response?.data?.message ?? 'Could not update accomplishment.', 'error')
    }
  } finally {
    savingId.value = null
  }
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function confirmDelete(item) {
  const result = await Swal.fire({
    title:              'Delete accomplishment?',
    text:               `"${item.title || item.description}" will be permanently removed.`,
    icon:               'warning',
    showCancelButton:   true,
    confirmButtonColor: '#dc2626',
    confirmButtonText:  'Delete',
    cancelButtonText:   'Cancel',
  })

  if (!result.isConfirmed) return

  deletingId.value = item.id

  try {
    await axios.delete(route('hr.wfh.accomplishments.destroy', item.id))
    emit('deleted', item.id)
    Swal.fire({ icon: 'success', title: 'Deleted.', timer: 1200, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Could not delete accomplishment.', 'error')
  } finally {
    deletingId.value = null
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtTime(t) {
  if (!t) return '—'
  const [h, m] = t.split(':').map(Number)
  const suffix = h >= 12 ? 'PM' : 'AM'
  const hh = h % 12 || 12
  return `${hh}:${String(m).padStart(2, '0')} ${suffix}`
}

function driveThumb(url) {
  if (!url) return ''
  const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/)
  if (match) return route('hr.wfh.photo', { fileId: match[1] })
  return url
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('en-PH', {
    month:  'short',
    day:    'numeric',
    hour:   '2-digit',
    minute: '2-digit',
  })
}
</script>
