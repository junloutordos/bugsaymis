<script setup>
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { EyeIcon, PrinterIcon, ClockIcon, HeartIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const props = defineProps({ consultations: Object, physicianSchedules: Array });
const page = usePage();

const physicianSchedules = ref(props.physicianSchedules || []);

function formatDate(d) {
  if (!d) return '—'
  try {
    const dt = new Date(d)
    return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
  } catch (e) {
    return d
  }
}

function formatDateTime(d) {
  if (!d) return '—'
  try {
    const dt = new Date(d)
    return dt.toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
  } catch (e) {
    return d
  }
}

const openSchedule = ref(null);
const scheduleForm = useForm({ scheduled_at: '', notes: '' });
const openAppointment = ref(null);
const appointmentForm = useForm({
  consultation_type: 'walk-in',
  scheduled_at: '',
  doctor_schedule_id: null,
  physician_schedule_id: null,
  concerns: [],
  other_concern: '',
  injury_type: '',
  concern: '',
  reason: '',
  requestor_id: null,
  requestor_type: '',
});
const viewConsultation = ref(null);
const openVitals = ref(null);
const vitalsForm = useForm({
  temperature: '',
  pulse_rate: '',
  oxygen: '',
  blood_pressure: '',
  medicine_given: '',
  action_taken: '',
  time_start: '',
  time_out: '',
  date_attended: '',
  disposition: '',
  dispositions: [],
  employee_disposition: '',
  others_disposition: '',
  actions: [],
  head_check_reason: '',
  parent_notified_at: '',
  others_action: '',

});

const openView = (c) => { viewConsultation.value = c };
const closeView = () => { viewConsultation.value = null };
const openVitalsFor = (c) => {
  openVitals.value = c;
  // populate form with existing values when available
  vitalsForm.reset();
  vitalsForm.temperature = c.temperature ?? c.temp ?? '';
  vitalsForm.pulse_rate = c.pulse_rate ?? c.pulse ?? c.heart_rate ?? '';
  vitalsForm.oxygen = c.oxygen ?? c.oxygen_saturation ?? c.spo2 ?? '';
  vitalsForm.blood_pressure = c.blood_pressure ?? c.bp ?? c.bloodpressure ?? '';
  vitalsForm.medicine_given = c.medicine_given ?? c.medicines ?? '';
  // reset structured action fields; keep original action text as fallback
  vitalsForm.actions = [];
  vitalsForm.head_check_reason = '';
  vitalsForm.parent_notified_at = '';
  vitalsForm.others_action = '';
  vitalsForm.action_taken = c.action_taken ?? '';

  // helper: convert various datetime formats into `YYYY-MM-DDTHH:MM` for datetime-local inputs
  const toDatetimeLocal = (val) => {
    if (!val) return '';
    try {
      const d = new Date(val);
      if (isNaN(d.getTime())) return '';
      const pad = (n) => String(n).padStart(2, '0');
      const yyyy = d.getFullYear();
      const mm = pad(d.getMonth() + 1);
      const dd = pad(d.getDate());
      const hh = pad(d.getHours());
      const min = pad(d.getMinutes());
      return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
    } catch (e) {
      return '';
    }
  };

  vitalsForm.time_start = toDatetimeLocal(c.time_start ?? c.time_in ?? '');
  vitalsForm.time_out = toDatetimeLocal(c.time_out ?? c.time_end ?? '');
  vitalsForm.date_attended = toDatetimeLocal(c.date_attended ?? '');

  // Parse structured action_taken into checkbox values when possible
  const parseActions = (text) => {
    if (!text) return [];
    const parts = text.split(';').map(s => s.trim()).filter(Boolean);
    const known = [
      'Student laid/sat in clinic for 20 minutes or less',
      'Student laid/sat in clinic for 20 minutes or more',
      'Temperature Taken',
      'Ice Pack applied to the affected Area',
      'Affected area cleaned',
      'Band aid applied to affected area',
      'Head checkup',
      'Parent/Guardian Notified',
      'Medications Given',
      'Others'
    ];
    const res = [];
    parts.forEach(p => {
      for (const k of known) {
        if (p.toLowerCase().startsWith(k.toLowerCase()) || p.toLowerCase().includes(k.toLowerCase())) {
          if (!res.includes(k)) res.push(k);
          return;
        }
      }
      if (p.toLowerCase().includes('head check')) { if (!res.includes('Head checkup')) res.push('Head checkup'); }
      else if (p.toLowerCase().includes('parent')) { if (!res.includes('Parent/Guardian Notified')) res.push('Parent/Guardian Notified'); }
      else if (p.toLowerCase().includes('medicat') || p.toLowerCase().includes('medic')) { if (!res.includes('Medications Given')) res.push('Medications Given'); }
      else if (!res.includes('Others')) res.push('Others');
    });
    return res;
  };

  if (c.action_taken && (!openVitals.value || openVitals.value.requestor_type !== 'employee')) {
    vitalsForm.actions = parseActions(c.action_taken);
    const at = c.action_taken || '';
    const headMatch = at.match(/Head checkup\s*:?\s*(.+)/i);
    if (headMatch) vitalsForm.head_check_reason = headMatch[1].trim();
    const parentMatch = at.match(/Parent\/Guardian Notified\s*:?\s*(.+)/i);
    if (parentMatch) vitalsForm.parent_notified_at = parentMatch[1].trim();
    const othersMatch = at.match(/Others\s*:?\s*(.+)/i);
    if (othersMatch) vitalsForm.others_action = othersMatch[1].trim();
  }

  // Parse disposition into selections when possible
  vitalsForm.dispositions = [];
  vitalsForm.employee_disposition = '';
  vitalsForm.others_disposition = '';
  if (c.disposition) {
    if (c.requestor_type === 'employee') {
      vitalsForm.employee_disposition = c.disposition;
    } else {
      const dparts = String(c.disposition).split(';').map(s => s.trim()).filter(Boolean);
      const knownD = [
        'Returned to class, feeling better',
        "Returned to class at parent's/guardian's request",
        'Returned to class, unable to contact parent/guardian',
        'Sent home',
        'Teacher Notified',
        'Referral was made to Health Care Provider',
        'Transported to Hospital',
        'Copy of clinic pass sent home',
        'Others'
      ];
      dparts.forEach(dp => {
        for (const kd of knownD) {
          if (dp.toLowerCase().startsWith(kd.toLowerCase()) || dp.toLowerCase().includes(kd.toLowerCase())) {
            if (!vitalsForm.dispositions.includes(kd)) vitalsForm.dispositions.push(kd);
            return;
          }
        }
        if (!vitalsForm.dispositions.includes('Others')) {
          vitalsForm.dispositions.push('Others');
          vitalsForm.others_disposition = dp;
        }
      });
    }
  }
};
const closeVitals = () => { openVitals.value = null; if (vitalsForm.reset) vitalsForm.reset(); };
// Exclusive selection helpers: prevent selecting both 20 minutes less and more
const hasLaidLess = computed(() => (vitalsForm.actions || []).includes('Student laid/sat in clinic for 20 minutes or less'));
const hasLaidMore = computed(() => (vitalsForm.actions || []).includes('Student laid/sat in clinic for 20 minutes or more'));
const removeAction = (val) => {
  vitalsForm.actions = (vitalsForm.actions || []).filter(a => a !== val);
};
const onExclusiveChange = (e) => {
  const val = e.target.value;
  const checked = e.target.checked;
  if (!checked) return;
  if (val === 'Student laid/sat in clinic for 20 minutes or less') {
    removeAction('Student laid/sat in clinic for 20 minutes or more');
  } else if (val === 'Student laid/sat in clinic for 20 minutes or more') {
    removeAction('Student laid/sat in clinic for 20 minutes or less');
  }
};

// Disposition exclusivity helpers
const hasReturnedFeelingBetter = computed(() => (vitalsForm.dispositions || []).includes('Returned to class, feeling better'));
const hasReturnedParentRequest = computed(() => (vitalsForm.dispositions || []).includes("Returned to class at parent's/guardian's request"));
const hasReturnedUnableContact = computed(() => (vitalsForm.dispositions || []).includes('Returned to class, unable to contact parent/guardian'));
const hasSentHome = computed(() => (vitalsForm.dispositions || []).includes('Sent home'));
const hasTransported = computed(() => (vitalsForm.dispositions || []).includes('Transported to Hospital'));

const removeDisposition = (val) => {
  vitalsForm.dispositions = (vitalsForm.dispositions || []).filter(d => d !== val);
};

const onDispositionChange = (e) => {
  const val = e.target.value;
  const checked = e.target.checked;
  if (!checked) return;

  // Mutual exclusivity between the two "returned to class" parent-related options
  if (val === "Returned to class at parent's/guardian's request") {
    removeDisposition('Returned to class, unable to contact parent/guardian');
  }
  if (val === 'Returned to class, unable to contact parent/guardian') {
    removeDisposition("Returned to class at parent's/guardian's request");
  }

  // If sent home or transported, remove any "returned to class" options
  if (val === 'Sent home' || val === 'Transported to Hospital') {
    removeDisposition('Returned to class, feeling better');
    removeDisposition("Returned to class at parent's/guardian's request");
    removeDisposition('Returned to class, unable to contact parent/guardian');
  }

  // If a returned-to-class option is selected, remove sent home / transported
  if (['Returned to class, feeling better', "Returned to class at parent's/guardian's request", 'Returned to class, unable to contact parent/guardian'].includes(val)) {
    removeDisposition('Sent home');
    removeDisposition('Transported to Hospital');
  }
};
const submitVitals = () => {
  if (!openVitals.value) return;

  // If the consultation requestor is an employee, allow free-text action_taken (do not overwrite)
  if (!(openVitals.value && openVitals.value.requestor_type === 'employee')) {
    // assemble action_taken from selected actions and conditional fields
    const parts = [];
    (vitalsForm.actions || []).forEach(a => {
      if (a === 'Head checkup') {
        const reason = vitalsForm.head_check_reason ? `: ${vitalsForm.head_check_reason}` : '';
        parts.push(`Head checkup${reason}`);
      } else if (a === 'Parent/Guardian Notified') {
        const dt = vitalsForm.parent_notified_at ? `: ${vitalsForm.parent_notified_at}` : '';
        parts.push(`Parent/Guardian Notified${dt}`);
      } else if (a === 'Others') {
        const o = vitalsForm.others_action ? `: ${vitalsForm.others_action}` : '';
        parts.push(`Others${o}`);
      } else if (a === 'Medications Given') {
        const med = vitalsForm.medicine_given ? `: ${vitalsForm.medicine_given}` : '';
        parts.push(`Medications Given${med}`);
      } else {
        parts.push(a);
      }
    });

    vitalsForm.action_taken = parts.join('; ');
  }

  // assemble disposition from selected dispositions
  if (openVitals.value && openVitals.value.requestor_type === 'employee') {
    // For employees use the single selected employee_disposition
    vitalsForm.disposition = vitalsForm.employee_disposition || '';
  } else {
    const disp = [];
    (vitalsForm.dispositions || []).forEach(d => {
      if (d === 'Others') {
        const o = vitalsForm.others_disposition ? `: ${vitalsForm.others_disposition}` : '';
        disp.push(`Others${o}`);
      } else {
        disp.push(d);
      }
    });
    vitalsForm.disposition = disp.join('; ');
  }

  // Truncate disposition on the client side as a safety net for DB column limits
  if (vitalsForm.disposition && vitalsForm.disposition.length > 255) {
    vitalsForm.disposition = vitalsForm.disposition.substring(0, 255);
  }

  if (vitalsForm.put) {
    vitalsForm.put(route('consultations.update', openVitals.value.id), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', text: 'Vitals saved.' })
        openVitals.value = null;
        if (vitalsForm.reset) vitalsForm.reset();
      },
    });
  }
};

function confirmDelete(c) {
  Swal.fire({ title: 'Delete consultation?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true }).then((r) => {
    if (r.isConfirmed) {
      router.delete(route('consultations.destroy', c.id), {
        onSuccess: () => {
          Swal.fire({ icon: 'success', title: 'Consultation deleted' }).then(() => {
            router.get(route('consultations.index'), { q: searchQuery.value }, { replace: true })
          })
        },
        onError: () => {
          Swal.fire({ icon: 'error', title: 'Failed to delete consultation' })
        }
      })
    }
  })
}
const statusBadge = (s) => {
  if (!s) return 'px-2 py-1 rounded bg-gray-100 text-gray-700';
  const key = String(s).toLowerCase();
  if (key === 'scheduled') return 'px-2 py-1 bg-green-100 text-green-700 rounded';
  if (key === 'pending') return 'px-2 py-1 bg-yellow-100 text-yellow-700 rounded';
  if (key === 'completed') return 'px-2 py-1 bg-blue-100 text-blue-700 rounded';
  return 'px-2 py-1 bg-red-100 text-red-700 rounded';
};
const openFor = (c) => { openSchedule.value = c; if (scheduleForm.reset) scheduleForm.reset(); };
const openAppointmentFor = (c = null) => { openAppointment.value = c || true; if (appointmentForm.reset) appointmentForm.reset(); };
const submitAppointment = () => {
  // assemble concern string from selected checkboxes similar to kiosk
  const assembled = (appointmentForm.concerns || []).map(c => {
    if (c === 'Others' && appointmentForm.other_concern) return `Others: ${appointmentForm.other_concern}`
    if (c === 'Injury' && appointmentForm.injury_type) return `Injury: ${appointmentForm.injury_type}`
    return c
  }).filter(Boolean).join(', ')
  appointmentForm.concern = assembled
  // backend expects `reason` — set it as well
  appointmentForm.reason = assembled

  // If the logged-in user is Staff, set requestor to the staff user (employee)
  const _role = String(page.props.auth?.user?.role?.name || '').toLowerCase();
  if (_role === 'staff') {
    appointmentForm.requestor_id = page.props.auth?.user?.id;
    appointmentForm.requestor_type = 'employee';
  }

  appointmentForm.post(route('consultations.store'), {
    onSuccess: () => {
      Swal.fire({ icon: 'success', text: 'Appointment created.' })
      openAppointment.value = null;
      if (appointmentForm.reset) appointmentForm.reset();
    },
    onError: (errors) => {
      const msg = Object.values(errors || {}).flat().join(' ')
      if (msg) Swal.fire({ icon: 'error', text: msg })
      else Swal.fire({ icon: 'error', text: 'Failed to create appointment.' })
    }
  })
};
const submitSchedule = () => {
  if (!openSchedule.value) return;
  if (scheduleForm.put) {
    scheduleForm.put(route('consultations.update', openSchedule.value.id), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', text: 'Consultation scheduled.' })
        openSchedule.value = null;
        if (scheduleForm.reset) scheduleForm.reset();
      },
    });
  }
}

// Pagination & search helpers (server-driven)
const consultationsList = computed(() => props.consultations?.data ?? props.consultations ?? []);
const pager = computed(() => page.props.consultations || null);
const prevUrl = computed(() => pager.value?.prev_page_url ?? null);
const nextUrl = computed(() => pager.value?.next_page_url ?? null);
const currentPage = computed(() => pager.value?.current_page ?? null);
const lastPage = computed(() => pager.value?.last_page ?? null);

const searchQuery = ref(page.props.q ?? '');
let searchTimer = null;
const doSearch = (val) => {
  router.get(route('consultations.index'), { q: val }, { replace: true, preserveState: false });
};
watch(searchQuery, (val) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    doSearch(val);
  }, 300);
});

onMounted(() => {
  // noop; keeps parity with other pages if needed
});

const goTo = (url) => { if (!url) return; window.location.href = url };
const roleName = page.props.auth?.user?.role?.name ?? '';
const isStaff = String(roleName).toLowerCase() === 'staff';
</script>

<template>
  <Head title="Consultations" />
  <AdminLayout title="Consultations">
      <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">Consultations</h1>

      <!-- Consultation request form removed as requested -->

      <div class="bg-white rounded-xl shadow p-6">
          <div class="flex items-center justify-between mb-4">
            <input v-model="searchQuery" @keydown.enter.prevent="doSearch(searchQuery)" type="text" placeholder="Search by name or Pisay ID" class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <div>
              <button @click.prevent="openAppointmentFor()" class="px-3 py-2 bg-blue-600 text-white rounded">New Appointment</button>
            </div>
          </div>

          <!-- Appointment Modal (copy of kiosk fields, simplified) -->
          <div v-if="openAppointment" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
              <button class="absolute top-3 right-3" @click="openAppointment = null">✕</button>
              <h3 class="text-lg font-semibold mb-3">New Appointment</h3>
              <form @submit.prevent="submitAppointment" class="space-y-3">
                <!-- PISAY ID removed per request -->

                <div>
                  <label class="block text-sm font-medium">Consultation Type</label>
                  <select v-model="appointmentForm.consultation_type" class="mt-1 block w-full rounded border-gray-300 p-2">
                    <option value="walk-in">Walk-in</option>
                    <option value="scheduled">Scheduled</option>
                  </select>
                </div>

                <div v-if="appointmentForm.consultation_type === 'scheduled'">
                  <label class="block text-sm font-medium">Date & Time</label>
                  <div class="mt-2">
                    <div v-if="physicianSchedules.length === 0" class="text-sm text-gray-500">No physician schedules available.</div>
                    <div v-else>
                      <select v-model="appointmentForm.physician_schedule_id" class="mt-1 block w-full rounded border-gray-300 p-2">
                        <option value="" disabled>Select a schedule</option>
                        <option v-for="s in physicianSchedules" :key="s.id" :value="s.id">{{ formatDate(s.schedule_date) }} — {{ s.time_start }}{{ s.time_end ? ' - ' + s.time_end : '' }}</option>
                      </select>
                    </div>
                  </div>
                  <p v-if="appointmentForm.errors.physician_schedule_id" class="text-red-600 text-sm">{{ appointmentForm.errors.physician_schedule_id }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium">Concern (select all that apply)</label>
                  <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="inline-flex items-center">
                      <input type="checkbox" value="Not feeling well" v-model="appointmentForm.concerns" class="mr-2" />
                      <span>Not feeling well</span>
                    </label>
                    <label class="inline-flex items-center">
                      <input type="checkbox" value="Stomachache" v-model="appointmentForm.concerns" class="mr-2" />
                      <span>Stomachache</span>
                    </label>
                    <label class="inline-flex items-center">
                      <input type="checkbox" value="Headache" v-model="appointmentForm.concerns" class="mr-2" />
                      <span>Headache</span>
                    </label>
                    <label class="inline-flex items-center">
                      <input type="checkbox" value="Toothache" v-model="appointmentForm.concerns" class="mr-2" />
                      <span>Toothache</span>
                    </label>
                    <label class="inline-flex items-center">
                      <input type="checkbox" value="Injury" v-model="appointmentForm.concerns" class="mr-2" />
                      <span>Injury</span>
                    </label>
                    <label class="inline-flex items-center">
                      <input type="checkbox" value="Others" v-model="appointmentForm.concerns" class="mr-2" />
                      <span>Others</span>
                    </label>
                  </div>

                  <div v-if="appointmentForm.concerns.includes('Others')" class="mt-3">
                    <label class="block text-sm font-medium">Please specify</label>
                    <input v-model="appointmentForm.other_concern" type="text" class="mt-1 block w-full rounded border-gray-300" placeholder="Describe other concern" />
                  </div>
                  <div v-if="appointmentForm.concerns.includes('Injury')" class="mt-3">
                    <label class="block text-sm font-medium">Type of injury</label>
                    <input v-model="appointmentForm.injury_type" type="text" class="mt-1 block w-full rounded border-gray-300" placeholder="Describe injury (e.g. cut, sprain)" />
                  </div>
                </div>

                <div class="flex gap-2">
                  <button :disabled="appointmentForm.processing" :aria-busy="appointmentForm.processing" :class="['bg-blue-600 text-white px-4 py-2 rounded inline-flex items-center', appointmentForm.processing ? 'opacity-60 cursor-not-allowed' : '']" class="inline-flex items-center">
                    <svg v-if="appointmentForm.processing" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span v-if="appointmentForm.processing" class="inline-flex items-center">Submitting...</span>
                    <span v-else>Create</span>
                  </button>
                  <button @click.prevent="openAppointment = null" :disabled="appointmentForm.processing" class="px-4 py-2 rounded border" :class="appointmentForm.processing ? 'opacity-50 cursor-not-allowed' : ''">Cancel</button>
                </div>
              </form>
            </div>
          </div>
          <!-- Desktop / larger screens: table -->
          <div class="hidden sm:block">
            <table class="w-full table-auto text-sm">
            <thead class="bg-gray-100 text-gray-700">
              <tr>
                <th class="px-3 py-2 text-left">#</th>
                <th v-if="!isStaff" class="px-3 py-2 text-left">Requestor Name</th>
                <th v-if="!isStaff" class="px-3 py-2 text-left">Sex</th>
                <th class="px-3 py-2 text-left">Selected Date &amp; Time of Appointment</th>
                <th class="px-3 py-2 text-left">Request Type</th>
                <th class="px-3 py-2 text-left">Actual Appointment Date</th>
                <th v-if="!isStaff" class="px-3 py-2 text-left">Grade Level</th>
                <th v-if="!isStaff" class="px-3 py-2 text-left">Section</th>
                <th v-if="!isStaff" class="px-3 py-2 text-left">Office</th>
                <th class="px-3 py-2 text-left">Status</th>
                <th class="px-3 py-2 text-left">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in consultationsList" :key="c.id" class="border-t">
                <td class="px-3 py-2">{{ c.id }}</td>
                <td v-if="!isStaff" class="px-3 py-2">{{ c.requestor_data?.name || c.requestor || '—' }}</td>
                <td v-if="!isStaff" class="px-3 py-2">{{ c.requestor_data?.sex || '—' }}</td>
                <td class="px-3 py-2">{{ c.selected_schedule_display || c.date_scheduled_display || '—' }}</td>
                <td class="px-3 py-2">{{ c.consultation_type || c.type || '—' }}</td>
                <td class="px-3 py-2">
                  <span v-if="String(c.status).toLowerCase() !== 'pending'">{{ c.actual_scheduled_display || formatDateTime(c.scheduled_at) || '—' }}</span>
                  <span v-else>—</span>
                </td>
                <td v-if="!isStaff" class="px-3 py-2">{{ c.requestor_data?.grade_level || '—' }}</td>
                <td v-if="!isStaff" class="px-3 py-2">{{ c.requestor_data?.section || '—' }}</td>
                <td v-if="!isStaff" class="px-3 py-2">{{ c.requestor_data?.office || '—' }}</td>
                <td class="px-3 py-2"><span :class="statusBadge(c.status)">{{ c.status }}</span></td>
                <td class="px-3 py-2">
                  <div class="flex gap-2">
                    <button @click.prevent="openView(c)" class="p-2 bg-gray-100 text-gray-700 rounded" aria-label="View">
                      <EyeIcon class="h-5 w-5" />
                    </button>
                    <a v-if="String(c.status).toLowerCase() === 'completed'" :href="route('consultations.print', c.id)" target="_blank" class="p-2 bg-white text-gray-700 rounded" aria-label="Print">
                      <PrinterIcon class="h-5 w-5" />
                    </a>
                    <button v-if="String(c.status).toLowerCase() === 'completed' && ['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name)" @click.prevent="openVitalsFor(c)" class="p-2 bg-yellow-100 text-yellow-700 rounded" aria-label="Edit Vitals">
                      <PencilIcon class="h-5 w-5" />
                    </button>
                    <button v-if="['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name) && String(c.status).toLowerCase() !== 'completed'" @click.prevent="confirmDelete(c)" class="p-2 bg-red-100 text-red-700 rounded" aria-label="Delete">
                      <TrashIcon class="h-5 w-5" />
                    </button>
                    <button v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && !['active','completed'].includes(String(c.status).toLowerCase())" @click.prevent="openFor(c)" class="p-2 bg-indigo-100 text-indigo-700 rounded" aria-label="Schedule">
                      <ClockIcon class="h-5 w-5" />
                    </button>
                    <button type="button" v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && ['active','scheduled'].includes(String(c.status).toLowerCase())" @click="openVitalsFor(c)" class="p-2 bg-green-100 text-green-700 rounded" aria-label="Record Vitals">
                      <HeartIcon class="h-5 w-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="consultationsList.length === 0"><td :colspan="(isStaff ? 6 : 9)" class="px-3 py-6 text-center text-gray-500">No consultations found.</td></tr>
            </tbody>
          </table>
          </div>

          <!-- Mobile / small screens: stacked cards -->
          <div class="sm:hidden space-y-3">
            <div v-for="c in consultationsList" :key="'card-'+c.id" class="bg-white border rounded-lg p-4">
              <div class="flex justify-between items-start">
                <div>
                  <div class="text-sm text-gray-600">#{{ c.id }}</div>
                  <div class="font-medium">{{ c.requestor_data?.name || c.requestor || '—' }}</div>
                  <div class="text-xs text-gray-500">{{ c.requestor_data?.sex || '—' }}</div>
                </div>
                <div class="text-right text-sm">
                  <div class="">{{ c.selected_schedule_display || c.date_scheduled_display || '—' }}</div>
                  <div class="text-xs text-gray-500">{{ c.consultation_type || c.type || '—' }}</div>
                </div>
              </div>
              <div class="mt-3 text-sm text-gray-700">
                <div><strong>Appointment:</strong> <span>{{ String(c.status).toLowerCase() !== 'pending' ? (c.actual_scheduled_display || formatDateTime(c.scheduled_at) || '—') : '—' }}</span></div>
                <div><strong>Grade/Section:</strong> <span>{{ c.requestor_data?.grade_level || '—' }} {{ c.requestor_data?.section ? '· ' + c.requestor_data.section : '' }}</span></div>
                <div><strong>Office:</strong> <span>{{ c.requestor_data?.office || '—' }}</span></div>
                <div class="mt-2"><span :class="statusBadge(c.status)">{{ c.status }}</span></div>
              </div>
                <div class="mt-3 flex gap-2">
                <button @click.prevent="openView(c)" class="px-3 py-2 bg-gray-100 rounded text-sm">View</button>
                <a v-if="String(c.status).toLowerCase() === 'completed'" :href="route('consultations.print', c.id)" target="_blank" class="px-3 py-2 bg-white rounded text-sm">Print</a>
                <button v-if="String(c.status).toLowerCase() === 'completed' && ['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name)" @click.prevent="openVitalsFor(c)" class="px-3 py-2 bg-yellow-100 rounded text-sm">Edit Vitals</button>
                <button v-if="['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name) && String(c.status).toLowerCase() !== 'completed'" @click.prevent="confirmDelete(c)" class="px-3 py-2 bg-red-100 rounded text-sm">Delete</button>
                <button v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && !['active','completed'].includes(String(c.status).toLowerCase())" @click.prevent="openFor(c)" class="px-3 py-2 bg-indigo-100 rounded text-sm">Schedule</button>
                <button v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && ['active','scheduled'].includes(String(c.status).toLowerCase())" @click="openVitalsFor(c)" class="px-3 py-2 bg-green-100 rounded text-sm">Record Vitals</button>
              </div>
            </div>
            <div v-if="consultationsList.length === 0" class="text-center text-gray-500">No consultations found.</div>
          </div>
      </div>

      <!-- Pagination controls (centered) -->
      <div class="mt-4">
        <div class="flex items-center justify-center space-x-3">
          <button @click.prevent="goTo(prevUrl)" :disabled="!prevUrl" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <div class="text-sm text-gray-600">Page {{ currentPage }} of {{ lastPage }}</div>
          <button @click.prevent="goTo(nextUrl)" :disabled="!nextUrl" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Schedule Modal -->
      <div v-if="openSchedule" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3" @click="openSchedule = null">✕</button>
          <h3 class="text-lg font-semibold mb-3">Schedule Consultation</h3>
          <form @submit.prevent="submitSchedule" class="space-y-3">
            <div>
              <label class="block text-sm font-medium">Date & Time</label>
              <input type="datetime-local" v-model="scheduleForm.scheduled_at" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="scheduleForm.errors.scheduled_at" class="text-red-600 text-sm">{{ scheduleForm.errors.scheduled_at }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium">Notes</label>
              <textarea v-model="scheduleForm.notes" class="mt-1 block w-full rounded border-gray-300" rows="3"></textarea>
            </div>
            <div class="flex gap-2">
              <button :disabled="scheduleForm.processing" class="bg-blue-600 text-white px-4 py-2 rounded disabled:opacity-60 inline-flex items-center justify-center">
                <span v-if="scheduleForm.processing" class="inline-flex items-center">
                  <svg class="animate-spin mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                  </svg>
                  Saving...
                </span>
                <span v-else>Save</span>
              </button>
              <button @click.prevent="openSchedule = null" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <!-- View Modal (Consultation Details) -->
      <div v-if="viewConsultation" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3 text-gray-600 hover:text-gray-900" @click="closeView">✕</button>
          <h2 class="text-lg font-semibold mb-3">Consultation Details</h2>
          <div class="bg-white shadow rounded p-4">
            <div class="flex items-start gap-4 mb-4">
              <div>
                <img v-if="viewConsultation.requestor_data?.photo" :src="viewConsultation.requestor_data.photo" alt="Profile" class="w-24 h-24 object-cover rounded border" />
                <div v-else class="w-24 h-24 bg-gray-100 rounded border flex items-center justify-center text-sm text-gray-500">No photo</div>
              </div>
              <div>
                <p><strong>ID:</strong> {{ viewConsultation.id }}</p>
                <p><strong>Requestor:</strong> {{ viewConsultation.requestor_data?.name || viewConsultation.requestor || '—' }}</p>
                <p class="text-sm text-gray-500">{{ viewConsultation.requestor_data?.grade_level || '' }} {{ viewConsultation.requestor_data?.section ? '· ' + viewConsultation.requestor_data.section : '' }}</p>
                <p class="mt-2"><strong>Type:</strong> {{ viewConsultation.consultation_type || viewConsultation.type || '—' }}</p>
              </div>
            </div>

                  <!-- Vitals Edit Modal (moved out so it can open independently) -->

            <p><strong>Concern:</strong> {{ viewConsultation.concern || viewConsultation.reason || '—' }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
              <div>
                <p><strong>Temperature:</strong> {{ viewConsultation.temperature || viewConsultation.temp || viewConsultation.body_temperature || '—' }}</p>
              </div>
              <div>
                <p><strong>Pulse Rate:</strong> {{ viewConsultation.pulse_rate || viewConsultation.pulse || viewConsultation.heart_rate || '—' }}</p>
              </div>
              <div>
                <p><strong>Oxygen:</strong> {{ viewConsultation.oxygen || viewConsultation.oxygen_saturation || viewConsultation.spo2 || '—' }}</p>
              </div>
              <div>
                <p><strong>Blood Pressure:</strong> {{ viewConsultation.blood_pressure || viewConsultation.bp || viewConsultation.bloodpressure || '—' }}</p>
              </div>
            </div>

            <p><strong>Action Taken:</strong> {{ viewConsultation.action_taken || '—' }}</p>
            <p><strong>Medicine Given:</strong> {{ viewConsultation.medicine_given || viewConsultation.medicines || '—' }}</p>
            <p><strong>Time Out:</strong> {{ viewConsultation.time_out || '—' }}</p>
            <p><strong>Disposition:</strong> {{ viewConsultation.disposition || '—' }}</p>
          </div>
        </div>
      </div>

      <!-- Vitals Edit Modal (global) -->
      <div v-if="openVitals" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl sm:max-w-3xl p-6 relative max-h-[90vh] overflow-auto">
          <button class="absolute top-3 right-3 text-gray-600 hover:text-gray-900" @click="closeVitals">✕</button>
          <h3 class="text-lg font-semibold mb-3">Record Vitals / Treatment</h3>
          <form @submit.prevent="submitVitals" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div>
                <label class="block text-sm font-medium">Temperature</label>
                <input type="number" step="0.1" v-model="vitalsForm.temperature" class="mt-1 block w-full rounded border-gray-300" />
                <p v-if="vitalsForm.errors.temperature" class="text-red-600 text-sm">{{ vitalsForm.errors.temperature }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium">Pulse Rate</label>
                <input type="number" v-model="vitalsForm.pulse_rate" class="mt-1 block w-full rounded border-gray-300" />
                <p v-if="vitalsForm.errors.pulse_rate" class="text-red-600 text-sm">{{ vitalsForm.errors.pulse_rate }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium">Oxygen (SpO2)</label>
                <input type="number" v-model="vitalsForm.oxygen" class="mt-1 block w-full rounded border-gray-300" />
                <p v-if="vitalsForm.errors.oxygen" class="text-red-600 text-sm">{{ vitalsForm.errors.oxygen }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium">Blood Pressure</label>
                <input type="text" v-model="vitalsForm.blood_pressure" class="mt-1 block w-full rounded border-gray-300" />
                <p v-if="vitalsForm.errors.blood_pressure" class="text-red-600 text-sm">{{ vitalsForm.errors.blood_pressure }}</p>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium">Medicine Given</label>
              <textarea v-model="vitalsForm.medicine_given" class="mt-1 block w-full rounded border-gray-300" rows="2"></textarea>
              <p v-if="vitalsForm.errors.medicine_given" class="text-red-600 text-sm">{{ vitalsForm.errors.medicine_given }}</p>
            </div>

                    <div>
                      <label class="block text-sm font-medium">Action Taken</label>
                      <div class="mt-2">
                        <template v-if="openVitals && openVitals.requestor_type === 'employee'">
                          <textarea v-model="vitalsForm.action_taken" class="mt-1 block w-full rounded border-gray-300" rows="4"></textarea>
                        </template>
                        <template v-else>
                          <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label class="inline-flex items-center">
                              <input type="checkbox" value="Student laid/sat in clinic for 20 minutes or less" v-model="vitalsForm.actions" @change="onExclusiveChange" :disabled="hasLaidMore" class="mr-2" />
                              <span>Student laid/sat in clinic for 20 minutes or less</span>
                            </label>
                            <label class="inline-flex items-center">
                              <input type="checkbox" value="Student laid/sat in clinic for 20 minutes or more" v-model="vitalsForm.actions" @change="onExclusiveChange" :disabled="hasLaidLess" class="mr-2" />
                              <span>Student laid/sat in clinic for 20 minutes or more</span>
                            </label>
                            <label class="inline-flex items-center">
                              <input type="checkbox" value="Temperature Taken" v-model="vitalsForm.actions" class="mr-2" />
                              <span>Temperature Taken</span>
                            </label>
                            <label class="inline-flex items-center">
                              <input type="checkbox" value="Ice Pack applied to the affected Area" v-model="vitalsForm.actions" class="mr-2" />
                              <span>Ice Pack applied to the affected Area</span>
                            </label>
                            <label class="inline-flex items-center">
                              <input type="checkbox" value="Affected area cleaned" v-model="vitalsForm.actions" class="mr-2" />
                              <span>Affected area cleaned</span>
                            </label>
                            <label class="inline-flex items-center">
                              <input type="checkbox" value="Band aid applied to affected area" v-model="vitalsForm.actions" class="mr-2" />
                              <span>Band aid applied to affected area</span>
                            </label>
                            <div>
                              <label class="inline-flex items-center">
                                <input type="checkbox" value="Head checkup" v-model="vitalsForm.actions" class="mr-2" />
                                <span>Head checkup</span>
                              </label>
                              <div v-if="vitalsForm.actions.includes('Head checkup')" class="mt-2">
                                <label class="block text-sm">Reason for head checkup</label>
                                <input type="text" v-model="vitalsForm.head_check_reason" class="mt-1 block w-full rounded border-gray-300" />
                              </div>
                            </div>
                            <div>
                              <label class="inline-flex items-center">
                                <input type="checkbox" value="Parent/Guardian Notified" v-model="vitalsForm.actions" class="mr-2" />
                                <span>Parent/Guardian Notified</span>
                              </label>
                              <div v-if="vitalsForm.actions.includes('Parent/Guardian Notified')" class="mt-2">
                                <label class="block text-sm">Date & Time notified</label>
                                <input type="datetime-local" v-model="vitalsForm.parent_notified_at" class="mt-1 block w-full rounded border-gray-300" />
                              </div>
                            </div>
                            <div>
                              <label class="inline-flex items-center">
                                <input type="checkbox" value="Medications Given" v-model="vitalsForm.actions" class="mr-2" />
                                <span>Medications Given</span>
                              </label>
                              <div v-if="vitalsForm.actions.includes('Medications Given')" class="mt-2">
                                <label class="block text-sm">Medications (describe)</label>
                                <textarea v-model="vitalsForm.medicine_given" class="mt-1 block w-full rounded border-gray-300" rows="2"></textarea>
                              </div>
                            </div>
                            <div>
                              <label class="inline-flex items-center">
                                <input type="checkbox" value="Others" v-model="vitalsForm.actions" class="mr-2" />
                                <span>Others</span>
                              </label>
                              <div v-if="vitalsForm.actions.includes('Others')" class="mt-2">
                                <label class="block text-sm">Please specify</label>
                                <input type="text" v-model="vitalsForm.others_action" class="mt-1 block w-full rounded border-gray-300" />
                              </div>
                            </div>
                          </div>
                        </template>
                      </div>
                      <p v-if="vitalsForm.errors.action_taken" class="text-red-600 text-sm mt-1">{{ vitalsForm.errors.action_taken }}</p>
                    </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div>
                <label class="block text-sm font-medium">Time Start</label>
                <input type="datetime-local" v-model="vitalsForm.time_start" class="mt-1 block w-full rounded border-gray-300" />
              </div>
              <div>
                <label class="block text-sm font-medium">Time Out</label>
                <input type="datetime-local" v-model="vitalsForm.time_out" class="mt-1 block w-full rounded border-gray-300" />
              </div>
            </div>

                            <div>
                              <label class="block text-sm font-medium">Date Attended</label>
                              <input type="datetime-local" v-model="vitalsForm.date_attended" class="mt-1 block w-full rounded border-gray-300" />
                              <p v-if="vitalsForm.errors.date_attended" class="text-red-600 text-sm">{{ vitalsForm.errors.date_attended }}</p>
                            </div>

                            <div>
                              <label class="block text-sm font-medium">Disposition</label>
                              <div class="mt-2">
                                <template v-if="openVitals && openVitals.requestor_type === 'employee'">
                                  <div class="space-y-2">
                                    <label class="inline-flex items-center">
                                      <input type="radio" value="Returned to Work, feeling better" v-model="vitalsForm.employee_disposition" class="mr-2" />
                                      <span>Returned to Work, feeling better</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="radio" value="Went Home" v-model="vitalsForm.employee_disposition" class="mr-2" />
                                      <span>Went Home</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="radio" value="Transported to Hospital" v-model="vitalsForm.employee_disposition" class="mr-2" />
                                      <span>Transported to Hospital</span>
                                    </label>
                                  </div>
                                </template>
                                <template v-else>
                                  <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <label class="inline-flex items-center">
                                      <input type="checkbox" value="Returned to class, feeling better" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasSentHome || hasTransported" class="mr-2" />
                                      <span>Returned to class, feeling better</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="checkbox" value="Returned to class at parent's/guardian's request" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasSentHome || hasTransported || hasReturnedUnableContact" class="mr-2" />
                                      <span>Returned to class at parent's/guardian's request</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="checkbox" value="Returned to class, unable to contact parent/guardian" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasSentHome || hasTransported || hasReturnedParentRequest" class="mr-2" />
                                      <span>Returned to class, unable to contact parent/guardian</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="checkbox" value="Sent home" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasReturnedFeelingBetter || hasReturnedParentRequest || hasReturnedUnableContact" class="mr-2" />
                                      <span>Sent home</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="checkbox" value="Teacher Notified" v-model="vitalsForm.dispositions" class="mr-2" />
                                      <span>Teacher Notified</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="checkbox" value="Referral was made to Health Care Provider" v-model="vitalsForm.dispositions" class="mr-2" />
                                      <span>Referral was made to Health Care Provider</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="checkbox" value="Transported to Hospital" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasReturnedFeelingBetter || hasReturnedParentRequest || hasReturnedUnableContact" class="mr-2" />
                                      <span>Transported to Hospital</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                      <input type="checkbox" value="Copy of clinic pass sent home" v-model="vitalsForm.dispositions" class="mr-2" />
                                      <span>Copy of clinic pass sent home</span>
                                    </label>
                                    <div>
                                      <label class="inline-flex items-center">
                                        <input type="checkbox" value="Others" v-model="vitalsForm.dispositions" class="mr-2" />
                                        <span>Others</span>
                                      </label>
                                      <div v-if="vitalsForm.dispositions.includes('Others')" class="mt-2">
                                        <label class="block text-sm">Please specify</label>
                                        <input type="text" v-model="vitalsForm.others_disposition" class="mt-1 block w-full rounded border-gray-300" />
                                      </div>
                                    </div>
                                  </div>
                                </template>
                              </div>
                              <p v-if="vitalsForm.errors.disposition" class="text-red-600 text-sm mt-1">{{ vitalsForm.errors.disposition }}</p>
                            </div>

            <div class="flex gap-2">
              <button :disabled="vitalsForm.processing" class="bg-blue-600 text-white px-4 py-2 rounded">{{ vitalsForm.processing ? 'Saving...' : 'Save' }}</button>
              <button @click.prevent="closeVitals" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
