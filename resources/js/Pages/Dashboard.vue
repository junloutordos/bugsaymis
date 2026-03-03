<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
const props = defineProps({
  scholarsCount: { type: Number, default: 0 },
  facultyCount: { type: Number, default: 0 },
  staffCount: { type: Number, default: 0 },
  maleCount: { type: Number, default: 0 },
  femaleCount: { type: Number, default: 0 },
  libraryAttendanceByGrade: { type: Array, default: () => [0,0,0,0,0,0] },
  libraryAttendanceMaleByGrade: { type: Array, default: () => [0,0,0,0,0,0] },
  libraryAttendanceFemaleByGrade: { type: Array, default: () => [0,0,0,0,0,0] },
})

// Chart.js + vue-chartjs
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
} from 'chart.js'
import { Pie, Bar, Doughnut, Scatter } from 'vue-chartjs'
import { computed, ref, onMounted } from 'vue'
import axios from 'axios'

// Calendar
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import { UserIcon, AcademicCapIcon, BriefcaseIcon, TrophyIcon } from "@heroicons/vue/24/solid";
// Register Chart.js components
ChartJS.register(
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement
)

// Shared chart options (responsive + proportional)
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
}

// --- Student Dashboard Data ---
const studentData = computed(() => ({
  labels: ['Male', 'Female'],
  datasets: [
    {
      data: [props.maleCount || 0, props.femaleCount || 0],
      backgroundColor: ['#3b82f6', '#facc15'],
    },
  ],
}))

const attendanceData = computed(() => ({
  labels: ['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'],
  datasets: [
    { label: 'Male', data: props.libraryAttendanceMaleByGrade || [0,0,0,0,0,0], backgroundColor: '#3b82f6' },
    { label: 'Female', data: props.libraryAttendanceFemaleByGrade || [0,0,0,0,0,0], backgroundColor: '#facc15' },
  ],
}))

// --- HR Analytics Data ---
const salaryByAgeGender = {
  labels: ['15-24', '25-34', '35-44', '45-54', '55-64', '65+'],
  datasets: [
    { label: 'Male', data: [74, 79, 47, 74, 58, 63], backgroundColor: '#3b82f6' },
    { label: 'Female', data: [98, 88, 74, 74, 82, 79], backgroundColor: '#facc15' },
  ],
}

const salaryByOrgUnit = {
  labels: ['Sales', 'Finance', 'Customer Support', 'Marketing', 'Logistics', 'Production', 'R&D'],
  datasets: [
    {
      data: [180, 461, 65, 184, 105, 220, 330],
      backgroundColor: ['#3b82f6', '#facc15', '#3b82f6', '#facc15', '#3b82f6', '#facc15', '#3b82f6'],
    },
  ],
}

const avgSalaryOrgGender = {
  labels: ['Sales', 'Finance', 'Customer Support', 'Marketing', 'Logistics', 'Production', 'R&D'],
  datasets: [
    { label: 'Male', data: [120, 160, 80, 90, 110, 100, 140], backgroundColor: '#3b82f6' },
    { label: 'Female', data: [110, 150, 70, 85, 100, 95, 130], backgroundColor: '#facc15' },
  ],
}

const performanceBySalary = {
  datasets: [
    {
      label: 'Performance vs Salary',
      data: Array.from({ length: 200 }, () => ({
        x: Math.floor(Math.random() * 100000),
        y: Math.floor(Math.random() * 100),
      })),
      backgroundColor: '#3b82f6',
    },
  ],
}

const compaRatio = {
  labels: ['Sales', 'Finance', 'Customer Support', 'Marketing', 'Logistics', 'Production', 'R&D'],
  datasets: [{ label: 'Compa Ratio', data: [1.1, 1.0, 0.9, 0.95, 1.05, 0.98, 1.02], backgroundColor: '#facc15' }],
}

// Calendar Options
const calendarOptions = {
  plugins: [dayGridPlugin],
  initialView: 'dayGridMonth',
  height: 'auto',
  fixedWeekCount: false,
  headerToolbar: { left: 'prev,next', center: 'title', right: '' },
  eventDisplay: 'block',
  dayMaxEventRows: 2,
  events: [
    { title: 'New Student Inauguration', date: '2025-07-17', color: '#3b82f6' },
    { title: 'Student Body Handover', date: '2025-07-19', color: '#10b981' },
    { title: 'Closing of School Clubs', date: '2025-07-27', color: '#facc15' },
  ],
}

// Modal state for date click
const showDateModal = ref(false)
const selectedDate = ref('')
const dateBookings = ref([])
const loadingBookings = ref(false)
const bookingsError = ref(null)
const calendarContainer = ref(null)

async function openDateModal(dateStr) {
  selectedDate.value = dateStr
  showDateModal.value = true
  loadingBookings.value = true
  bookingsError.value = null
  try {
    const url = route('facility-requests.byDate')
    console.log('[Dashboard] fetching bookings for', dateStr, '->', url)
    const res = await axios.get(url, { params: { date: dateStr } })
    dateBookings.value = res.data || []
  } catch (e) {
    bookingsError.value = 'Failed to load bookings'
    dateBookings.value = []
    console.error('[Dashboard] failed to fetch bookings', e)
  } finally {
    loadingBookings.value = false
  }
}

// attach handler for FullCalendar date clicks
// Register dateClick after mount via calendar API to avoid FullCalendar warning
onMounted(() => {
  // Use DOM delegation: calendar day cells include a data-date attribute
  try {
    const handler = (ev) => {
      let el = ev.target
      const root = calendarContainer.value
      while (el && el !== root) {
        if (el.dataset && el.dataset.date) {
          const dateStr = el.dataset.date
          console.log('[Dashboard] day cell clicked', dateStr)
          openDateModal(dateStr)
          return
        }
        el = el.parentElement
      }
    }
    if (calendarContainer.value) {
      calendarContainer.value.addEventListener('click', handler)
      console.log('[Dashboard] attached DOM click handler to calendar container')
    } else {
      console.warn('[Dashboard] calendar container not found to attach click handler')
    }
  } catch (e) {
    console.error('[Dashboard] failed to attach DOM click handler', e)
  }
})
</script>

<template>
  <AdminLayout title="Dashboard">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Left Main Content -->
      <div class="lg:col-span-3 space-y-6">
        <!-- Top Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <!-- Students -->
          <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-4 rounded-xl shadow hover:shadow-lg transition text-white flex items-center space-x-3">
            <UserIcon class="h-10 w-10 opacity-90" />
            <div>
              <p class="text-sm opacity-80">Scholars</p>
              <h2 class="text-2xl font-bold">{{ (props.scholarsCount || 0).toLocaleString() }}</h2>
            </div>
          </div>

          <!-- Teachers -->
          <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-4 rounded-xl shadow hover:shadow-lg transition text-white flex items-center space-x-3">
            <AcademicCapIcon class="h-10 w-10 opacity-90" />
            <div>
              <p class="text-sm opacity-80">Faculty</p>
              <h2 class="text-2xl font-bold">{{ (props.facultyCount || 0).toLocaleString() }}</h2>
            </div>
          </div>

          <!-- Staffs -->
          <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-4 rounded-xl shadow hover:shadow-lg transition text-white flex items-center space-x-3">
            <BriefcaseIcon class="h-10 w-10 opacity-90" />
            <div>
              <p class="text-sm opacity-80">Staffs</p>
              <h2 class="text-2xl font-bold">{{ (props.staffCount || 0).toLocaleString() }}</h2>
            </div>
          </div>

          <!-- Awards -->
          <div class="bg-gradient-to-r from-yellow-400 to-yellow-600 p-4 rounded-xl shadow hover:shadow-lg transition text-white flex items-center space-x-3">
            <TrophyIcon class="h-10 w-10 opacity-90" />
            <div>
              <p class="text-sm opacity-80">Awards</p>
              <h2 class="text-2xl font-bold">893</h2>
            </div>
          </div>
        </div>


        <!-- Main Charts -->
        <div class="space-y-6">
          <h2 class="text-xl font-bold text-gray-700">Student Analytics</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-4">Students</h3>
            <div class="chart-container min-h-[220px]">
              <Pie :data="studentData" :options="chartOptions" />
            </div>
          </div>
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-4">Library Traffic</h3>
            <div class="chart-container min-h-[300px]">
              <Bar :data="attendanceData" :options="chartOptions" />
            </div>
          </div>
        </div>
        </div>
        <!-- HR Analytics Section -->
        <div class="space-y-6">
          <h2 class="text-xl font-bold text-gray-700">HR Analytics</h2>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-xl shadow">
              <h3 class="text-lg font-semibold mb-4">Salary by Age Group & Gender</h3>
              <div class="chart-container min-h-[300px]">
                <Bar :data="salaryByAgeGender" :options="chartOptions" />
              </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow">
              <h3 class="text-lg font-semibold mb-4">Total Salary by Org Unit</h3>
              <div class="chart-container min-h-[220px]">
                <Doughnut :data="salaryByOrgUnit" :options="chartOptions" />
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-xl shadow">
              <h3 class="text-lg font-semibold mb-4">Average Salary by Org Unit & Gender</h3>
              <div class="chart-container min-h-[300px]">
                <Bar :data="avgSalaryOrgGender" :options="chartOptions" />
              </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow">
              <h3 class="text-lg font-semibold mb-4">Performance by Salary</h3>
              <div class="chart-container min-h-[300px]">
                <Scatter :data="performanceBySalary" :options="chartOptions" />
              </div>
            </div>
          </div>

          
        </div>
      </div>

      <!-- Right Sidebar -->
      <div class="space-y-6">
        <!-- Calendar -->
        <div class="bg-white p-6 rounded-xl shadow overflow-hidden">
          <h3 class="text-lg font-semibold mb-4">Calendar</h3>
          <div class="rounded-lg border border-gray-100 overflow-hidden">
            <div ref="calendarContainer">
              <FullCalendar :options="calendarOptions" />
            </div>
            <!-- Date bookings modal -->
            <div v-if="showDateModal" class="fixed inset-0 flex items-center justify-center z-50">
              <div class="absolute inset-0 bg-black bg-opacity-40" @click="showDateModal = false"></div>
              <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full mx-4 p-4 z-10">
                <div class="flex items-center justify-between mb-3">
                  <h4 class="font-semibold">Facility Requests — {{ selectedDate }}</h4>
                  <button class="text-gray-600 hover:text-gray-800" @click="showDateModal = false">Close</button>
                </div>
                <div v-if="loadingBookings" class="py-6 text-center text-sm text-gray-600">Loading…</div>
                <div v-else>
                  <div v-if="bookingsError" class="text-red-600 mb-2">{{ bookingsError }}</div>
                  <div v-if="dateBookings.length === 0" class="text-sm text-gray-600">No facility requests for this date.</div>
                  <ul v-else class="space-y-3">
                    <li v-for="b in dateBookings" :key="b.id" class="p-3 border rounded-lg">
                      <div class="flex items-start justify-between">
                        <div>
                          <div class="text-sm text-gray-500">Requestor: <span class="font-medium">{{ b.requester_name || '—' }}</span> <small class="text-gray-400">{{ b.requester_unit || '' }}</small></div>
                          <div class="text-lg font-semibold">{{ b.activity || '—' }}</div>
                          <div class="text-sm text-gray-600">Venue: <span class="font-medium">{{ (b.venue && b.venue.length) ? b.venue.join(', ') : '—' }}</span></div>
                          <div class="text-sm text-gray-600">Time: <span class="font-medium">{{ b.time_start || '—' }} — {{ b.time_end || '—' }}</span></div>
                        </div>
                        <div class="text-right space-y-1">
                          <div :class="{'text-green-600': b.status && b.status.includes('Approved'), 'text-yellow-600': b.status && b.status.includes('Pending'), 'text-gray-600': !b.status}" class="text-sm font-medium">{{ b.status || '—' }}</div>
                          <div class="text-sm">
                            <span v-if="b.has_it_job" class="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-xs">IT Job: <strong class="ml-2">{{ b.it_job_status || 'Unknown' }}</strong></span>
                            <span v-else class="inline-flex items-center px-2 py-1 rounded bg-gray-50 text-xs text-gray-500">No IT job</span>
                          </div>
                        </div>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Upcoming Events -->
        <div class="bg-white p-6 rounded-xl shadow">
          <h3 class="text-lg font-semibold mb-4">Upcoming Events</h3>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="text-blue-500">🎉 New Student Inauguration Ceremony</li>
            <li class="text-green-500">👨‍🏫 Chairman of Student Body Handover</li>
            <li class="text-yellow-500">🏫 Closing of School Clubs Acceptance</li>
          </ul>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white p-6 rounded-xl shadow">
          <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="text-green-500">✔️ Mia Gordon won art match competition</li>
            <li class="text-green-500">✔️ Liam Fiddle and 20 others signed community cleanup</li>
            <li class="text-green-500">✔️ Jayden Obeary completed new assignment</li>
            <li class="text-purple-500">✔️ Mr. Bennett uploaded exam schedule</li>
          </ul>
        </div>

        <!-- Student Activities -->
        <div class="bg-white p-6 rounded-xl shadow">
          <h3 class="text-lg font-semibold mb-4">Student Activities</h3>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="text-green-500">🏆 Best in Show at Statewide Art Contest</li>
            <li class="text-green-500">🥇 Gold Medal in National Math Olympiad</li>
            <li class="text-green-500">🥈 First Place in Regional Science Fair</li>
          </ul>
        </div>

        <!-- Notice Board -->
        <div class="bg-white p-6 rounded-xl shadow">
          <h3 class="text-lg font-semibold mb-4">Notice Board</h3>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="text-yellow-500">📌 School Event Reminder</li>
            <li class="text-yellow-500">📌 Important Exam Update</li>
            <li class="text-yellow-500">📌 Health and Safety Update</li>
            <li class="text-yellow-500">📌 Parent-Teacher Meeting Announcement</li>
          </ul>
        </div>

        <!-- Messages -->
        <div class="bg-white p-6 rounded-xl shadow">
          <h3 class="text-lg font-semibold mb-4">Messages</h3>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="text-pink-500">💬 Alex Campbell: "Reminder about tomorrow’s event"</li>
            <li class="text-pink-500">💬 Mrs. Patel: "Exam schedules have been updated"</li>
            <li class="text-pink-500">💬 Coach Davies: "Training moved to Friday"</li>
          </ul>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
