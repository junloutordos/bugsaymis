<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { PencilSquareIcon, TrashIcon, PrinterIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ requests: Array, facilities: Array });
const page = usePage();

const showModal = ref(false);
const form = useForm({
  unit: '',
  activity: '',
  purpose: '',
  venue: [],
  date_start: '',
  date_end: '',
  time_start: '',
  time_end: '',
  equipment: [],
  others: '',
});

const facilityMap = (props.facilities || []).reduce((m, f) => {
  m[f.id] = f.name;
  return m;
}, {});

const venueDisplay = (venue) => {
  if (!venue) return '—';
  const arr = Array.isArray(venue) ? venue : [venue];
  if (arr.length === 0) return '—';
  return arr.map(v => facilityMap[v] ?? v).join(', ');
};

const openModal = (req = null) => {
  if (req) {
    form.reset();
    form.requestor = req.requestor;
    form.unit = req.unit;
    form.activity = req.activity;
    form.purpose = req.purpose;
    form.venue = Array.isArray(req.venue) ? req.venue : (req.venue ? req.venue : []);
    form.equipment = Array.isArray(req.equipment) ? req.equipment : (req.equipment ? req.equipment : []);
    form.others = req.others ?? '';
    form.date_start = req.date_start;
    form.date_end = req.date_end;
    form.time_start = req.time_start ?? '';
    form.time_end = req.time_end ?? '';
  } else {
    form.reset();
  }
  showModal.value = true;
};

const closeModal = () => { showModal.value = false; form.reset(); };

const submit = () => {
  form.post(route('facility-requests.store'), {
    onSuccess: () => {
      closeModal();
    },
    onError: (errors) => {
      const venueErr = errors?.venue ?? page.props.errors?.venue;
      if (venueErr) {
        const text = Array.isArray(venueErr) ? venueErr.join(', ') : venueErr;
        alert(text);
      }
    }
  });
};

const destroy = (req) => {
  if (!confirm('Delete this facility request?')) return;
  import('@inertiajs/vue3').then(({ router }) => {
    router.delete(route('facility-requests.destroy', req.id));
  });
};

const openPrint = (req) => {
  let url;
  try {
    url = route('facility-requests.print', req.id);
  } catch (e) {
    url = `/facility-requests/${req.id}/print`;
  }
  window.open(url, '_blank');
};
</script>

<template>
  <Head title="Facility Requests" />
  <AdminLayout title="Facility Requests">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Facility Requests</h1>
        <button
          v-if="page.props.auth?.user?.role?.name !== 'GSU Head'"
          @click.prevent="openModal()"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          + New Request
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Requestor</th>
              <th class="px-4 py-3 text-left">Unit</th>
              <th class="px-4 py-3 text-left">Activity</th>
              <th class="px-4 py-3 text-left">Date(s)</th>
              <th class="px-4 py-3 text-left">Time(s)</th>
              <th class="px-4 py-3 text-left">Venue</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="req in props.requests" :key="req.id">
              <td class="px-4 py-3">{{ req.id }}</td>
              <td class="px-4 py-3">{{ req.requestor }}</td>
              <td class="px-4 py-3">{{ req.unit ?? '—' }}</td>
              <td class="px-4 py-3">{{ req.activity ?? '—' }}</td>
              <td class="px-4 py-3">{{ req.date_start ? new Date(req.date_start).toLocaleDateString() : '—' }}
                <span v-if="req.date_end"> — {{ new Date(req.date_end).toLocaleDateString() }}</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="req.time_start">{{ (req.time_start || '').slice(0,5) }}</span>
                <span v-if="req.time_end"> <span v-if="req.time_start">—</span> {{ (req.time_end || '').slice(0,5) }}</span>
                <span v-if="!req.time_start && !req.time_end">—</span>
              </td>
              <td class="px-4 py-3">{{ venueDisplay(req.venue) }}</td>
              <td class="px-4 py-3">
                <span v-if="req.status && req.status.includes('Approved')" class="bg-green-100 text-green-800 px-2 py-1 rounded font-semibold">
                  {{ req.status }}
                </span>
                <span v-else-if="req.status === 'Declined'" class="bg-red-100 text-red-800 px-2 py-1 rounded font-semibold">
                  {{ req.status }}
                </span>
                <span v-else>
                  {{ req.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center gap-2 justify-center">
                  <button
                      v-if="page.props.auth?.user?.role?.name === 'Administrator'"
                      @click.prevent="openModal(req)"
                      class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700"
                      title="Edit"
                  >
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>

                  <button
                    v-if="page.props.auth?.user?.role?.name === 'Administrator'"
                    @click.prevent="destroy(req)"
                    class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700"
                    title="Delete"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                  
                  <!-- Print button shown when OCD Approved for Admin or GSU Head -->
                  <button
                    v-if="['Administrator','GSU Head'].includes(page.props.auth?.user?.role?.name) && req.status === 'OCD Approved'"
                    @click.prevent="openPrint(req)"
                    class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700"
                    title="Print"
                  >
                    <PrinterIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="props.requests.length === 0">
              <td colspan="8" class="px-4 py-6 text-center text-gray-500">No facility requests found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Create Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">New Facility Request</h2>
          <div class="space-y-4">
            <!-- Requestor is set automatically from the authenticated user -->

            <!-- Unit is assigned automatically from the requestor's division -->

            <div>
              <label class="block text-sm font-medium text-gray-700">Activity</label>
              <input v-model="form.activity" type="text" class="mt-1 block w-full rounded border-gray-300" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Purpose</label>
              <input v-model="form.purpose" type="text" class="mt-1 block w-full rounded border-gray-300" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                <input v-model="form.date_start" type="date" class="mt-1 block w-full rounded border-gray-300" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">End Date</label>
                <input v-model="form.date_end" type="date" class="mt-1 block w-full rounded border-gray-300" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Start Time</label>
                <input v-model="form.time_start" type="time" class="mt-1 block w-full rounded border-gray-300" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">End Time</label>
                <input v-model="form.time_end" type="time" class="mt-1 block w-full rounded border-gray-300" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Venue</label>
              <select v-model="form.venue" multiple class="mt-1 block w-full rounded border-gray-300">
                <option v-for="f in props.facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Equipment Needed</label>
              <select v-model="form.equipment" multiple class="mt-1 block w-full rounded border-gray-300">
                <option value="Chairs">Chairs</option>
                <option value="Tables">Tables</option>
                <option value="Microphone">Microphone</option>
                <option value="Whiteboard">Whiteboard</option>
                <option value="Projector">Projector</option>
                <option value="Electric Fans">Electric Fans</option>
                <option value="Airconditioner">Airconditioner</option>
                <option value="Trashbins">Trashbins</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Other Equipment (describe)</label>
              <input v-model="form.others" type="text" class="mt-1 block w-full rounded border-gray-300" placeholder="e.g. podium, stage lights" />
            </div>

            <div class="flex gap-2">
              <button @click.prevent="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Submit</button>
              <button @click.prevent="closeModal" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
