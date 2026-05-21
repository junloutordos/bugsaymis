<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { BellIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { BellAlertIcon } from '@heroicons/vue/24/solid'
import axios from 'axios'

const props = defineProps({
  userId: { type: Number, required: true },
})

const open          = ref(false)
const notifications = ref([])
const unreadCount   = ref(0)
const loading       = ref(false)

// ── Fetch ────────────────────────────────────────────────────────────────────
async function fetchNotifications() {
  loading.value = true
  try {
    const { data } = await axios.get(route('notifications.index'))
    notifications.value = data.notifications
    unreadCount.value   = data.unread_count
  } catch (e) {
    // silent — bell stays at last known state
  } finally {
    loading.value = false
  }
}

async function markRead(id) {
  await axios.post(route('notifications.read', id))
  const n = notifications.value.find(n => n.id === id)
  if (n) n.read_at = new Date().toISOString()
  unreadCount.value = Math.max(0, unreadCount.value - 1)
}

async function markAllRead() {
  await axios.post(route('notifications.read-all'))
  notifications.value.forEach(n => { n.read_at = n.read_at ?? new Date().toISOString() })
  unreadCount.value = 0
}

function handleClick(n) {
  if (!n.read_at) markRead(n.id)
  open.value = false
  if (n.url && n.url !== '#') window.location.href = n.url
}

function timeAgo(iso) {
  const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (diff < 60)   return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400)return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

// ── Real-time via Soketi ─────────────────────────────────────────────────────
function subscribe() {
  if (!window.Echo) return
  window.Echo.private(`user.${props.userId}`)
    .listen('.request.status.updated', (payload) => {
      notifications.value.unshift({
        id:           crypto.randomUUID(),
        request_type: payload.request_type,
        reference_no: payload.reference_no,
        status:       payload.status,
        url:          payload.url,
        remarks:      payload.remarks ?? null,
        read_at:      null,
        created_at:   payload.created_at ?? new Date().toISOString(),
      })
      unreadCount.value += 1
    })
}

// ── Click-outside close ──────────────────────────────────────────────────────
function onClickOutside(e) {
  if (!e.target.closest('[data-notification-bell]')) open.value = false
}

// ── Web Push ─────────────────────────────────────────────────────────────────
const pushEnabled = ref(false)

function urlBase64ToUint8Array(base64) {
  const pad = '='.repeat((4 - base64.length % 4) % 4)
  const b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(b64)
  return Uint8Array.from([...raw].map(c => c.charCodeAt(0)))
}

async function registerServiceWorker() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return
  const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY
  if (!vapidKey) return  // not configured in this build

  try {
    const reg = await navigator.serviceWorker.register('/crcmis-sw.js')
    const perm = await Notification.requestPermission()
    if (perm !== 'granted') return

    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapidKey),
    })
    const json = sub.toJSON()
    await axios.post(route('push.subscribe'), {
      endpoint:         json.endpoint,
      keys:             json.keys,
      content_encoding: (sub.options?.applicationServerKey ? 'aes128gcm' : 'aesgcm'),
    })
    pushEnabled.value = true
  } catch (e) {
    // Web Push not available or denied — app works without it
  }
}

onMounted(() => {
  fetchNotifications()
  subscribe()
  document.addEventListener('click', onClickOutside)
  // Register service worker silently — only prompts if permission not yet decided
  if (Notification.permission === 'default') {
    registerServiceWorker()
  } else if (Notification.permission === 'granted') {
    registerServiceWorker()
    pushEnabled.value = true
  }
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside)
})
</script>

<template>
  <div class="relative" data-notification-bell>

    <!-- Bell button -->
    <button
      @click.stop="open = !open"
      class="relative p-1.5 rounded-lg text-blue-200/70 hover:bg-white/10 hover:text-white transition-all duration-150 focus:outline-none"
      aria-label="Notifications"
    >
      <component :is="unreadCount > 0 ? BellAlertIcon : BellIcon" class="h-5 w-5" />
      <span
        v-if="unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white leading-none"
      >
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown -->
    <Teleport to="body">
      <div
        v-if="open"
        class="fixed z-[200] w-80 rounded-2xl border border-slate-100 bg-white shadow-2xl overflow-hidden"
        style="top: 56px; right: 12px;"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <BellAlertIcon class="h-4 w-4 text-indigo-500" />
            <span class="text-sm font-semibold text-slate-800">Notifications</span>
            <span v-if="unreadCount > 0"
              class="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-600">
              {{ unreadCount }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="unreadCount > 0"
              @click="markAllRead"
              class="text-[11px] font-medium text-indigo-600 hover:text-indigo-800"
            >Mark all read</button>
            <button @click="open = false" class="text-slate-400 hover:text-slate-600">
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>
        </div>

        <!-- List -->
        <div class="max-h-96 overflow-y-auto divide-y divide-slate-50">
          <div v-if="loading" class="py-8 text-center text-sm text-slate-400">Loading…</div>

          <div v-else-if="notifications.length === 0"
            class="py-10 text-center text-sm text-slate-400">
            No notifications yet.
          </div>

          <div
            v-for="n in notifications"
            :key="n.id"
            @click="handleClick(n)"
            class="flex gap-3 px-4 py-3 cursor-pointer transition-colors"
            :class="n.read_at ? 'hover:bg-slate-50' : 'bg-indigo-50/60 hover:bg-indigo-50'"
          >
            <!-- Unread dot -->
            <div class="mt-1.5 shrink-0">
              <span
                class="block h-2 w-2 rounded-full"
                :class="n.read_at ? 'bg-slate-200' : 'bg-indigo-500'"
              ></span>
            </div>

            <div class="min-w-0 flex-1">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                {{ n.request_type }}
              </p>
              <p class="text-sm font-medium text-slate-800 leading-snug truncate">
                {{ n.reference_no }}
              </p>
              <p class="text-xs text-indigo-600 font-medium mt-0.5">{{ n.status }}</p>
              <p v-if="n.remarks" class="text-xs text-slate-500 mt-0.5 truncate">{{ n.remarks }}</p>
              <p class="text-[11px] text-slate-400 mt-1">{{ timeAgo(n.created_at) }}</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
