<template>
  <div>
    <!-- Empty state -->
    <p v-if="!items.length" class="py-16 text-center text-slate-400 text-sm">
      No accomplishments recorded yet.
    </p>

    <!-- List -->
    <ul v-else class="space-y-3">
      <li
        v-for="item in items"
        :key="item.id"
        class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex items-start justify-between gap-3"
      >
        <!-- Content -->
        <div class="flex-1 min-w-0 space-y-1">
          <div class="flex items-start gap-2 flex-wrap">
            <p class="text-sm text-slate-800 flex-1">{{ item.description || item.title }}</p>
            <span v-if="item.time_from || item.time_to"
              class="inline-flex items-center gap-1 text-xs bg-indigo-50 text-indigo-700 rounded px-1.5 py-0.5 font-medium shrink-0">
              🕐 {{ item.time_from ? fmtTime(item.time_from) : '—' }} – {{ item.time_to ? fmtTime(item.time_to) : '—' }}
            </span>
          </div>

          <!-- Proof badge -->
          <div v-if="item.proof_type" class="mt-1">
            <!-- Photo proof -->
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

            <!-- Link proof -->
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

        <!-- Delete button -->
        <button
          @click="confirmDelete(item)"
          :disabled="deletingId === item.id"
          title="Delete accomplishment"
          class="flex-shrink-0 p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-red-500 disabled:opacity-40 transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
               viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
          </svg>
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

// ── Props & Emits ─────────────────────────────────────────────────────────────
const props = defineProps({
  items: {
    type:    Array,
    default: () => [],
  },
})

const emit = defineEmits(['deleted'])

// ── State ─────────────────────────────────────────────────────────────────────
const deletingId = ref(null)

// ── Delete ────────────────────────────────────────────────────────────────────
async function confirmDelete(item) {
  const result = await Swal.fire({
    title:              'Delete accomplishment?',
    text:               `"${item.title}" will be permanently removed.`,
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
  // t may be "HH:MM" or "HH:MM:SS"
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
