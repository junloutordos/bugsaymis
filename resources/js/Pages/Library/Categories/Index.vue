<template>
  <Head title="Collection Categories" />
  <AdminLayout title="Collection Categories">
    <div class="space-y-5">

      <AppPageHeader title="Collection Categories" subtitle="Manage library collection categories and borrowing day limits.">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Category
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-64">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="q"
            @keydown.enter.prevent="search"
            type="text"
            placeholder="Search categories..."
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <template #actions>
          <AppButton size="sm" @click="search">Search</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!categories.data?.length" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Student Days</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee Days</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="c in categories.data" :key="c.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ c.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-800 font-medium">{{ c.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ c.student_borrowing_days ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ c.employee_borrowing_days ?? '—' }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="Edit category" @click="openEdit(c)">
                <PencilSquareIcon class="w-5 h-5" />
              </AppIconButton>
              <AppIconButton label="Delete category" variant="danger" :disabled="isSubmitting" @click="confirmDelete(c)">
                <TrashIcon class="w-5 h-5" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="c in categories.data" :key="c.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-500">#{{ c.id }}</p>
                <p class="font-semibold text-slate-800">{{ c.name }}</p>
                <p class="text-sm text-slate-600">Student Days: {{ c.student_borrowing_days ?? '—' }}</p>
                <p class="text-sm text-slate-600">Employee Days: {{ c.employee_borrowing_days ?? '—' }}</p>
              </div>
              <div class="flex items-center gap-1">
                <AppIconButton label="Edit category" @click="openEdit(c)">
                  <PencilSquareIcon class="w-5 h-5" />
                </AppIconButton>
                <AppIconButton label="Delete category" variant="danger" :disabled="isSubmitting" @click="confirmDelete(c)">
                  <TrashIcon class="w-5 h-5" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No categories found" />
        </template>

        <template #footer>
          <PaginationControl
            :links="categories.links"
            :current-page="categories.current_page"
            :total-pages="categories.last_page"
            :total="categories.total"
          />
        </template>
      </AppTable>

      <!-- Add/Edit Modal -->
      <AppModal :show="showModal" :title="editing ? 'Edit Category' : 'New Category'" @close="closeModal">
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

        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton :loading="isSubmitting" @click.prevent="submitForm">{{ isSubmitting ? 'Saving…' : 'Save' }}</AppButton>
        </template>
      </AppModal>

      <!-- Delete confirm handled by shared confirm dialog -->

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import Swal from 'sweetalert2'
import { usePage, router, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { useSubmit } from '@/Composables/useSubmit'
import { confirmDelete as confirmDeleteDialog } from '@/Composables/useConfirm.js'

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
  const ok = await confirmDeleteDialog(`Delete ${c.name}? This action cannot be undone.`)
  if (!ok) return
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
