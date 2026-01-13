<template>
  <Head title="Collections" />
  <AdminLayout title="Collections">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Collections</h1>

      </div>

      <div class="bg-white rounded-xl shadow p-4">
          <div class="flex items-center justify-between mb-4">
              <div class="flex items-center space-x-3">
              <input v-model="q" @keydown.enter="search" type="text" placeholder="Search collections..." class="w-80 rounded-lg border-gray-300 shadow-sm p-2" />
              <select v-model="filterType" @change="applyFilters" class="rounded border p-2">
                <option value="">All Types</option>
                <option>Instructional Materials</option>
                <option>Book</option>
                <option>Periodical</option>
                <option>Non-Print</option>
                <option>Thesis</option>
              </select>
              <select v-model="filterCategory" @change="applyFilters" class="rounded border p-2">
                <option value="">All Categories</option>
                <option v-for="cat in (page.props.categories || [])" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <button @click="openCreate" class="px-4 py-2 bg-blue-600 text-white rounded">New Collection</button>
            </div>
          </div>

        <div class="overflow-x-auto">
          <table class="min-w-full border">
            <thead class="bg-gray-100 text-sm text-gray-700">
              <tr>
                <th class="px-4 py-2">ACCESSION #</th>
                <th class="px-4 py-2"><button @click.prevent="sortBy('call_number')" class="flex items-center gap-2">CALL # <span v-if="sort==='call_number'">{{ direction==='asc' ? '▲' : '▼' }}</span></button></th>
                <th class="px-4 py-2">AUTHOR</th>
                <th class="px-4 py-2"><button @click.prevent="sortBy('title')" class="flex items-center gap-2">TITLE <span v-if="sort==='title'">{{ direction==='asc' ? '▲' : '▼' }}</span></button></th>
                <th class="px-4 py-2"><button @click.prevent="sortBy('collection_type')" class="flex items-center gap-2">COLLECTION TYPE <span v-if="sort==='collection_type'">{{ direction==='asc' ? '▲' : '▼' }}</span></button></th>
                <th class="px-4 py-2"><button @click.prevent="sortBy('edition')" class="flex items-center gap-2">EDITION <span v-if="sort==='edition'">{{ direction==='asc' ? '▲' : '▼' }}</span></button></th>
                <th class="px-4 py-2"><button @click.prevent="sortBy('publisher')" class="flex items-center gap-2">PUBLISHER <span v-if="sort==='publisher'">{{ direction==='asc' ? '▲' : '▼' }}</span></button></th>
                <th class="px-4 py-2"><button @click.prevent="sortBy('year')" class="flex items-center gap-2">YEAR <span v-if="sort==='year'">{{ direction==='asc' ? '▲' : '▼' }}</span></button></th>
                <th class="px-4 py-2">ISBN</th>
                <th class="px-4 py-2">SUBJECT</th>
                <th class="px-4 py-2">CATEGORY</th>
                <th class="px-4 py-2">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              <tr v-for="c in collections.data" :key="c.id">
                <td class="px-4 py-2">{{ c.id }}</td>
                <td class="px-4 py-2">{{ c.call_number || '—' }}</td>
                <td class="px-4 py-2">{{ c.author_publisher || '—' }}</td>
                <td class="px-4 py-2">{{ c.title }}</td>
                <td class="px-4 py-2">{{ c.collection_type || c.volume }}</td>
                <td class="px-4 py-2">{{ c.edition || '—' }}</td>
                <td class="px-4 py-2">{{ c.publisher || '—' }}</td>
                <td class="px-4 py-2">{{ c.year || '—' }}</td>
                <td class="px-4 py-2">{{ c.isbn || '—' }}</td>
                <td class="px-4 py-2">{{ c.subject || '—' }}</td>
                <td class="px-4 py-2">{{ c.category || '—' }}</td>
                <td class="px-4 py-2">
                  <button @click="openEdit(c)" class="p-1 hover:bg-gray-100 rounded" title="Edit">
                    <PencilSquareIcon class="w-5 h-5 text-yellow-600" />
                  </button>
                  <button @click="confirmDelete(c)" class="p-1 hover:bg-gray-100 rounded ml-2" title="Delete">
                    <TrashIcon class="w-5 h-5 text-red-600" />
                  </button>
                </td>
              </tr>
              <tr v-if="(collections.data || []).length === 0"><td :colspan="8" class="px-4 py-6 text-center text-gray-500">No collections</td></tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex items-center justify-between">
          <div class="text-sm text-gray-600">Page {{ collections.current_page }} of {{ collections.last_page }}</div>
          <div class="space-x-2">
            <button @click.prevent="goTo(collections.prev_page_url)" :disabled="!collections.prev_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
            <button @click.prevent="goTo(collections.next_page_url)" :disabled="!collections.next_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

      <!-- Modal (create / edit) -->
      <div v-show="showModal" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-black bg-opacity-50 z-50 overflow-auto">
        <div class="bg-white rounded p-4 w-full max-w-md max-h-[90vh] overflow-auto shadow-lg">
          <h3 class="text-lg font-semibold mb-4">{{ editing ? 'Edit Collection' : 'New Collection' }}</h3>
          <form @submit.prevent="submitForm">

            <div class="space-y-3">
              <div>
                <label class="block text-sm">Collection Type</label>
                <select v-model="form.collection_type" class="w-full rounded border p-2">
                  <option>Instructional Materials</option>
                  <option>Book</option>
                  <option>Periodical</option>
                  <option>Non-Print</option>
                  <option>Thesis</option>
                </select>
                <div v-if="errors.collection_type || errors.volume" class="text-red-600 text-sm mt-1">{{ (errors.collection_type || errors.volume)[0] }}</div>
              </div>
              <div>
                <label class="block text-sm">Title</label>
                <input name="title" v-model="form.title" class="w-full rounded border p-2" />
                <div v-if="errors.title" class="text-red-600 text-sm mt-1">{{ errors.title[0] }}</div>
              </div>
              <div>
                <label class="block text-sm">Author</label>
                <input name="author_publisher" v-model="form.author_publisher" class="w-full rounded border p-2" />
                <div v-if="errors.author_publisher" class="text-red-600 text-sm mt-1">{{ errors.author_publisher[0] }}</div>
              </div>
              
              <div>
                <label class="block text-sm">Call Number</label>
                <input name="call_number" v-model="form.call_number" class="w-full rounded border p-2" />
                <div v-if="errors.call_number" class="text-red-600 text-sm mt-1">{{ errors.call_number[0] }}</div>
              </div>
              <div>
                <label class="block text-sm">Edition</label>
                <input name="edition" v-model="form.edition" class="w-full rounded border p-2" />
                <div v-if="errors.edition" class="text-red-600 text-sm mt-1">{{ errors.edition[0] }}</div>
              </div>
              <div>
                <label class="block text-sm">Year</label>
                <input name="year" v-model="form.year" class="w-full rounded border p-2" />
                <div v-if="errors.year" class="text-red-600 text-sm mt-1">{{ errors.year[0] }}</div>
              </div>
              <div>
                <label class="block text-sm">ISBN</label>
                <input name="isbn" v-model="form.isbn" class="w-full rounded border p-2" />
                <div v-if="errors.isbn" class="text-red-600 text-sm mt-1">{{ errors.isbn[0] }}</div>
              </div>
              <div>
                <label class="block text-sm">Subject</label>
                <input name="subject" v-model="form.subject" class="w-full rounded border p-2" />
                <div v-if="errors.subject" class="text-red-600 text-sm mt-1">{{ errors.subject[0] }}</div>
              </div>
              <div>
                <label class="block text-sm">Category</label>
                <input name="category" v-model="form.category" class="w-full rounded border p-2" />
                <div v-if="errors.category" class="text-red-600 text-sm mt-1">{{ errors.category[0] }}</div>
              </div>
              <div>
                <label class="block text-sm">Publisher</label>
                <input name="publisher" v-model="form.publisher" class="w-full rounded border p-2" />
                <div v-if="errors.publisher" class="text-red-600 text-sm mt-1">{{ errors.publisher[0] }}</div>
              </div>
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
          <div class="mb-4">Are you sure you want to delete <strong>{{ deleting?.title }}</strong>?</div>
          <div class="flex justify-end space-x-2">
            <button @click="showDeleteConfirm=false" class="px-3 py-1 bg-gray-200 rounded">Cancel</button>
            <button @click="deleteCollection" class="px-3 py-1 bg-red-500 text-white rounded">Delete</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>

import { ref, computed } from 'vue';
import { usePage, router, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const collections = computed(() => page.props.collections || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null });
const q = ref(page.props.q || '');
const csrfToken = page.props.csrf_token ?? page.props.csrfToken ?? null;
const sort = ref(page.props.sort || 'title');
const direction = ref(page.props.direction || 'asc');
const filterType = ref(page.props.filters?.collection_type || '');
const filterCategory = ref(page.props.filters?.category || '');

const showModal = ref(false);
const editing = ref(null);
const form = ref({ collection_type: 'Book', title: '', author_publisher: '', call_number: '', edition: '', year: '', isbn: '', subject: '', category: '', publisher: '' });
const errors = ref(page.props.errors || {});
const showDeleteConfirm = ref(false);
const deleting = ref(null);

function openCreate() { editing.value = null; form.value = { collection_type: 'Book', title: '', author_publisher: '', call_number: '', edition: '', year: '', isbn: '', subject: '', category: '', publisher: '' }; errors.value = {}; showModal.value = true }
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
  showModal.value = true
}
function closeModal(){ showModal.value = false; errors.value = {} }

function confirmDelete(c){ deleting.value = c; showDeleteConfirm.value = true }

function applyFilters(){
  router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true })
}

function search(){ applyFilters() }
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
  const payload = { ...form.value }
  // keep compatibility with backend validation which still expects `volume`
  payload.volume = payload.collection_type ?? payload.volume
  if (editing.value) {
    router.put(route('library.collections.update', editing.value), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true }) },
      onError: (e) => { errors.value = e }
    })
  } else {
    router.post(route('library.collections.store'), payload, {
      preserveState: true,
      onSuccess: () => { showModal.value = false; router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true }) },
      onError: (e) => { errors.value = e }
    })
  }
}

function deleteCollection(){
  if (!deleting.value) return
  const id = deleting.value.id
  // close the confirmation modal immediately for a snappier UX
  const previous = deleting.value
  showDeleteConfirm.value = false
  deleting.value = null

  router.delete(route('library.collections.destroy', id), {}, {
    preserveState: true,
    onSuccess: () => {
      router.get(route('library.collections.index'), { q: q.value, collection_type: filterType.value, category: filterCategory.value, sort: sort.value, direction: direction.value }, { replace: true })
    },
    onError: (e) => {
      console.error(e)
      // restore modal / selection so user can retry or see the error
      deleting.value = previous
      showDeleteConfirm.value = true
    }
  })
}

</script>

<style scoped>
.table-auto th, .table-auto td { padding: 0.5rem; }
</style>
