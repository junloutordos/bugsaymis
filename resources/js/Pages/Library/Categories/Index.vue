<template>
  <Head title="Collection Categories" />
  <AdminLayout title="Collection Categories">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Collection Categories</h1>
        <button @click="openCreate" class="px-4 py-2 bg-blue-600 text-white rounded">New Category</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="overflow-x-auto">
          <table class="min-w-full border">
            <thead class="bg-gray-100 text-sm text-gray-700">
              <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2">Actions</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              <tr v-for="c in categories" :key="c.id">
                <td class="px-4 py-2">{{ c.id }}</td>
                <td class="px-4 py-2">{{ c.name }}</td>
                <td class="px-4 py-2">
                  <button @click="openEdit(c)" class="p-1 hover:bg-gray-100 rounded" title="Edit" aria-label="Edit category">
                    <PencilSquareIcon class="w-5 h-5 text-yellow-600" />
                  </button>
                  <button @click="confirmDelete(c)" class="p-1 hover:bg-gray-100 rounded ml-2" title="Delete" aria-label="Delete category">
                    <TrashIcon class="w-5 h-5 text-red-600" />
                  </button>
                </td>
              </tr>
              <tr v-if="(categories || []).length === 0"><td :colspan="3" class="px-4 py-6 text-center text-gray-500">No categories</td></tr>
            </tbody>
          </table>
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
            <div class="flex justify-end mt-4 space-x-2">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete confirm -->
      <div v-show="showDeleteConfirm" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white p-4 rounded shadow">
          <div class="mb-4">Are you sure you want to delete <strong>{{ deleting?.name }}</strong>?</div>
          <div class="flex justify-end space-x-2">
            <button @click="showDeleteConfirm=false" class="px-3 py-1 bg-gray-200 rounded">Cancel</button>
            <button @click="deleteCategory" class="px-3 py-1 bg-red-500 text-white rounded">Delete</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage, router, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

const page = usePage()
const categories = computed(() => page.props.categories || [])

const showModal = ref(false)
const editing = ref(null)
const form = ref({ name: '' })
const errors = ref({})
const showDeleteConfirm = ref(false)
const deleting = ref(null)

function openCreate(){ editing.value = null; form.value = { name: '' }; errors.value = {}; showModal.value = true }
function openEdit(c){ editing.value = c.id; form.value = { name: c.name }; errors.value = {}; showModal.value = true }
function closeModal(){ showModal.value = false; errors.value = {} }
function confirmDelete(c){ deleting.value = c; showDeleteConfirm.value = true }

async function submitForm(){
  errors.value = {}
  const payload = { ...form.value }
  if (editing.value) {
    router.put(route('library.collection-categories.update', editing.value), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; router.get(route('library.collection-categories.index')) },
      onError: (e) => { errors.value = e }
    })
  } else {
    router.post(route('library.collection-categories.store'), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; router.get(route('library.collection-categories.index')) },
      onError: (e) => { errors.value = e }
    })
  }
}

function deleteCategory(){
  if (!deleting.value) return
  const id = deleting.value.id
  const previous = deleting.value
  showDeleteConfirm.value = false
  deleting.value = null
  router.delete(route('library.collection-categories.destroy', id), {}, {
    preserveState: true,
    onSuccess: () => { router.get(route('library.collection-categories.index')) },
    onError: (e) => { console.error(e); deleting.value = previous; showDeleteConfirm.value = true }
  })
}

</script>

<style scoped>
.table-auto th, .table-auto td { padding: 0.5rem; }
</style>
