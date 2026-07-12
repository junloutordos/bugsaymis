<script setup>
import { computed, onMounted, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { storageUrl } from '@/Composables/useStorage.js'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import EmptyState from '@/Components/EmptyState.vue'
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
  MegaphoneIcon,
  PhotoIcon,
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
  announcements: { type: Array, default: () => [] },
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

const photoUrl = computed(() => storageUrl(authUser.value?.profile_picture))

const flattenedActions = computed(() => {
  const approvals = (props.approvalTabs || []).flatMap(tab =>
    (tab.items || []).slice(0, 4).map(item => ({
      id: `approval-${tab.type}-${item.id}`,
      label: tab.label,
      title: item.summary || item.reference_no || 'Approval item',
      meta: `${item.requester_name || 'Requester'} · ${item.status || 'Pending'}`,
      date: item.filed_at,
      tone: 'warning',
      icon: InboxStackIcon,
      url: route('approvals.inbox'),
    }))
  )

  const documents = (props.documentsForAction || []).map(item => ({
    id: `document-${item.id}`,
    label: item.is_overdue ? 'Overdue Document' : 'Document Routing',
    title: item.title,
    meta: [item.reference, item.status, item.due_at ? `Due ${formatDate(item.due_at)}` : null].filter(Boolean).join(' · '),
    date: item.due_at,
    tone: item.is_overdue ? 'danger' : 'warning',
    icon: DocumentTextIcon,
    url: item.url,
  }))

  const acknowledgments = (props.acknowledgments || []).map(item => ({
    id: `ack-${item.id}`,
    label: 'Acknowledgment',
    title: item.title,
    meta: [item.kind, item.reference].filter(Boolean).join(' · '),
    date: item.date,
    tone: 'indigo',
    icon: CheckCircleIcon,
    url: item.url,
  }))

  const evaluations = (props.activityEvaluations || []).map(item => ({
    id: `eval-${item.id}`,
    label: 'Activity Evaluation',
    title: item.title,
    meta: item.reference,
    date: item.date,
    tone: 'success',
    icon: ClipboardDocumentCheckIcon,
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
    tone: 'warning',
  },
  {
    label: 'Documents Due',
    value: props.summary?.documents_due ?? 0,
    sub: `${props.summary?.overdue_documents ?? 0} overdue`,
    icon: DocumentTextIcon,
    tone: (props.summary?.overdue_documents ?? 0) > 0 ? 'danger' : 'warning',
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
    tone: 'indigo',
  },
  {
    label: 'Upcoming',
    value: props.summary?.upcoming_events ?? 0,
    sub: 'Calendar items',
    icon: CalendarDaysIcon,
    tone: 'success',
  },
])

// Animated stat values — count up from 0 on mount (rAF, no deps)
const animatedValues = ref(summaryCards.value.map(() => 0))

onMounted(() => {
  const targets = summaryCards.value.map(card => Number(card.value) || 0)
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    animatedValues.value = targets
    return
  }
  const duration = 700
  const start = performance.now()
  const tick = (now) => {
    const t = Math.min((now - start) / duration, 1)
    const ease = 1 - Math.pow(1 - t, 3)
    animatedValues.value = targets.map(v => Math.round(v * ease))
    if (t < 1) requestAnimationFrame(tick)
  }
  requestAnimationFrame(tick)
})

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
    indigo: {
      soft: 'bg-indigo-50 text-indigo-700 border-indigo-100',
      icon: 'bg-indigo-100 text-indigo-700',
      dot: 'bg-indigo-500',
    },
    warning: {
      soft: 'bg-warning-50 text-warning-700 border-warning-100',
      icon: 'bg-warning-100 text-warning-700',
      dot: 'bg-warning-500',
    },
    danger: {
      soft: 'bg-danger-50 text-danger-700 border-danger-100',
      icon: 'bg-danger-100 text-danger-700',
      dot: 'bg-danger-500',
    },
    success: {
      soft: 'bg-success-50 text-success-700 border-success-100',
      icon: 'bg-success-100 text-success-700',
      dot: 'bg-success-500',
    },
  }
  return classes[tone]?.[variant] || classes.indigo[variant]
}

function statusClass(status) {
  const value = String(status || '').toLowerCase()
  if (value.includes('approved') || value.includes('completed') || value.includes('released')) {
    return 'bg-success-50 text-success-700 border-success-100'
  }
  if (value.includes('declined') || value.includes('rejected') || value.includes('returned') || value.includes('cancelled')) {
    return 'bg-danger-50 text-danger-700 border-danger-100'
  }
  if (value.includes('pending') || value.includes('review') || value.includes('forwarded')) {
    return 'bg-warning-50 text-warning-700 border-warning-100'
  }
  return 'bg-slate-50 text-slate-600 border-slate-100'
}
</script>

<template>
  <Head title="Dashboard" />

  <AdminLayout title="Dashboard">
    <div class="space-y-5">
      <section class="dash-section relative overflow-hidden rounded-2xl bg-white px-4 py-5 shadow-sm ring-1 ring-slate-200/70 sm:px-6" style="--stagger: 0">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-600 via-indigo-500 to-indigo-100" aria-hidden="true"></div>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 items-center gap-4">
            <img
              v-if="photoUrl"
              :src="photoUrl"
              alt=""
              class="h-14 w-14 shrink-0 rounded-full object-cover ring-2 ring-indigo-100"
            />
            <div
              v-else
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-base font-bold text-indigo-700"
            >
              {{ initials }}
            </div>
            <div class="min-w-0">
              <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">{{ today }}</span>
              <h1 class="mt-1.5 truncate font-heading text-xl font-semibold text-slate-900 sm:text-2xl">
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
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:-translate-y-0.5 hover:bg-indigo-100 hover:shadow-sm"
            >
              {{ link.label }}
              <ChevronRightIcon class="h-4 w-4" />
            </Link>
          </div>
        </div>
      </section>

      <section class="dash-section grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5" style="--stagger: 1">
        <div
          v-for="(card, index) in summaryCards"
          :key="card.label"
          class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-3xl font-bold tracking-normal text-slate-900 tabular-nums">{{ Number(animatedValues[index] ?? card.value).toLocaleString() }}</p>
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
          <div class="dash-section rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70" style="--stagger: 2">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                  <InboxStackIcon class="h-4 w-4 text-indigo-600" />
                </span>
                <div class="min-w-0">
                  <h2 class="font-heading text-sm font-semibold text-slate-900">Needs Action</h2>
                  <p class="truncate text-xs text-slate-500">Approvals, routed documents, acknowledgments, and evaluations assigned to you</p>
                </div>
              </div>
              <Link v-if="approvalTabs.length" :href="route('approvals.inbox')" class="hidden shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-800 sm:inline">
                Open Inbox
              </Link>
            </div>

            <div v-if="flattenedActions.length" class="divide-y divide-slate-100">
              <Link
                v-for="item in flattenedActions"
                :key="item.id"
                :href="item.url"
                class="group flex gap-3 px-4 py-3 transition hover:bg-slate-50"
              >
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="toneClass(item.tone, 'icon')">
                  <component :is="item.icon" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                  <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ item.title }}</p>
                    <span class="shrink-0 text-xs text-slate-400">{{ item.date ? formatDate(item.date) : '' }}</span>
                  </div>
                  <p class="mt-0.5 text-xs font-medium uppercase text-slate-500">{{ item.label }}</p>
                  <p class="mt-1 truncate text-sm text-slate-500">{{ item.meta }}</p>
                </div>
                <ChevronRightIcon class="mt-3 h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-400" />
              </Link>
            </div>

            <div v-else class="flex flex-col items-center justify-center px-4 py-14 text-center">
              <CheckCircleIcon class="h-10 w-10 text-emerald-500" />
              <p class="mt-3 text-sm font-semibold text-slate-800">Nothing needs your action right now.</p>
              <p class="mt-1 max-w-md text-sm text-slate-500">New approvals, routed documents, acknowledgments, and activity evaluations will appear here.</p>
            </div>
          </div>

          <div class="dash-section rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70" style="--stagger: 3">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                  <ClockIcon class="h-4 w-4 text-indigo-600" />
                </span>
                <div class="min-w-0">
                  <h2 class="font-heading text-sm font-semibold text-slate-900">My Requests</h2>
                  <p class="truncate text-xs text-slate-500">Recent requests you filed or own across modules</p>
                </div>
              </div>
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

            <EmptyState v-else title="No recent requests found." />
          </div>
        </div>

        <aside class="space-y-5">
          <div class="dash-section rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" style="--stagger: 2">
            <div class="mb-3 flex items-center justify-between">
              <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                  <CalendarDaysIcon class="h-4 w-4 text-indigo-600" />
                </span>
                <div class="min-w-0">
                  <h2 class="font-heading text-sm font-semibold text-slate-900">My Calendar</h2>
                  <p class="truncate text-xs text-slate-500">Upcoming personal dates and due items</p>
                </div>
              </div>
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
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="toneClass(event.type === 'overdue' ? 'danger' : event.type === 'document' ? 'warning' : event.type === 'activity' ? 'success' : 'indigo', 'dot')" />
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

          <div class="dash-section rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70" style="--stagger: 3">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                  <BellAlertIcon class="h-4 w-4 text-indigo-600" />
                </span>
                <div class="min-w-0">
                  <h2 class="font-heading text-sm font-semibold text-slate-900">Notifications</h2>
                  <p class="truncate text-xs text-slate-500">Latest alerts addressed to you</p>
                </div>
              </div>
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

            <EmptyState v-else title="No notifications yet." />
          </div>

          <div v-if="announcements.length" class="dash-section rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70" style="--stagger: 4">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                  <MegaphoneIcon class="h-4 w-4 text-indigo-600" />
                </span>
                <div class="min-w-0">
                  <h2 class="font-heading text-sm font-semibold text-slate-900">Announcements</h2>
                  <p class="truncate text-xs text-slate-500">Latest campus announcements for you</p>
                </div>
              </div>
              <Link :href="route('announcements.index')" class="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-700">
                View all
              </Link>
            </div>
            <div class="divide-y divide-slate-100">
              <Link
                v-for="a in announcements"
                :key="a.id"
                :href="route('announcements.index')"
                class="flex items-start gap-3 px-4 py-3 transition hover:bg-slate-50"
              >
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50">
                  <component :is="a.has_poster ? PhotoIcon : MegaphoneIcon" class="h-4 w-4 text-indigo-600" />
                </span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold text-slate-900">{{ a.title }}</p>
                  <p class="mt-0.5 text-xs text-slate-500">{{ timeAgo(a.published_at) }}</p>
                </div>
              </Link>
            </div>
          </div>

          <div class="dash-section rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70" style="--stagger: 5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-3">
              <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                <UserCircleIcon class="h-4 w-4 text-indigo-600" />
              </span>
              <div class="min-w-0">
                <h2 class="font-heading text-sm font-semibold text-slate-900">Quick Links</h2>
                <p class="truncate text-xs text-slate-500">Common places based on your role and workload</p>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-2 p-4">
              <Link
                v-for="link in quickLinks"
                :key="link.label"
                :href="link.url"
                class="group flex items-center gap-3 rounded-lg bg-indigo-50/60 p-3 text-indigo-700 transition hover:-translate-y-0.5 hover:bg-indigo-50 hover:shadow-sm"
              >
                <UserCircleIcon class="h-5 w-5 shrink-0 text-indigo-500" />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold">{{ link.label }}</p>
                  <p class="truncate text-xs text-indigo-700/70">{{ link.description }}</p>
                </div>
                <ChevronRightIcon class="h-4 w-4 shrink-0 opacity-60 transition group-hover:translate-x-0.5" />
              </Link>
            </div>
          </div>

          <div
            v-if="(summary.overdue_documents ?? 0) > 0"
            class="dash-section rounded-2xl border border-danger-100 bg-danger-50 p-4 text-danger-700"
            style="--stagger: 6"
          >
            <div class="flex gap-3">
              <ExclamationTriangleIcon class="h-5 w-5 shrink-0" />
              <div>
                <p class="text-sm font-semibold">You have overdue routed document(s).</p>
                <p class="mt-1 text-sm text-danger-700">Open the document from Needs Action and record the required action.</p>
              </div>
            </div>
          </div>
        </aside>
      </section>
    </div>
  </AdminLayout>
</template>

<style scoped>
/* Staggered entrance — each section fades/slides in, offset by --stagger */
.dash-section {
  animation: dash-fade-up 400ms ease both;
  animation-delay: calc(var(--stagger, 0) * 60ms);
}

@keyframes dash-fade-up {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .dash-section {
    animation: none;
  }
}

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

.dashboard-calendar :deep(.dash-calendar-leave),
.dashboard-calendar :deep(.dash-calendar-travel),
.dashboard-calendar :deep(.dash-calendar-activity),
.dashboard-calendar :deep(.dash-calendar-facility),
.dashboard-calendar :deep(.dash-calendar-vehicle),
.dashboard-calendar :deep(.dash-calendar-service),
.dashboard-calendar :deep(.dash-calendar-document) { background: #0867DB; }
.dashboard-calendar :deep(.dash-calendar-overdue) { background: #DC2626; }
</style>
