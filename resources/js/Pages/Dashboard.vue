<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import {
  BellAlertIcon,
  CalendarDaysIcon,
  CheckCircleIcon,
  ChevronRightIcon,
  ClipboardDocumentCheckIcon,
  ClockIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  InboxStackIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  profile: { type: Object, default: () => ({}) },
  summary: { type: Object, default: () => ({}) },
  approvalTabs: { type: Array, default: () => [] },
  documentsForAction: { type: Array, default: () => [] },
  acknowledgments: { type: Array, default: () => [] },
  activityEvaluations: { type: Array, default: () => [] },
  myRequests: { type: Array, default: () => [] },
  notifications: { type: Array, default: () => [] },
  calendarEvents: { type: Array, default: () => [] },
  quickLinks: { type: Array, default: () => [] },
})

const page = usePage()
const authUser = computed(() => page.props.auth?.user)

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
})

const today = computed(() =>
  new Date().toLocaleDateString('en-PH', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
)

const initials = computed(() => {
  const name = props.profile?.name || authUser.value?.name || '?'
  return name.split(' ').filter(Boolean).slice(0, 2).map(word => word[0].toUpperCase()).join('')
})

const profileLine = computed(() => {
  const pieces = [props.profile?.position, props.profile?.division, props.profile?.office].filter(Boolean)
  return pieces.length ? pieces.join(' · ') : 'Personal workspace'
})

const flattenedActions = computed(() => {
  const approvals = (props.approvalTabs || []).flatMap(tab =>
    (tab.items || []).slice(0, 4).map(item => ({
      id: `approval-${tab.type}-${item.id}`,
      label: tab.label,
      title: item.summary || item.reference_no || 'Approval item',
      meta: `${item.requester_name || 'Requester'} · ${item.status || 'Pending'}`,
      date: item.filed_at,
      tone: 'rose',
      url: route('approvals.inbox'),
    }))
  )

  const documents = (props.documentsForAction || []).map(item => ({
    id: `document-${item.id}`,
    label: item.is_overdue ? 'Overdue Document' : 'Document Routing',
    title: item.title,
    meta: [item.reference, item.status, item.due_at ? `Due ${formatDate(item.due_at)}` : null].filter(Boolean).join(' · '),
    date: item.due_at,
    tone: item.is_overdue ? 'red' : 'amber',
    url: item.url,
  }))

  const acknowledgments = (props.acknowledgments || []).map(item => ({
    id: `ack-${item.id}`,
    label: 'Acknowledgment',
    title: item.title,
    meta: [item.kind, item.reference].filter(Boolean).join(' · '),
    date: item.date,
    tone: 'indigo',
    url: item.url,
  }))

  const evaluations = (props.activityEvaluations || []).map(item => ({
    id: `eval-${item.id}`,
    label: 'Activity Evaluation',
    title: item.title,
    meta: item.reference,
    date: item.date,
    tone: 'emerald',
    url: item.url,
  }))

  return [...documents, ...approvals, ...acknowledgments, ...evaluations]
    .sort((a, b) => scoreDate(a.date) - scoreDate(b.date))
    .slice(0, 10)
})

const nextEvents = computed(() =>
  (props.calendarEvents || [])
    .filter(event => new Date(event.start) >= startOfToday())
    .sort((a, b) => new Date(a.start) - new Date(b.start))
    .slice(0, 5)
)

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin],
  initialView: 'dayGridMonth',
  height: 'auto',
  fixedWeekCount: false,
  headerToolbar: { left: 'prev,next', center: 'title', right: '' },
  dayMaxEventRows: 3,
  eventDisplay: 'block',
  events: props.calendarEvents || [],
  eventClick(info) {
    if (info.event.url) {
      info.jsEvent.preventDefault()
      window.location.href = info.event.url
    }
  },
}))

const summaryCards = computed(() => [
  {
    label: 'Needs Action',
    value: props.summary?.needs_action ?? 0,
    sub: `${props.summary?.approval_items ?? 0} approval item(s)`,
    icon: InboxStackIcon,
    tone: 'rose',
  },
  {
    label: 'Documents Due',
    value: props.summary?.documents_due ?? 0,
    sub: `${props.summary?.overdue_documents ?? 0} overdue`,
    icon: DocumentTextIcon,
    tone: (props.summary?.overdue_documents ?? 0) > 0 ? 'red' : 'amber',
  },
  {
    label: 'Unread Alerts',
    value: props.summary?.unread_notifications ?? 0,
    sub: 'Notifications for you',
    icon: BellAlertIcon,
    tone: 'indigo',
  },
  {
    label: 'Active Requests',
    value: props.summary?.active_requests ?? 0,
    sub: 'Recent personal requests',
    icon: ClipboardDocumentCheckIcon,
    tone: 'sky',
  },
  {
    label: 'Upcoming',
    value: props.summary?.upcoming_events ?? 0,
    sub: 'Calendar items',
    icon: CalendarDaysIcon,
    tone: 'emerald',
  },
])

function startOfToday() {
  const date = new Date()
  date.setHours(0, 0, 0, 0)
  return date
}

function scoreDate(value) {
  if (!value) return Number.MAX_SAFE_INTEGER
  return Math.abs(new Date(value).getTime() - Date.now())
}

function formatDate(value) {
  if (!value) return 'No date'
  return new Date(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(value) {
  if (!value) return ''
  return new Date(value).toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function timeAgo(value) {
  if (!value) return ''
  const diff = Math.floor((Date.now() - new Date(value).getTime()) / 1000)
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function toneClass(tone, variant = 'soft') {
  const classes = {
    rose: {
      soft: 'bg-rose-50 text-rose-700 border-rose-100',
      icon: 'bg-rose-100 text-rose-700',
      dot: 'bg-rose-500',
    },
    red: {
      soft: 'bg-red-50 text-red-700 border-red-100',
      icon: 'bg-red-100 text-red-700',
      dot: 'bg-red-500',
    },
    amber: {
      soft: 'bg-amber-50 text-amber-700 border-amber-100',
      icon: 'bg-amber-100 text-amber-700',
      dot: 'bg-amber-500',
    },
    indigo: {
      soft: 'bg-indigo-50 text-indigo-700 border-indigo-100',
      icon: 'bg-indigo-100 text-indigo-700',
      dot: 'bg-indigo-500',
    },
    sky: {
      soft: 'bg-sky-50 text-sky-700 border-sky-100',
      icon: 'bg-sky-100 text-sky-700',
      dot: 'bg-sky-500',
    },
    emerald: {
      soft: 'bg-emerald-50 text-emerald-700 border-emerald-100',
      icon: 'bg-emerald-100 text-emerald-700',
      dot: 'bg-emerald-500',
    },
    violet: {
      soft: 'bg-violet-50 text-violet-700 border-violet-100',
      icon: 'bg-violet-100 text-violet-700',
      dot: 'bg-violet-500',
    },
  }
  return classes[tone]?.[variant] || classes.indigo[variant]
}

function statusClass(status) {
  const value = String(status || '').toLowerCase()
  if (value.includes('approved') || value.includes('completed') || value.includes('released')) {
    return 'bg-emerald-50 text-emerald-700 border-emerald-100'
  }
  if (value.includes('declined') || value.includes('rejected') || value.includes('returned') || value.includes('cancelled')) {
    return 'bg-red-50 text-red-700 border-red-100'
  }
  if (value.includes('pending') || value.includes('review') || value.includes('forwarded')) {
    return 'bg-amber-50 text-amber-700 border-amber-100'
  }
  return 'bg-slate-50 text-slate-600 border-slate-100'
}
</script>

<template>
  <Head title="Dashboard" />

  <AdminLayout title="Dashboard">
    <div class="space-y-5">
      <section class="rounded-lg border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 items-center gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-sm font-bold text-indigo-700">
              {{ initials }}
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500">{{ today }}</p>
              <h1 class="mt-0.5 truncate text-lg font-semibold text-slate-900 sm:text-xl">
                {{ greeting }}, {{ (profile.name || authUser?.name || 'there').split(' ')[0] }}
              </h1>
              <p class="mt-1 truncate text-sm text-slate-500">{{ profileLine }}</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <Link
              v-for="link in quickLinks.slice(0, 3)"
              :key="link.label"
              :href="link.url"
              class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:-translate-y-0.5 hover:shadow-sm"
              :class="toneClass(link.tone)"
            >
              {{ link.label }}
              <ChevronRightIcon class="h-4 w-4" />
            </Link>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-3xl font-bold tracking-normal text-slate-900">{{ Number(card.value).toLocaleString() }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ card.sub }}</p>
            </div>
            <div class="rounded-lg p-2" :class="toneClass(card.tone, 'icon')">
              <component :is="card.icon" class="h-5 w-5" />
            </div>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,.65fr)]">
        <div class="space-y-5">
          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <div>
                <h2 class="text-sm font-semibold text-slate-900">Needs Action</h2>
                <p class="text-xs text-slate-500">Approvals, routed documents, acknowledgments, and evaluations assigned to you</p>
              </div>
              <Link v-if="approvalTabs.length" :href="route('approvals.inbox')" class="hidden text-sm font-medium text-indigo-600 hover:text-indigo-800 sm:inline">
                Open Inbox
              </Link>
            </div>

            <div v-if="flattenedActions.length" class="divide-y divide-slate-100">
              <Link
                v-for="item in flattenedActions"
                :key="item.id"
                :href="item.url"
                class="flex gap-3 px-4 py-3 transition hover:bg-slate-50"
              >
                <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full" :class="toneClass(item.tone, 'dot')" />
                <div class="min-w-0 flex-1">
                  <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ item.title }}</p>
                    <span class="shrink-0 text-xs text-slate-400">{{ item.date ? formatDate(item.date) : '' }}</span>
                  </div>
                  <p class="mt-0.5 text-xs font-medium uppercase text-slate-500">{{ item.label }}</p>
                  <p class="mt-1 truncate text-sm text-slate-500">{{ item.meta }}</p>
                </div>
                <ChevronRightIcon class="mt-3 h-4 w-4 shrink-0 text-slate-300" />
              </Link>
            </div>

            <div v-else class="flex flex-col items-center justify-center px-4 py-14 text-center">
              <CheckCircleIcon class="h-10 w-10 text-emerald-500" />
              <p class="mt-3 text-sm font-semibold text-slate-800">Nothing needs your action right now.</p>
              <p class="mt-1 max-w-md text-sm text-slate-500">New approvals, routed documents, acknowledgments, and activity evaluations will appear here.</p>
            </div>
          </div>

          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <div>
                <h2 class="text-sm font-semibold text-slate-900">My Requests</h2>
                <p class="text-xs text-slate-500">Recent requests you filed or own across modules</p>
              </div>
              <ClockIcon class="h-5 w-5 text-slate-400" />
            </div>

            <div v-if="myRequests.length" class="overflow-x-auto">
              <table class="w-full min-w-[760px] text-sm">
                <thead>
                  <tr class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="px-4 py-3">Request</th>
                    <th class="px-4 py-3">Module</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Updated</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="request in myRequests" :key="`${request.module}-${request.reference}`" class="hover:bg-slate-50">
                    <td class="max-w-[280px] px-4 py-3">
                      <Link :href="request.url" class="block truncate font-medium text-slate-900 hover:text-indigo-700">
                        {{ request.title }}
                      </Link>
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ request.module }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ request.reference }}</td>
                    <td class="px-4 py-3">
                      <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="statusClass(request.status)">
                        {{ request.status }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-400">{{ formatDateTime(request.updated_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="px-4 py-12 text-center text-sm text-slate-400">
              No recent requests found.
            </div>
          </div>
        </div>

        <aside class="space-y-5">
          <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
              <div>
                <h2 class="text-sm font-semibold text-slate-900">My Calendar</h2>
                <p class="text-xs text-slate-500">Upcoming personal dates and due items</p>
              </div>
              <CalendarDaysIcon class="h-5 w-5 text-slate-400" />
            </div>
            <div class="dashboard-calendar rounded-lg border border-slate-100 text-xs">
              <FullCalendar :options="calendarOptions" />
            </div>

            <div class="mt-4 space-y-2">
              <p class="text-xs font-semibold uppercase text-slate-500">Next Up</p>
              <Link
                v-for="event in nextEvents"
                :key="event.id"
                :href="event.url"
                class="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 transition hover:bg-slate-50"
              >
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="toneClass(event.type === 'overdue' ? 'red' : event.type === 'document' ? 'amber' : event.type === 'activity' ? 'emerald' : 'indigo', 'dot')" />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-slate-800">{{ event.title }}</p>
                  <p class="text-xs text-slate-400">{{ formatDate(event.start) }}</p>
                </div>
              </Link>
              <p v-if="!nextEvents.length" class="rounded-lg border border-slate-100 px-3 py-4 text-center text-sm text-slate-400">
                No upcoming calendar items.
              </p>
            </div>
          </div>

          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <div>
                <h2 class="text-sm font-semibold text-slate-900">Notifications</h2>
                <p class="text-xs text-slate-500">Latest alerts addressed to you</p>
              </div>
              <BellAlertIcon class="h-5 w-5 text-slate-400" />
            </div>

            <div v-if="notifications.length" class="divide-y divide-slate-100">
              <a
                v-for="notification in notifications"
                :key="notification.id"
                :href="notification.url || '#'"
                class="flex gap-3 px-4 py-3 transition hover:bg-slate-50"
                :class="notification.read_at ? '' : 'bg-indigo-50/40'"
              >
                <span class="mt-2 h-2 w-2 shrink-0 rounded-full" :class="notification.read_at ? 'bg-slate-200' : 'bg-indigo-500'" />
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-3">
                    <p class="truncate text-sm font-semibold text-slate-900">
                      {{ notification.reference_no || notification.request_type }}
                    </p>
                    <span class="shrink-0 text-xs text-slate-400">{{ timeAgo(notification.created_at) }}</span>
                  </div>
                  <p class="mt-0.5 text-xs font-medium uppercase text-slate-500">{{ notification.request_type }}</p>
                  <p class="mt-1 truncate text-sm text-indigo-700">{{ notification.status }}</p>
                  <p v-if="notification.remarks" class="mt-0.5 truncate text-xs text-slate-500">{{ notification.remarks }}</p>
                </div>
              </a>
            </div>

            <div v-else class="px-4 py-10 text-center text-sm text-slate-400">
              No notifications yet.
            </div>
          </div>

          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3">
              <h2 class="text-sm font-semibold text-slate-900">Quick Links</h2>
              <p class="text-xs text-slate-500">Common places based on your role and workload</p>
            </div>
            <div class="grid grid-cols-1 gap-2 p-4">
              <Link
                v-for="link in quickLinks"
                :key="link.label"
                :href="link.url"
                class="group flex items-center gap-3 rounded-lg border p-3 transition hover:-translate-y-0.5 hover:shadow-sm"
                :class="toneClass(link.tone)"
              >
                <UserCircleIcon class="h-5 w-5 shrink-0" />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold">{{ link.label }}</p>
                  <p class="truncate text-xs opacity-80">{{ link.description }}</p>
                </div>
                <ChevronRightIcon class="h-4 w-4 shrink-0 opacity-60 transition group-hover:translate-x-0.5" />
              </Link>
            </div>
          </div>

          <div
            v-if="(summary.overdue_documents ?? 0) > 0"
            class="rounded-lg border border-red-100 bg-red-50 p-4 text-red-800"
          >
            <div class="flex gap-3">
              <ExclamationTriangleIcon class="h-5 w-5 shrink-0" />
              <div>
                <p class="text-sm font-semibold">You have overdue routed document(s).</p>
                <p class="mt-1 text-sm text-red-700">Open the document from Needs Action and record the required action.</p>
              </div>
            </div>
          </div>
        </aside>
      </section>
    </div>
  </AdminLayout>
</template>

<style scoped>
.dashboard-calendar :deep(.fc) {
  --fc-border-color: #e2e8f0;
  --fc-today-bg-color: #eef2ff;
  font-family: inherit;
}

.dashboard-calendar :deep(.fc-toolbar-title) {
  font-size: .9rem;
  font-weight: 700;
  color: #0f172a;
}

.dashboard-calendar :deep(.fc-button) {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  border-radius: .5rem;
  padding: .25rem .45rem;
  box-shadow: none;
}

.dashboard-calendar :deep(.fc-button:hover),
.dashboard-calendar :deep(.fc-button:focus) {
  background: #f8fafc;
  color: #0f172a;
}

.dashboard-calendar :deep(.fc-daygrid-day-number) {
  color: #64748b;
  font-size: .72rem;
  padding: .25rem;
}

.dashboard-calendar :deep(.fc-col-header-cell-cushion) {
  color: #475569;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
}

.dashboard-calendar :deep(.fc-event) {
  border: 0;
  border-radius: .35rem;
  padding: .05rem .2rem;
  font-size: .68rem;
  font-weight: 600;
}

.dashboard-calendar :deep(.dash-calendar-leave) { background: #4f46e5; }
.dashboard-calendar :deep(.dash-calendar-travel) { background: #0284c7; }
.dashboard-calendar :deep(.dash-calendar-activity) { background: #059669; }
.dashboard-calendar :deep(.dash-calendar-facility) { background: #7c3aed; }
.dashboard-calendar :deep(.dash-calendar-vehicle) { background: #0f766e; }
.dashboard-calendar :deep(.dash-calendar-service) { background: #d97706; }
.dashboard-calendar :deep(.dash-calendar-document) { background: #f59e0b; color: #422006; }
.dashboard-calendar :deep(.dash-calendar-overdue) { background: #dc2626; }
</style>
