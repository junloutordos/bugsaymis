<template>
  <Head title="Collections" />
  <AdminLayout title="Collections">
    <div class="space-y-5">

      <AppPageHeader title="Collections" subtitle="Manage the library's collection catalog.">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Collection
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
            placeholder="Search collections..."
            @keydown.enter="search"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>

        <select v-model="filterType" @change="applyFilters"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Types</option>
          <option>Instructional Materials</option>
          <option>Book</option>
          <option>Periodical</option>
          <option>Non-Print</option>
          <option>Thesis</option>
        </select>

        <select v-model="filterCategory" @change="applyFilters"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Categories</option>
          <option v-for="cat in (page.props.categories || [])" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
        </select>

        <template #actions>
          <AppButton size="sm" @click="search">Search</AppButton>
          <AppButton v-if="q || filterType || filterCategory" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!(collections.data || []).length" :skeleton-cols="12">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Accession #</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">
              <button @click.prevent="sortBy('call_number')" class="flex items-center gap-1">Call # <span v-if="sort==='call_number'">{{ direction==='asc' ? '▲' : '▼' }}</span></button>
            </th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Author</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">
              <button @click.prevent="sortBy('title')" class="flex items-center gap-1">Title <span v-if="sort==='title'">{{ direction==='asc' ? '▲' : '▼' }}</span></button>
            </th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">
              <button @click.prevent="sortBy('collection_type')" class="flex items-center gap-1">Collection Type <span v-if="sort==='collection_type'">{{ direction==='asc' ? '▲' : '▼' }}</span></button>
            </th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">
              <button @click.prevent="sortBy('edition')" class="flex items-center gap-1">Edition <span v-if="sort==='edition'">{{ direction==='asc' ? '▲' : '▼' }}</span></button>
            </th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">
              <button @click.prevent="sortBy('publisher')" class="flex items-center gap-1">Publisher <span v-if="sort==='publisher'">{{ direction==='asc' ? '▲' : '▼' }}</span></button>
            </th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">
              <button @click.prevent="sortBy('year')" class="flex items-center gap-1">Year <span v-if="sort==='year'">{{ direction==='asc' ? '▲' : '▼' }}</span></button>
            </th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">ISBN</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Subject</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Category</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="c in collections.data" :key="c.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-slate-700">{{ c.id }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.call_number || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.author_publisher || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.title }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.collection_type || c.volume }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.edition || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.publisher || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.year || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.isbn || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.subject || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ c.category || '—' }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="Edit collection" @click="openEdit(c)">
                <PencilSquareIcon class="h-5 w-5" />
              </AppIconButton>
              <AppIconButton label="Delete collection" variant="danger" :disabled="isSubmitting" @click="confirmDelete(c)">
                <TrashIcon class="h-5 w-5" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="c in collections.data" :key="c.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-500">#{{ c.id }}</p>
                <p class="font-medium text-slate-800">{{ c.title || '—' }}</p>
                <p class="text-xs text-slate-500">{{ c.author_publisher || '—' }}</p>
                <p class="text-xs text-slate-500">{{ c.call_number || '—' }} &bull; {{ c.collection_type || c.volume || '—' }}</p>
                <p class="text-xs text-slate-500">{{ c.category || '—' }} &bull; {{ c.year || '—' }}</p>
              </div>
              <div class="flex items-center gap-1">
                <AppIconButton label="Edit collection" @click="openEdit(c)">
                  <PencilSquareIcon class="h-5 w-5" />
                </AppIconButton>
                <AppIconButton label="Delete collection" variant="danger" :disabled="isSubmitting" @click="confirmDelete(c)">
                  <TrashIcon class="h-5 w-5" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No collections found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="collections.current_page"
            :total-pages="collections.last_page"
            @prev="goTo(collections.prev_page_url)"
            @next="goTo(collections.next_page_url)"
          />
        </template>
      </AppTable>

    </div>

    <!-- Modal (create / edit) -->
    <AppModal :show="showModal" :title="editing ? 'Edit Collection' : 'New Collection'" @close="closeModal">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Collection Type</label>
          <select v-model="form.collection_type" @change="validateField('collection_type')" :class="['w-full rounded-lg border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400', fieldErrors.collection_type ? 'border-red-500' : 'border-slate-200']">
            <option>Instructional Materials</option>
            <option>Book</option>
            <option>Periodical</option>
            <option>Non-Print</option>
            <option>Thesis</option>
          </select>
          <div v-if="fieldErrors.collection_type" class="text-red-600 text-xs mt-1">{{ fieldErrors.collection_type }}</div>
          <div v-else-if="errors.collection_type || errors.volume" class="text-red-600 text-xs mt-1">{{ (errors.collection_type || errors.volume)[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Title</label>
          <input name="title" v-model="form.title" @input="validateField('title')" :class="['w-full rounded-lg border px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400', fieldErrors.title ? 'border-red-500' : 'border-slate-200']" />
          <div v-if="fieldErrors.title" class="text-red-600 text-xs mt-1">{{ fieldErrors.title }}</div>
          <div v-else-if="errors.title" class="text-red-600 text-xs mt-1">{{ errors.title[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Author</label>
          <input name="author_publisher" v-model="form.author_publisher" @input="validateField('author_publisher')" :class="['w-full rounded-lg border px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400', fieldErrors.author_publisher ? 'border-red-500' : 'border-slate-200']" />
          <div v-if="fieldErrors.author_publisher" class="text-red-600 text-xs mt-1">{{ fieldErrors.author_publisher }}</div>
          <div v-else-if="errors.author_publisher" class="text-red-600 text-xs mt-1">{{ errors.author_publisher[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Call Number</label>
          <input name="call_number" v-model="form.call_number" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <div v-if="errors.call_number" class="text-red-600 text-xs mt-1">{{ errors.call_number[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Edition</label>
          <input name="edition" v-model="form.edition" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <div v-if="errors.edition" class="text-red-600 text-xs mt-1">{{ errors.edition[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
          <input name="year" v-model="form.year" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <div v-if="errors.year" class="text-red-600 text-xs mt-1">{{ errors.year[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">ISBN</label>
          <input name="isbn" v-model="form.isbn" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <div v-if="errors.isbn" class="text-red-600 text-xs mt-1">{{ errors.isbn[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Subject</label>
          <input name="subject" v-model="form.subject" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <div v-if="errors.subject" class="text-red-600 text-xs mt-1">{{ errors.subject[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
          <input name="category" v-model="form.category" @input="validateField('category')" :class="['w-full rounded-lg border px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400', fieldErrors.category ? 'border-red-500' : 'border-slate-200']" />
          <div v-if="fieldErrors.category" class="text-red-600 text-xs mt-1">{{ fieldErrors.category }}</div>
          <div v-else-if="errors.category" class="text-red-600 text-xs mt-1">{{ errors.category[0] }}</div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Publisher</label>
          <input name="publisher" v-model="form.publisher" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <div v-if="errors.publisher" class="text-red-600 text-xs mt-1">{{ errors.publisher[0] }}</div>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="submitForm">Save</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import Swal from 'sweetalert2'

import { ref, computed, reactive } from 'vue';
import { usePage, router, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppButton from '@/Components/AppButton.vue';
import AppIconButton from '@/Components/AppIconButton.vue';
import AppFilterBar from '@/Components/AppFilterBar.vue';
import AppTable from '@/Components/AppTable.vue';
import AppModal from '@/Components/AppModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PaginationControl from '@/Components/PaginationControl.vue';
import { PencilSquareIcon, TrashIcon, PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { useSubmit } from '@/Composables/useSubmit';
import { confirmDelete as confirmDeleteDialog } from '@/Composables/useConfirm.js';

const page = usePage();
const collections = computed(() => page.props.collections || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null });
const q = ref(page.props.q || '');
const { isSubmitting, submit } = useSubmit();
const csrfToken = page.props.csrf_token ?? page.props.csrfToken ?? null;
const sort = ref(page.props.sort || 'title');
const direction = ref(page.props.direction || 'asc');
const filterType = ref(page.props.filters?.collection_type || '');
const filterCategory = ref(page.props.filters?.category || '');

const showModal = ref(false);
const editing = ref(null);
const form = ref({ collection_type: 'Book', title: '', author_publisher: '', call_number: '', edition: '', year: '', isbn: '', subject: '', category: '', publisher: '' });
const errors = ref(page.props.errors || {});

// Client-side inline validation state
const fieldErrors = reactive({
  collection_type: '',
  title: '',
  author_publisher: '',
  category: '',
});
const deleting = ref(null);

function openCreate() { editing.value = null; form.value = { collection_type: 'Book', title: '', author_publisher: '', call_number: '', edition: '', year: '', isbn: '', subject: '', category: '', publisher: '' }; errors.value = {}; Object.keys(fieldErrors).forEach(k => fieldErrors[k] = ''); showModal.value = true }
async function openEdit(c) {
  editing.value = c.id;
  errors.value = {};
  try {
    const res = await fetch(route('library.collections.show', c.id));
    if (!res.ok) throw new Error('Network response was not ok')
    const data = await res.json();
    form.value = {
      collection_type: data.collection_type ?? data.volume,
      title: data.title,
      author_publisher: data.author_publisher,
      call_number: data.call_number ?? '',
      edition: data.edition ?? '',
      year: data.year ?? '',
      isbn: data.isbn ?? '',
      subject: data.subject ?? '',
      category: data.category ?? '',
      publisher: data.publisher ?? ''
    };
  } catch (e) {
    // fallback to passed record if fetch fails
    form.value = { collection_type: c.collection_type ?? c.volume, title: c.title, author_publisher: c.author_publisher, call_number: c.call_number ?? '', edition: c.edition ?? '', year: c.year ?? '', isbn: c.isbn ?? '', subject: c.subject ?? '', category: c.category ?? '', publisher: c.publisher ?? '' };
    console.error('Failed to load latest collection data:', e);
  }
  Object.keys(fieldErrors).forEach(k => fieldErrors[k] = '');
  showModal.value = true
}
function closeModal(){ showModal.value = false; errors.value = {}; Object.keys(fieldErrors).forEach(k => fieldErrors[k] = '') }

const validateField = (field) => {
  switch (field) {
    case 'collection_type':
      fieldErrors.collection_type = form.value.collection_type ? '' : 'Select a collection type';
      break;
    case 'title':
      fieldErrors.title = form.value.title && String(form.value.title).trim() ? '' : 'Title is required';
      break;
    case 'author_publisher':
      fieldErrors.author_publisher = form.value.author_publisher && String(form.value.author_publisher).trim() ? '' : 'Author/Publisher is required';
      break;
    case 'category':
      fieldErrors.category = form.value.category && String(form.value.category).trim() ? '' : 'Category is required';
      break;
  }
};

const validateAll = () => {
  ['collection_type','title','author_publisher','category'].forEach(f => validateField(f));
  return !Object.values(fieldErrors).some(v => typeof v === 'string' ? v && v.length > 0 : false);
};

async function confirmDelete(c){
  deleting.value = c
  const ok = await confirmDeleteDialog(`This will permanently delete "${c.title}".`)
  if (!ok) { deleting.value = null; return }
  // perform delete via Inertia
  const id = c.id
  const previous = deleting.value
  deleting.value = null
  submit.delete(route('library.collections.destroy', id), {
    preserveState: true,
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Collection deleted', timer: 1000, showConfirmButton: false }).then(() => {
        router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true })
      })
    },
    onError: (e) => {
      console.error(e)
      deleting.value = previous
      Swal.fire({ icon: 'error', title: 'Failed to delete' })
    }
  })
}

function applyFilters(){
  router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true })
}

function search(){ applyFilters() }
function clearFilters(){
  q.value = ''
  filterType.value = ''
  filterCategory.value = ''
  router.get(route('library.collections.index'), { q: '', collection_type: '', category: '', sort: sort.value, direction: direction.value }, { replace: true })
}
function goTo(url){ if(!url) return; window.location.href = url }

function sortBy(col){
  if (sort.value === col) {
    direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = col; direction.value = 'asc'
  }
  router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true })
}

async function submitForm(){
  errors.value = {}
  // client-side validation
  if (!validateAll()) {
    Swal.fire({ icon: 'error', title: 'Validation failed', text: 'Please fix the highlighted errors before saving the collection.' });
    return;
  }
  const payload = { ...form.value }
  // keep compatibility with backend validation which still expects `volume`
  payload.volume = payload.collection_type ?? payload.volume
  if (editing.value) {
    submit.put(route('library.collections.update', editing.value), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; Swal.fire({ icon: 'success', title: 'Collection updated', timer: 1200, showConfirmButton: false }).then(() => { router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true }) }) },
      onError: (e) => { errors.value = e }
    })
  } else {
    submit.post(route('library.collections.store'), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; Swal.fire({ icon: 'success', title: 'Collection added', timer: 1200, showConfirmButton: false }).then(() => { router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true }) }) },
      onError: (e) => { errors.value = e }
    })
  }
}

function deleteCollection(){
  if (!deleting.value) return
  const id = deleting.value.id
  // close the confirmation modal immediately for a snappier UX
  const previous = deleting.value
  // kept for compatibility if referenced elsewhere
  deleting.value = null

  submit.delete(route('library.collections.destroy', id), {
    preserveState: true,
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Collection deleted', timer: 1000, showConfirmButton: false }).then(() => { router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true }) })
    },
    onError: (e) => {
      console.error(e)
      // restore modal / selection so user can retry or see the error
      deleting.value = previous
      Swal.fire({ icon: 'error', title: 'Failed to delete' })
    }
  })
}

</script>
