<template>
  <Head title="Borrowings" />
  <AdminLayout title="Borrowings">
    <div class="space-y-5">

      <AppPageHeader title="Borrowings" subtitle="Track collection borrow and return activity.">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Borrowing
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-72">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="q"
            type="text"
            placeholder="Search borrowings..."
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-9 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
          <AppIconButton
            v-if="q"
            label="Clear search"
            size="sm"
            class="absolute right-1 top-1/2 -translate-y-1/2"
            @click="clearSearch"
          >
            <XMarkIcon class="h-4 w-4" />
          </AppIconButton>
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!(borrowings.data || []).length" :skeleton-cols="9">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Collection</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Borrower</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Section</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Borrow Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Due Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Return Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="b in borrowings.data" :key="b.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-slate-700">{{ b.id }}</td>
          <td class="px-4 py-3 text-slate-700">
            <button @click="openCollectionHistory(b.collection?.id)" class="text-indigo-600 hover:text-indigo-800 font-medium hover:underline">{{ b.collection?.title || '—' }}</button>
          </td>
          <td class="px-4 py-3 text-slate-700">
            <button @click="openBorrowerHistory(b.borrower_type, b.borrower_id)" class="text-indigo-600 hover:text-indigo-800 font-medium hover:underline">{{ b.borrower_name || (b.borrower_type + ' #' + b.borrower_id) }}</button>
          </td>
          <td class="px-4 py-3 text-slate-700">{{ b.section_name || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ b.borrow_date }}</td>
          <td class="px-4 py-3 text-slate-700">{{ b.due_date }}</td>
          <td class="px-4 py-3 text-slate-700">{{ b.return_date || '—' }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <AppBadge :color="statusColor(b.status)">{{ b.status }}</AppBadge>
              <AppBadge v-if="isOverdue(b)" color="red">Overdue</AppBadge>
            </div>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton v-if="!b.return_date" :label="'Return ' + (b.collection?.title || '')" variant="success" :disabled="isSubmitting" @click="processReturn(b)">
                <CheckCircleIcon class="h-5 w-5" />
              </AppIconButton>
              <AppIconButton v-if="b.status !== 'Returned'" label="Override due date" variant="warning" @click="openOverride(b)">
                <PencilSquareIcon class="h-5 w-5" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="b in borrowings.data" :key="b.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-500">#{{ b.id }}</p>
                <p class="font-medium text-slate-800">{{ b.collection?.title || '—' }}</p>
                <p class="text-xs text-slate-500">Borrower: <button @click="openBorrowerHistory(b.borrower_type, b.borrower_id)" class="text-indigo-600 hover:underline">{{ b.borrower_name || (b.borrower_type + ' #' + b.borrower_id) }}</button></p>
                <p class="text-xs text-slate-500">Section: {{ b.section_name || '—' }}</p>
                <p class="text-xs text-slate-500">Borrow: {{ b.borrow_date }} &bull; Due: {{ b.due_date }}</p>
                <p class="text-xs text-slate-500">Return: {{ b.return_date || '—' }}</p>
                <div class="flex items-center gap-2 mt-1">
                  <AppBadge :color="statusColor(b.status)">{{ b.status }}</AppBadge>
                  <AppBadge v-if="isOverdue(b)" color="red">Overdue</AppBadge>
                </div>
              </div>
              <div class="flex flex-col items-end gap-1">
                <AppIconButton v-if="!b.return_date" :label="'Return ' + (b.collection?.title || '')" variant="success" :disabled="isSubmitting" @click="processReturn(b)">
                  <CheckCircleIcon class="h-5 w-5" />
                </AppIconButton>
                <AppIconButton v-if="b.status !== 'Returned'" label="Override due date" variant="warning" @click="openOverride(b)">
                  <PencilSquareIcon class="h-5 w-5" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No borrowings found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="borrowings.current_page"
            :total-pages="borrowings.last_page"
            @prev="goTo(borrowings.prev_page_url)"
            @next="goTo(borrowings.next_page_url)"
          />
        </template>
      </AppTable>

    </div>

    <!-- Modal create -->
    <AppModal :show="showModal" title="New Borrowing" @close="closeModal">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Collection ID</label>
          <input ref="collectionRef" v-model="form.collection_id" @input="validateField('collection_id')" @keydown.enter.prevent="onCollectionEnter" type="text" autocomplete="off" :class="['w-full rounded-lg border px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400', fieldErrors.collection_id ? 'border-red-500' : 'border-slate-200']" />
          <p v-if="fieldErrors.collection_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.collection_id }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Borrower Type</label>
          <select v-model="form.borrower_type" @change="validateField('borrower_type')" :class="['w-full rounded-lg border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400', fieldErrors.borrower_type ? 'border-red-500' : 'border-slate-200']">
            <option value="student">Student</option>
            <option value="employee">Employee</option>
          </select>
          <p v-if="fieldErrors.borrower_type" class="mt-1 text-xs text-red-600">{{ fieldErrors.borrower_type }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Borrower ID</label>
          <select v-if="form.borrower_type === 'employee'" ref="borrowerRef" v-model="form.borrower_id" @change="validateField('borrower_id')" :class="['w-full rounded-lg border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400', fieldErrors.borrower_id ? 'border-red-500' : 'border-slate-200']">
            <option value="">-- Select employee --</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
          <input v-else ref="borrowerRef" v-model="form.borrower_id" @input="validateField('borrower_id')" placeholder="PISAY System ID" type="text" autocomplete="off" :class="['w-full rounded-lg border px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400', fieldErrors.borrower_id ? 'border-red-500' : 'border-slate-200']" />
          <p v-if="fieldErrors.borrower_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.borrower_id }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
          <input v-model="form.remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="submitForm">Process Borrow</AppButton>
      </template>
    </AppModal>

    <!-- Collection history modal -->
    <AppModal :show="showHistory" title="Collection Borrowing History" size="2xl" @close="closeHistory">
      <AppTable :loading="historyLoading" :is-empty="!historyLoading && (history || []).length === 0" :skeleton-cols="6" :card="false">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Borrower</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Borrow Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Due Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Return Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
          </tr>
        </template>

        <tr v-for="h in history" :key="h.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-slate-700">{{ h.id }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.borrower_name }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.borrow_date }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.due_date }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.return_date || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.status }}</td>
        </tr>

        <template #empty>
          <EmptyState title="No history found" />
        </template>
      </AppTable>

      <template #footer>
        <AppButton variant="secondary" @click="closeHistory">Close</AppButton>
      </template>
    </AppModal>

    <!-- Borrower history modal -->
    <AppModal :show="showBorrowerHistory" title="Borrower History" size="2xl" @close="closeBorrowerHistory">
      <AppTable :loading="borrowerHistoryLoading" :is-empty="!borrowerHistoryLoading && (borrowerHistory || []).length === 0" :skeleton-cols="6" :card="false">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Collection</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Borrow Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Due Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Return Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
          </tr>
        </template>

        <tr v-for="h in borrowerHistory" :key="h.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-slate-700">{{ h.id }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.collection_title || h.collection?.title || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.borrow_date }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.due_date }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.return_date || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ h.status }}</td>
        </tr>

        <template #empty>
          <EmptyState title="No history found" />
        </template>
      </AppTable>

      <template #footer>
        <AppButton variant="secondary" @click="closeBorrowerHistory">Close</AppButton>
      </template>
    </AppModal>

    <!-- Override Modal -->
    <AppModal :show="showOverride" title="Override Due Date" @close="closeOverride">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Due Date</label>
        <input type="date" v-model="overrideForm.due_date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
      </div>
      <div class="mt-3">
        <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
        <input v-model="overrideForm.remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeOverride">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="submitOverride">Save</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import Swal from 'sweetalert2'
import { ref, nextTick, watch, reactive, computed } from 'vue'
import { usePage, router, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { CheckCircleIcon, PencilSquareIcon, PlusIcon, MagnifyingGlassIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import { useSubmit } from '@/Composables/useSubmit'
import { confirmAction } from '@/Composables/useConfirm.js'

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

function statusColor(status) {
  const map = { Returned: 'green', Borrowed: 'blue', Overdue: 'red' }
  return map[status] ?? 'slate'
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
  const ok = await confirmAction({
    title: 'Mark as returned?',
    text: 'This will mark the borrowing as returned and update the collection status to Available.',
    confirmText: 'Yes, return',
  })
  if (!ok) return
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
