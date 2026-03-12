<template>
  <Head title="Borrowings" />
  <AdminLayout title="Borrowings">
    <div>
          <div class="flex items-center justify-between mb-4 gap-2">
            <h1 class="text-2xl font-bold">Borrowings</h1>
            <div class="flex items-center gap-3">
              <div class="relative">
                <input v-model="q" placeholder="Search borrowings..." class="rounded border p-2 pr-8" />
                <button v-if="q" @click="clearSearch" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700" aria-label="Clear search">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 8.586l4.95-4.95a1 1 0 111.414 1.414L11.414 10l4.95 4.95a1 1 0 01-1.414 1.414L10 11.414l-4.95 4.95a1 1 0 01-1.414-1.414L8.586 10 3.636 5.05A1 1 0 015.05 3.636L10 8.586z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
              <button @click="openCreate" class="px-4 py-2 bg-blue-600 text-white rounded">
                New Borrowing</button>
            </div>
          </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full border">
            <thead class="bg-gray-100 text-sm text-gray-700">
              <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Collection</th>
                <th class="px-4 py-2">Borrower</th>
                <th class="px-4 py-2">Section</th>
                <th class="px-4 py-2">Borrow Date</th>
                <th class="px-4 py-2">Due Date</th>
                <th class="px-4 py-2">Return Date</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Actions</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              <tr v-for="b in borrowings.data" :key="b.id">
                  <td class="px-4 py-2">{{ b.id }}</td>
                  <td class="px-4 py-2">
                    <button @click="openCollectionHistory(b.collection?.id)" class="text-blue-600 hover:underline">{{ b.collection?.title || '—' }}</button>
                  </td>
                <td class="px-4 py-2">
                  <button @click="openBorrowerHistory(b.borrower_type, b.borrower_id)" class="text-blue-600 hover:underline">{{ b.borrower_name || (b.borrower_type + ' #' + b.borrower_id) }}</button>
                </td>
                <td class="px-4 py-2">{{ b.section_name || '—' }}</td>
                <td class="px-4 py-2">{{ b.borrow_date }}</td>
                <td class="px-4 py-2">{{ b.due_date }}</td>
                <td class="px-4 py-2">{{ b.return_date || '—' }}</td>
                <td class="px-4 py-2">
                  <div>{{ b.status }}</div>
                  <div v-if="isOverdue(b)" class="mt-1 inline-block px-2 py-0.5 text-xs font-semibold text-white bg-red-600 rounded">Overdue</div>
                </td>
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2">
                    <button v-if="!b.return_date" @click="processReturn(b)" :disabled="isSubmitting" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700 disabled:opacity-50 disabled:cursor-not-allowed" :title="'Return ' + (b.collection?.title || '')">
                      <CheckCircleIcon class="h-5 w-5" />
                    </button>
                    <button v-if="b.status !== 'Returned'" @click="openOverride(b)" class="p-2 rounded-full bg-yellow-100 hover:bg-yellow-200 text-yellow-700" title="Override due date">
                      <PencilSquareIcon class="h-5 w-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="(borrowings.data || []).length === 0"><td :colspan="9" class="px-4 py-6 text-center text-gray-500">No borrowings</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div v-else class="space-y-3 sm:hidden">
          <div v-for="b in borrowings.data" :key="b.id" class="bg-white rounded-lg p-3 border shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-gray-500">#{{ b.id }}</div>
                <div class="font-semibold text-gray-800">{{ b.collection?.title || '—' }}</div>
                <div class="text-sm text-gray-600">Borrower: <button @click="openBorrowerHistory(b.borrower_type, b.borrower_id)" class="text-blue-600 hover:underline">{{ b.borrower_name || (b.borrower_type + ' #' + b.borrower_id) }}</button></div>
                <div class="text-sm text-gray-500">Section: {{ b.section_name || '—' }}</div>
                <div class="text-sm text-gray-500">Borrow: {{ b.borrow_date }} • Due: {{ b.due_date }}</div>
                <div class="text-sm text-gray-500">Return: {{ b.return_date || '—' }} • Status: {{ b.status }}</div>
                <div v-if="isOverdue(b)" class="mt-2 inline-block px-2 py-0.5 text-xs font-semibold text-white bg-red-600 rounded">Overdue</div>
              </div>
              <div class="flex flex-col items-end space-y-2">
                <div class="flex flex-col space-y-2">
                  <button v-if="!b.return_date" @click="processReturn(b)" :disabled="isSubmitting" class="p-2 text-green-600 hover:bg-green-100 rounded disabled:opacity-50 disabled:cursor-not-allowed" :title="'Return ' + (b.collection?.title || '')">
                    <CheckCircleIcon class="h-5 w-5" />
                  </button>
                  <button v-if="b.status !== 'Returned'" @click="openOverride(b)" class="p-2 text-yellow-600 hover:bg-yellow-100 rounded" title="Override due date">
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="(borrowings.data || []).length === 0" class="text-center text-gray-500 py-6">No borrowings</div>
        </div>

        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click.prevent="goTo(borrowings.prev_page_url)" :disabled="!borrowings.prev_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ borrowings.current_page }} of {{ borrowings.last_page }}</span>
          <button @click.prevent="goTo(borrowings.next_page_url)" :disabled="!borrowings.next_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal create -->
      <div v-show="showModal" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded p-4 w-full max-w-md max-h-[90vh] overflow-auto shadow-lg">
          <h3 class="text-lg font-semibold mb-4">New Borrowing</h3>
          <form @submit.prevent="submitForm">
            <div class="space-y-3">
              <div>
                <label class="block text-sm">Collection ID</label>
                <input ref="collectionRef" v-model="form.collection_id" @input="validateField('collection_id')" @keydown.enter.prevent="onCollectionEnter" type="text" autocomplete="off" :class="['w-full rounded p-2', fieldErrors.collection_id ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.collection_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.collection_id }}</p>
              </div>
              <div>
                <label class="block text-sm">Borrower Type</label>
                <select v-model="form.borrower_type" @change="validateField('borrower_type')" :class="['w-full rounded p-2', fieldErrors.borrower_type ? 'border-red-600' : 'border-gray-300']">
                  <option value="student">Student</option>
                  <option value="employee">Employee</option>
                </select>
                <p v-if="fieldErrors.borrower_type" class="mt-1 text-xs text-red-600">{{ fieldErrors.borrower_type }}</p>
              </div>
              <div>
                <label class="block text-sm">Borrower ID</label>
                <select v-if="form.borrower_type === 'employee'" ref="borrowerRef" v-model="form.borrower_id" @change="validateField('borrower_id')" :class="['w-full rounded p-2', fieldErrors.borrower_id ? 'border-red-600' : 'border-gray-300']">
                  <option value="">-- Select employee --</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <input v-else ref="borrowerRef" v-model="form.borrower_id" @input="validateField('borrower_id')" placeholder="PISAY System ID" type="text" autocomplete="off" :class="['w-full rounded p-2', fieldErrors.borrower_id ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.borrower_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.borrower_id }}</p>
              </div>
              <div>
                <label class="block text-sm">Remarks</label>
                <input v-model="form.remarks" class="w-full rounded border p-2" />
              </div>
            </div>

            <div class="flex justify-end mt-4 space-x-2">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
              <button type="submit" @click.prevent="submitForm" :disabled="isSubmitting" class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">{{ isSubmitting ? 'Saving…' : 'Process Borrow' }}</button>
            </div>
          </form>
        </div>
      </div>
      <!-- Collection history modal -->
      <div v-show="showHistory" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded p-4 w-full max-w-2xl max-h-[80vh] overflow-auto shadow-lg">
          <h3 class="text-lg font-semibold mb-4">Collection Borrowing History</h3>
          <div v-if="historyLoading" class="text-sm text-gray-600">Loading...</div>
          <div v-else>
            <table class="min-w-full border text-sm">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-3 py-2">#</th>
                  <th class="px-3 py-2">Borrower</th>
                  <th class="px-3 py-2">Borrow Date</th>
                  <th class="px-3 py-2">Due Date</th>
                  <th class="px-3 py-2">Return Date</th>
                  <th class="px-3 py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="h in history" :key="h.id">
                  <td class="px-3 py-2">{{ h.id }}</td>
                  <td class="px-3 py-2">{{ h.borrower_name }}</td>
                  <td class="px-3 py-2">{{ h.borrow_date }}</td>
                  <td class="px-3 py-2">{{ h.due_date }}</td>
                  <td class="px-3 py-2">{{ h.return_date || '—' }}</td>
                  <td class="px-3 py-2">{{ h.status }}</td>
                </tr>
                <tr v-if="(history || []).length === 0"><td :colspan="6" class="px-3 py-6 text-center text-gray-500">No history</td></tr>
              </tbody>
            </table>
          </div>
          <div class="flex justify-end mt-4">
            <button @click="closeHistory" class="px-4 py-2 bg-gray-300 rounded">Close</button>
          </div>
        </div>
      </div>
      <!-- Borrower history modal -->
      <div v-show="showBorrowerHistory" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded p-4 w-full max-w-2xl max-h-[80vh] overflow-auto shadow-lg">
          <h3 class="text-lg font-semibold mb-4">Borrower History</h3>
          <div v-if="borrowerHistoryLoading" class="text-sm text-gray-600">Loading...</div>
          <div v-else>
            <table class="min-w-full border text-sm">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-3 py-2">#</th>
                  <th class="px-3 py-2">Collection</th>
                  <th class="px-3 py-2">Borrow Date</th>
                  <th class="px-3 py-2">Due Date</th>
                  <th class="px-3 py-2">Return Date</th>
                  <th class="px-3 py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="h in borrowerHistory" :key="h.id">
                  <td class="px-3 py-2">{{ h.id }}</td>
                  <td class="px-3 py-2">{{ h.collection_title || h.collection?.title || '—' }}</td>
                  <td class="px-3 py-2">{{ h.borrow_date }}</td>
                  <td class="px-3 py-2">{{ h.due_date }}</td>
                  <td class="px-3 py-2">{{ h.return_date || '—' }}</td>
                  <td class="px-3 py-2">{{ h.status }}</td>
                </tr>
                <tr v-if="(borrowerHistory || []).length === 0"><td :colspan="6" class="px-3 py-6 text-center text-gray-500">No history</td></tr>
              </tbody>
            </table>
          </div>
          <div class="flex justify-end mt-4">
            <button @click="closeBorrowerHistory" class="px-4 py-2 bg-gray-300 rounded">Close</button>
          </div>
        </div>
      </div>

      <!-- Override Modal -->
      <div v-show="showOverride" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded p-4 w-full max-w-md max-h-[90vh] overflow-auto shadow-lg">
          <h3 class="text-lg font-semibold mb-4">Override Due Date</h3>
          <form @submit.prevent="submitOverride">
            <div>
              <label class="block text-sm">Due Date</label>
              <input type="date" v-model="overrideForm.due_date" class="w-full rounded border p-2" />
            </div>
            <div class="mt-2">
              <label class="block text-sm">Remarks</label>
              <input v-model="overrideForm.remarks" class="w-full rounded border p-2" />
            </div>
            <div class="flex justify-end mt-4">
              <button type="button" @click="closeOverride" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
              <button type="submit" :disabled="isSubmitting" class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">{{ isSubmitting ? 'Saving…' : 'Save' }}</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import Swal from 'sweetalert2'
import { ref, nextTick, watch, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage, router, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CheckCircleIcon, PencilSquareIcon } from "@heroicons/vue/24/outline";
import { useSubmit } from '@/Composables/useSubmit'

const page = usePage()
const borrowings = page.props.borrowings || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null }
const employees = page.props.employees || []
const { isSubmitting, submit } = useSubmit()

const showModal = ref(false)
const showOverride = ref(false)
const showHistory = ref(false)
const history = ref([])
const historyLoading = ref(false)
const showBorrowerHistory = ref(false)
const borrowerHistory = ref([])
const borrowerHistoryLoading = ref(false)
const form = ref({ collection_id: '', borrower_type: 'student', borrower_id: '', remarks: '' })
// Client-side inline validation state
const fieldErrors = reactive({
  collection_id: '',
  borrower_type: '',
  borrower_id: '',
});
const overrideForm = ref({ id: null, due_date: '', remarks: '' })
const collectionRef = ref(null)
const borrowerRef = ref(null)
const employeesRef = ref(null)
const q = ref(page.props.q || '')
let qTimeout = null

// Responsive: track window width to toggle table vs mobile cards
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 640)
function handleResize() { windowWidth.value = window.innerWidth }

onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

watch(q, (val) => {
  if (qTimeout) clearTimeout(qTimeout)
  qTimeout = setTimeout(() => {
    router.get(route('library.borrowings.index'), { q: val }, { replace: true, preserveState: false })
  }, 400)
})

function clearSearch() {
  q.value = ''
  if (qTimeout) { clearTimeout(qTimeout); qTimeout = null }
  router.get(route('library.borrowings.index'), { q: '' }, { replace: true, preserveState: false })
}

async function openCreate(){
  showModal.value = true;
  // reset client-side errors and form
  Object.keys(fieldErrors).forEach(k => fieldErrors[k] = '');
  form.value = { collection_id: '', borrower_type: 'student', borrower_id: '', remarks: '' };
  await nextTick();
  if (collectionRef.value && collectionRef.value.focus) collectionRef.value.focus()
}
function closeModal(){ showModal.value = false; form.value = { collection_id: '', borrower_type: 'student', borrower_id: '', remarks: '' }; Object.keys(fieldErrors).forEach(k => fieldErrors[k] = '') }

function onCollectionEnter(){ if (borrowerRef.value && borrowerRef.value.focus) borrowerRef.value.focus() }

const validateField = (field) => {
  switch (field) {
    case 'collection_id':
      fieldErrors.collection_id = form.value.collection_id && String(form.value.collection_id).trim() ? '' : 'Collection ID is required';
      break;
    case 'borrower_type':
      fieldErrors.borrower_type = form.value.borrower_type ? '' : 'Select borrower type';
      break;
    case 'borrower_id':
      fieldErrors.borrower_id = form.value.borrower_id && String(form.value.borrower_id).trim() ? '' : 'Borrower ID is required';
      break;
  }
};

const validateAll = () => {
  ['collection_id','borrower_type','borrower_id'].forEach(f => validateField(f));
  return !Object.values(fieldErrors).some(v => typeof v === 'string' ? v && v.length > 0 : false);
};

// Return true when borrowing is overdue: has a due_date, not yet returned, and due_date is before today
const isOverdue = (b) => {
  try {
    if (!b) return false
    if (b.return_date) return false
    if (!b.due_date) return false
    // normalize to date only (ignore timezone) by using YYYY-MM-DD
    const due = new Date(b.due_date)
    const today = new Date()
    // set time portion to 00:00:00 for comparison (consider overdue if due < today)
    due.setHours(0,0,0,0)
    today.setHours(0,0,0,0)
    return due < today
  } catch (e) {
    return false
  }
}

watch(() => form.value.borrower_type, (val) => {
  form.value.borrower_id = ''
  // focus borrower field when switching type
  nextTick(() => { if (borrowerRef.value && borrowerRef.value.focus) borrowerRef.value.focus() })
})

function submitForm(){
  // client-side validation
  if (!validateAll()) {
    Swal.fire({ icon: 'error', title: 'Validation failed', text: 'Please fix the highlighted errors before processing the borrow.' });
    return;
  }

  try {
    console.log('Submitting borrowing', form.value)
    submit.post(route('library.borrowings.store'), form.value, {
      onStart: () => console.log('borrow request started'),
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Borrowing processed', timer: 1200, showConfirmButton: false }).then(() => { router.get(route('library.borrowings.index')) }) },
      onError: (errors) => { console.error('borrow error', errors); Swal.fire({ icon: 'error', title: 'Error processing borrowing', text: Object.values(errors || {}).join('\n') || 'Error processing borrowing' }) }
    })
  } catch (e) {
    console.error('submitForm exception', e);
    Swal.fire({ icon: 'error', title: 'Error', text: e.message })
  }
}

async function processReturn(b){
  const res = await Swal.fire({
    title: 'Mark as returned?',
    text: 'This will mark the borrowing as returned and update the collection status to Available.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, return',
    cancelButtonText: 'Cancel'
  })
  if (!res.isConfirmed) return
  submit.post(route('library.borrowings.return', b.id), {}, {
    onSuccess: () => { Swal.fire({ icon: 'success', title: 'Return processed', timer: 1200, showConfirmButton: false }).then(() => { router.get(route('library.borrowings.index'), { q: q.value }) }) },
    onError: (e) => { console.error('return error', e); Swal.fire({ icon: 'error', title: 'Failed to process return' }) }
  })
}

function openOverride(b){ overrideForm.value = { id: b.id, due_date: b.due_date || '', remarks: b.remarks || '' }; showOverride.value = true }
function closeOverride(){ showOverride.value = false; overrideForm.value = { id: null, due_date: '', remarks: '' } }
function submitOverride(){
  submit.post(route('library.borrowings.override', overrideForm.value.id), { due_date: overrideForm.value.due_date, remarks: overrideForm.value.remarks }, {
    onSuccess: () => { closeOverride(); Swal.fire({ icon: 'success', title: 'Due date overridden', timer: 1200, showConfirmButton: false }).then(() => { router.get(route('library.borrowings.index'), { q: q.value }) }) },
    onError: (e) => { console.error('override error', e); Swal.fire({ icon: 'error', title: 'Failed to override due date' }) }
  })
}

async function openCollectionHistory(collectionId){
  if (!collectionId) return;
  showHistory.value = true
  historyLoading.value = true
  history.value = []
  try {
    const res = await fetch(route('library.collections.history', collectionId))
    if (!res.ok) throw new Error('Failed to load history')
    const data = await res.json()
    history.value = data
  } catch (e) {
    console.error('history load error', e)
    history.value = []
  } finally {
    historyLoading.value = false
  }
}

function closeHistory(){ showHistory.value = false; history.value = []; historyLoading.value = false }

async function openBorrowerHistory(borrowerType, borrowerId){
  if (!borrowerType || !borrowerId) return;
  showBorrowerHistory.value = true
  borrowerHistoryLoading.value = true
  borrowerHistory.value = []
  try {
    const res = await fetch(route('library.borrowers.history', { type: borrowerType, id: borrowerId }))
    if (!res.ok) throw new Error('Failed to load borrower history')
    const data = await res.json()
    borrowerHistory.value = data
  } catch (e) {
    console.error('borrower history load error', e)
    borrowerHistory.value = []
  } finally {
    borrowerHistoryLoading.value = false
  }
}

function closeBorrowerHistory(){ showBorrowerHistory.value = false; borrowerHistory.value = []; borrowerHistoryLoading.value = false }

function goTo(url){ if(!url) return; window.location.href = url }

</script>
