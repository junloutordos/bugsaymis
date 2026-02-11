<template>
  <Head title="Collection Categories" />
  <AdminLayout title="Collection Categories">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Collection Categories</h1>
        <button @click="openCreate" class="px-4 py-2 bg-blue-600 text-white rounded">New Category</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="mb-4">
          <input v-model="q" @keydown.enter="search" type="text" placeholder="Search categories..." class="w-1/3 rounded-lg border-gray-300 shadow-sm p-2" />
        </div>
          <div v-if="!isMobile" class="overflow-x-auto">
            <table class="min-w-full border">
            <thead class="bg-gray-100 text-sm text-gray-700">
              <tr>
                <th class="px-4 py-2 text-center">#</th>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2 text-center">Student Days</th>
                <th class="px-4 py-2 text-center">Employee Days</th>
                <th class="px-4 py-2">Actions</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              <tr v-for="c in categories.data" :key="c.id">
                <td class="px-4 py-2 text-center">{{ c.id }}</td>
                <td class="px-4 py-2">{{ c.name }}</td>
                <td class="px-4 py-2 text-center">{{ c.student_borrowing_days ?? '—' }}</td>
                <td class="px-4 py-2 text-center">{{ c.employee_borrowing_days ?? '—' }}</td>
                <td class="px-4 py-2">
                  <button @click="openEdit(c)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit" aria-label="Edit category">
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>
                  <button @click="confirmDelete(c)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700 ml-2" title="Delete" aria-label="Delete category">
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </td>
              </tr>
              <tr v-if="(categories.data || []).length === 0"><td :colspan="5" class="px-4 py-6 text-center text-gray-500">No categories</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div v-else class="space-y-3 sm:hidden">
          <div v-for="c in categories.data" :key="c.id" class="bg-white rounded-lg p-3 border shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-gray-500">#{{ c.id }}</div>
                <div class="font-semibold text-gray-800">{{ c.name }}</div>
                <div class="text-sm text-gray-600">Student Days: {{ c.student_borrowing_days ?? '—' }}</div>
                <div class="text-sm text-gray-600">Employee Days: {{ c.employee_borrowing_days ?? '—' }}</div>
              </div>
              <div class="flex flex-col items-end space-y-2">
                <div class="flex space-x-2">
                  <button @click="openEdit(c)" class="p-1 hover:bg-gray-100 rounded" title="Edit" aria-label="Edit category">
                    <PencilSquareIcon class="w-5 h-5 text-yellow-600" />
                  </button>
                  <button @click="confirmDelete(c)" class="p-1 hover:bg-gray-100 rounded ml-2" title="Delete" aria-label="Delete category">
                    <TrashIcon class="w-5 h-5 text-red-600" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="(categories.data || []).length === 0" class="text-center text-gray-500 py-6">No categories</div>
        </div>
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click.prevent="goTo(categories.prev_page_url)" :disabled="!categories.prev_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ categories.current_page }} of {{ categories.last_page }}</span>
          <button @click.prevent="goTo(categories.next_page_url)" :disabled="!categories.next_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded p-4 w-full max-w-md shadow-lg">
          <h3 class="text-lg font-semibold mb-4">{{ editing ? 'Edit Category' : 'New Category' }}</h3>
          <form @submit.prevent="submitForm">
                    <div>
                      <label class="block text-sm">Name</label>
                      <input v-model="form.name" class="w-full rounded border p-2" />
                      <div v-if="errors.name" class="text-red-600 text-sm mt-1">{{ errors.name[0] }}</div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-sm">Student Borrowing Days</label>
                        <input v-model="form.student_borrowing_days" type="number" min="1" class="w-full rounded border p-2" />
                        <div v-if="errors.student_borrowing_days" class="text-red-600 text-sm mt-1">{{ errors.student_borrowing_days[0] }}</div>
                      </div>
                      <div>
                        <label class="block text-sm">Employee Borrowing Days</label>
                        <input v-model="form.employee_borrowing_days" type="number" min="1" class="w-full rounded border p-2" />
                        <div v-if="errors.employee_borrowing_days" class="text-red-600 text-sm mt-1">{{ errors.employee_borrowing_days[0] }}</div>
                      </div>
                    </div>
            <div class="flex justify-end mt-4 space-x-2">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
          </form>
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

const page = usePage()
const categories = computed(() => page.props.categories || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null })
const q = ref(page.props.q || '')

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
  router.delete(route('library.collection-categories.destroy', id), {}, {
    preserveState: true,
    onSuccess: () => { Swal.fire({ icon: 'success', title: 'Category deleted', timer: 1000, showConfirmButton: false }).then(() => { router.get(route('library.collection-categories.index'), { q: q.value }) }) },
    onError: (e) => { console.error(e); deleting.value = previous; Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
  })
}

async function submitForm(){
  errors.value = {}
  const payload = { ...form.value }
  if (editing.value) {
    router.put(route('library.collection-categories.update', editing.value), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; Swal.fire({ icon: 'success', title: 'Category updated', timer: 1200, showConfirmButton: false }).then(() => { router.get(route('library.collection-categories.index'), { q: q.value }) }) },
      onError: (e) => { errors.value = e }
    })
  } else {
    router.post(route('library.collection-categories.store'), payload, {
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
  router.delete(route('library.collection-categories.destroy', id), {}, {
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
