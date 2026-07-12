<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ClipboardDocumentCheckIcon, CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  rooms:     Array,
  checkDate: String,
  items:     Object,
  interns:   Array,
  myHall:    String,
})

const date = ref(props.checkDate)
const showModal = ref(null) // room object
const scores = ref({})

function openRoom(room) {
  showModal.value = room
  scores.value = room.check ? { ...room.check.scores } : {}
  // Pre-fill with 0 for missing items
  for (const key of Object.keys(props.items)) {
    if (!scores.value[key]) scores.value[key] = 0
  }
}

function applyDate() {
  router.get(route('rh.housekeeping.index'), { check_date: date.value }, { preserveState: true, replace: true })
}

function submitCheck() {
  router.post(route('rh.housekeeping.store'), {
    rh_room_id: showModal.value.id,
    check_date:  date.value,
    scores:      scores.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { showModal.value = null },
  })
}

function logConforme(check, internId) {
  router.post(route('rh.housekeeping.conforme', check.id), { rh_intern_id: internId }, { preserveScroll: true })
}

const computedAvg = computed(() => {
  if (!scores.value || !Object.keys(scores.value).length) return 0
  const vals = Object.values(scores.value).map(Number).filter(v => v > 0)
  if (!vals.length) return 0
  return vals.reduce((a, b) => a + b, 0) / vals.length
})

const roomInterns = computed(() => {
  if (!showModal.value) return []
  return props.interns.filter(i => i.room_id === showModal.value.id)
})

const scoreClass = (s) => {
  if (!s) return 'bg-slate-100 text-slate-400'
  if (s >= 4) return 'bg-emerald-100 text-emerald-700'
  if (s >= 3) return 'bg-sky-100 text-sky-700'
  if (s >= 2) return 'bg-amber-100 text-amber-700'
  return 'bg-rose-100 text-rose-700'
}

const passClass = (passed) => passed
  ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
  : 'bg-rose-100 text-rose-700 border-rose-200'

const fmtAvg = (n) => n ? Number(n).toFixed(2) : '—'

const hallColor = (h) => h === 'BRH' ? 'indigo' : 'pink'
</script>

<template>
  <Head title="Good Housekeeping" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <AppPageHeader title="Good Housekeeping" subtitle="F-RHU-05 — Daily room inspection checklist">
        <template #actions>
          <input v-model="date" @change="applyDate" type="date"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </template>
      </AppPageHeader>

      <!-- Empty state -->
      <div v-if="!rooms.length" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <EmptyState :icon="ClipboardDocumentCheckIcon" title="No active rooms configured" subtitle="Set up rooms first before running inspections.">
          <a href="/rh/rooms" class="mt-4 inline-block text-sm text-indigo-600 hover:underline font-medium">Go to Rooms →</a>
        </EmptyState>
      </div>

      <!-- Room Grid by Hall -->
      <div v-for="hall in ['BRH', 'GRH']" :key="hall">
        <div v-if="rooms.some(r => r.residence_hall === hall)">
          <h2 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">
            {{ hall === 'BRH' ? 'Boy\'s Residence Hall (BRH)' : 'Girl\'s Residence Hall (GRH)' }}
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-6">
            <button v-for="room in rooms.filter(r => r.residence_hall === hall)"
                    :key="room.id"
                    @click="openRoom(room)"
                    :class="['relative rounded-xl border-2 p-4 text-left transition-all hover:shadow-md',
                      room.check
                        ? (room.check.passed ? 'border-emerald-300 bg-emerald-50' : 'border-rose-300 bg-rose-50')
                        : 'border-slate-200 bg-white hover:border-indigo-300']">
              <div class="flex items-start justify-between mb-2">
                <span class="text-lg font-bold text-slate-700">{{ room.room_number }}</span>
                <CheckCircleIcon v-if="room.check?.passed" class="w-5 h-5 text-emerald-500" />
                <ExclamationCircleIcon v-else-if="room.check && !room.check.passed" class="w-5 h-5 text-rose-500" />
                <ClipboardDocumentCheckIcon v-else class="w-5 h-5 text-slate-300" />
              </div>
              <div v-if="room.check">
                <p :class="['text-xs font-semibold', room.check.passed ? 'text-emerald-700' : 'text-rose-700']">
                  Avg: {{ fmtAvg(room.check.average_score) }}
                </p>
                <p class="text-xs text-slate-500">{{ room.check.inspector }}</p>
              </div>
              <p v-else class="text-xs text-slate-400">Not yet inspected</p>
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Checklist Modal -->
    <AppModal :show="!!showModal" size="lg" @close="showModal = null">
      <template v-if="showModal" #header>
        <div class="flex items-center justify-between flex-1">
          <div>
            <h3 class="text-base font-semibold text-slate-800">
              Room {{ showModal.room_number }} — {{ showModal.residence_hall }}
            </h3>
            <p class="text-xs text-slate-500">{{ date }}</p>
          </div>
          <div v-if="computedAvg > 0"
               :class="['text-sm font-bold px-3 py-1 rounded-full mr-3', computedAvg >= 3 ? 'bg-success-100 text-success-700' : 'bg-danger-100 text-danger-700']">
            Avg: {{ computedAvg.toFixed(2) }}
          </div>
        </div>
      </template>

      <template v-if="showModal">
        <!-- Score grid -->
        <div class="space-y-2 mb-4">
          <div v-for="(label, key) in items" :key="key"
               class="flex items-center justify-between gap-3">
            <span class="text-sm text-slate-700 flex-1">{{ label }}</span>
            <div class="flex gap-1">
              <button v-for="n in [1,2,3,4]" :key="n"
                      @click="scores[key] = n"
                      :class="['w-8 h-8 rounded-lg text-xs font-bold border transition-colors',
                        scores[key] === n
                          ? scoreClass(n) + ' border-transparent'
                          : 'border-slate-200 text-slate-400 hover:bg-slate-50']">
                {{ n }}
              </button>
            </div>
          </div>
        </div>

        <p class="text-xs text-slate-400 mb-4">Scoring: 4=Excellent, 3=Good, 2=Fair, 1=Poor. Passing average ≥ 3.0</p>

        <!-- Conforme section (if check already saved) -->
        <div v-if="showModal.check" class="border-t border-slate-100 pt-4 mb-4">
          <h4 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Intern Conforme</h4>
          <div v-if="roomInterns.length" class="space-y-1">
            <div v-for="dormer in roomInterns" :key="dormer.id" class="flex items-center justify-between">
              <span class="text-sm text-slate-700">{{ dormer.name }}</span>
              <span v-if="showModal.check.conforme_ids?.includes(dormer.id)"
                    class="text-xs text-success-600 font-medium">Signed</span>
              <button v-else @click="logConforme(showModal.check, dormer.id)"
                      class="text-xs text-indigo-600 hover:underline">Record Conforme</button>
            </div>
          </div>
          <p v-else class="text-xs text-slate-400">No active dormers in this room.</p>
        </div>
      </template>

      <template v-if="showModal" #footer>
        <div class="flex gap-3 w-full">
          <AppButton block variant="secondary" @click="showModal = null">Cancel</AppButton>
          <AppButton block :disabled="Object.values(scores).filter(Boolean).length < Object.keys(items).length" @click="submitCheck">
            Save Check
          </AppButton>
        </div>
      </template>
    </AppModal>

  </AdminLayout>
</template>
