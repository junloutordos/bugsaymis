<template>
  <Head title="Collection Categories" />
  <AdminLayout title="Collection Categories">
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Collection Categories</h1>
        <button @click="openCreate" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">New Category</button>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="q" @keydown.enter="search" type="text" placeholder="Search categories..." class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
        </div>

        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Student Days</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee Days</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="c in categories.data" :key="c.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ c.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ c.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ c.student_borrowing_days ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ c.employee_borrowing_days ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <div class="flex items-center gap-1">
                    <button @click="openEdit(c)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit" aria-label="Edit category">
                      <PencilSquareIcon class="w-5 h-5" />
                    </button>
                    <button @click="confirmDelete(c)" :disabled="isSubmitting" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Delete" aria-label="Delete category">
                      <TrashIcon class="w-5 h-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="(categories.data || []).length === 0"><td :colspan="5" class="py-16 text-center text-slate-400 text-sm">No categories</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div v-else class="space-y-3 p-4 sm:hidden">
          <div v-for="c in categories.data" :key="c.id" class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-slate-500">#{{ c.id }}</div>
                <div class="font-semibold text-slate-800">{{ c.name }}</div>
                <div class="text-sm text-slate-600">Student Days: {{ c.student_borrowing_days ?? '—' }}</div>
                <div class="text-sm text-slate-600">Employee Days: {{ c.employee_borrowing_days ?? '—' }}</div>
              </div>
              <div class="flex flex-col items-end space-y-2">
                <div class="flex space-x-1">
                  <button @click="openEdit(c)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit" aria-label="Edit category">
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>
                  <button @click="confirmDelete(c)" :disabled="isSubmitting" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Delete" aria-label="Delete category">
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="(categories.data || []).length === 0" class="py-16 text-center text-slate-400 text-sm">No categories</div>
        </div>

        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click.prevent="goTo(categories.prev_page_url)" :disabled="!categories.prev_page_url" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Prev</button>
          <span>Page {{ categories.current_page }} of {{ categories.last_page }}</span>
          <button @click.prevent="goTo(categories.next_page_url)" :disabled="!categories.next_page_url" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">{{ editing ? 'Edit Category' : 'New Category' }}</h3>
          </div>
          <div class="px-6 py-5">
            <form @submit.prevent="submitForm">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
                <input v-model="form.name" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <div v-if="errors.name" class="text-red-600 text-xs mt-1">{{ errors.name[0] }}</div>
              </div>
              <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Student Borrowing Days</label>
                  <input v-model="form.student_borrowing_days" type="number" min="1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  <div v-if="errors.student_borrowing_days" class="text-red-600 text-xs mt-1">{{ errors.student_borrowing_days[0] }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Employee Borrowing Days</label>
                  <input v-model="form.employee_borrowing_days" type="number" min="1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  <div v-if="errors.employee_borrowing_days" class="text-red-600 text-xs mt-1">{{ errors.employee_borrowing_days[0] }}</div>
                </div>
              </div>
            </form>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" @click.prevent="submitForm" :disabled="isSubmitting" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">{{ isSubmitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </div>
      </div>

      <!-- Delete confirm handled by SweetAlert -->

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import Swal from 'sweetalert2'
import { usePage, router, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useSubmit } from '@/Composables/useSubmit'

const page = usePage()
const categories = computed(() => page.props.categories || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null })
const q = ref(page.props.q || '')
const { isSubmitting, submit } = useSubmit()

const showModal = ref(false)
const editing = ref(null)
const form = ref({ name: '', student_borrowing_days: '', employee_borrowing_days: '' })
const errors = ref({})
const showDeleteConfirm = ref(false)
const deleting = ref(null)

// Responsive: track window width to toggle table vs mobile cards
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 640)
function handleResize() { windowWidth.value = window.innerWidth }

onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

function openCreate(){ editing.value = null; form.value = { name: '', student_borrowing_days: '', employee_borrowing_days: '' }; errors.value = {}; showModal.value = true }
function openEdit(c){ editing.value = c.id; form.value = { name: c.name, student_borrowing_days: c.student_borrowing_days ?? '', employee_borrowing_days: c.employee_borrowing_days ?? '' }; errors.value = {}; showModal.value = true }
function closeModal(){ showModal.value = false; errors.value = {} }
async function confirmDelete(c){
  const res = await Swal.fire({
    title: `Delete ${c.name}?`,
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  })
  if (!res.isConfirmed) return
  const id = c.id
  const previous = deleting.value
  deleting.value = null
  submit.delete(route('library.collection-categories.destroy', id), {
    preserveState: true,
    onSuccess: () => { Swal.fire({ icon: 'success', title: 'Category deleted', timer: 1000, showConfirmButton: false }).then(() => { router.get(route('library.collection-categories.index'), { q: q.value }) }) },
    onError: (e) => { console.error(e); deleting.value = previous; Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
  })
}

async function submitForm(){
  errors.value = {}
  const payload = { ...form.value }
  if (editing.value) {
    submit.put(route('library.collection-categories.update', editing.value), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; Swal.fire({ icon: 'success', title: 'Category updated', timer: 1200, showConfirmButton: false }).then(() => { router.get(route('library.collection-categories.index'), { q: q.value }) }) },
      onError: (e) => { errors.value = e }
    })
  } else {
    submit.post(route('library.collection-categories.store'), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; Swal.fire({ icon: 'success', title: 'Category added', timer: 1200, showConfirmButton: false }).then(() => { router.get(route('library.collection-categories.index'), { q: q.value }) }) },
      onError: (e) => { errors.value = e }
    })
  }
}

function search(){
  router.get(route('library.collection-categories.index'), { q: q.value }, { replace: true })
}

function deleteCategory(){
  if (!deleting.value) return
  const id = deleting.value.id
  const previous = deleting.value
  showDeleteConfirm.value = false
  deleting.value = null
  submit.delete(route('library.collection-categories.destroy', id), {
    preserveState: true,
    onSuccess: () => { Swal.fire({ icon: 'success', title: 'Category deleted', timer: 1000, showConfirmButton: false }).then(() => { router.get(route('library.collection-categories.index'), { q: q.value }) }) },
    onError: (e) => { console.error(e); deleting.value = previous; Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
  })
}

function goTo(url){ if(!url) return; window.location.href = url }

</script>

<style scoped>
.table-auto th, .table-auto td { padding: 0.5rem; }
</style>
