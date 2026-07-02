<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import {
  UsersIcon,
  ArrowPathIcon,
  ChevronUpIcon,
  ChevronDownIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const data     = ref(null)
const loading  = ref(false)
const expanded = ref(true)
let   poll     = null

// Per-user drill-down state
const openUserId       = ref(null)
const activityCache    = ref({})   // userId -> activities[]
const activityLoading  = ref(null) // userId currently fetching

async function fetchActiveUsers() {
  loading.value = true
  try {
    const res = await window.axios.get(route('atlas.watchtower.active-users'))
    data.value = res.data
  } catch {
    // silently skip — non-critical panel
  } finally {
    loading.value = false
  }
}

async function toggleUser(user) {
  if (openUserId.value === user.id) {
    openUserId.value = null
    return
  }
  openUserId.value = user.id

  if (activityCache.value[user.id]) return
  activityLoading.value = user.id
  try {
    const res = await window.axios.get(route('atlas.watchtower.user-activity', user.id))
    activityCache.value[user.id] = res.data.activities
  } catch {
    activityCache.value[user.id] = []
  } finally {
    activityLoading.value = null
  }
}

function timeAgo(isoStr) {
  if (!isoStr) return ''
  const secs = Math.floor((Date.now() - new Date(isoStr).getTime()) / 1000)
  if (secs < 60) return `${secs}s ago`
  const mins = Math.floor(secs / 60)
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.floor(hours / 24)}d ago`
}

const actionBadge = {
  login:   'green',
  logout:  'slate',
  add:     'indigo',
  edit:    'amber',
  approve: 'green',
  decline: 'red',
  delete:  'red',
}

function activityLabel(a) {
  if (!a.subject) return null
  return a.subject_id ? `${a.subject} #${a.subject_id}` : a.subject
}

onMounted(() => {
  fetchActiveUsers()
  poll = setInterval(fetchActiveUsers, 60_000)
})

onUnmounted(() => {
  clearInterval(poll)
})
</script>

<template>
  <AppCard :padded="false">
    <template #header>
      <div class="flex w-full items-center justify-between">
        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
          <UsersIcon class="h-4 w-4" /> Active Users
          <span v-if="data" class="normal-case font-normal tracking-normal text-slate-400">
            · refreshed {{ timeAgo(data.refreshed_at) }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchActiveUsers"
            :disabled="loading"
            class="rounded-md p-1 text-slate-400 hover:text-slate-600 disabled:opacity-40"
            title="Refresh"
          >
            <ArrowPathIcon :class="['h-3.5 w-3.5', loading && 'animate-spin']" />
          </button>
          <button
            @click="expanded = !expanded"
            class="rounded-md p-1 text-slate-400 hover:text-slate-600"
          >
            <ChevronUpIcon v-if="expanded" class="h-4 w-4" />
            <ChevronDownIcon v-else class="h-4 w-4" />
          </button>
        </div>
      </div>
    </template>

    <div v-show="expanded">
      <!-- Loading skeleton -->
      <div v-if="!data && loading" class="flex items-center gap-4 p-5">
        <div v-for="i in 3" :key="i" class="h-16 w-32 animate-pulse rounded-lg bg-slate-100" />
      </div>

      <template v-else-if="data">
        <!-- Stat tiles -->
        <div class="grid grid-cols-3 gap-3 p-5 pb-4">
          <div class="rounded-lg bg-emerald-50 px-4 py-3 text-center">
            <p class="text-[10px] font-medium text-emerald-500 uppercase tracking-wide">Online Now</p>
            <p class="text-2xl font-bold text-emerald-700">{{ data.counts.now }}</p>
            <p class="text-[10px] text-emerald-400">&lt; 5 min</p>
          </div>
          <div class="rounded-lg bg-indigo-50 px-4 py-3 text-center">
            <p class="text-[10px] font-medium text-indigo-500 uppercase tracking-wide">Recently Active</p>
            <p class="text-2xl font-bold text-indigo-700">{{ data.counts.recent }}</p>
            <p class="text-[10px] text-indigo-400">last 30 min</p>
          </div>
          <div class="rounded-lg bg-slate-50 px-4 py-3 text-center">
            <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide">Today</p>
            <p class="text-2xl font-bold text-slate-700">{{ data.counts.today }}</p>
            <p class="text-[10px] text-slate-400">unique users</p>
          </div>
        </div>

        <!-- User list -->
        <div v-if="data.users.length" class="max-h-[28rem] divide-y divide-slate-50 overflow-y-auto border-t border-slate-100">
          <div v-for="user in data.users" :key="user.id">
            <button
              @click="toggleUser(user)"
              class="flex w-full items-center gap-3 px-5 py-2.5 text-left hover:bg-slate-50"
            >
              <!-- Avatar -->
              <div class="relative shrink-0">
                <div
                  class="flex h-8 w-8 items-center justify-center rounded-full text-[11px] font-bold"
                  :class="user.is_online ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                >{{ user.initials }}</div>
                <span
                  v-if="user.is_online"
                  class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"
                  title="Online now"
                />
              </div>
              <!-- Info -->
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-800">{{ user.name }}</p>
                <p class="text-[11px] text-slate-400">
                  {{ timeAgo(user.last_seen_at) }}
                  <span v-if="user.browser"> · {{ user.browser }}</span>
                  <span v-if="user.ip_address" class="font-mono"> · {{ user.ip_address }}</span>
                </p>
              </div>
              <ChevronDownIcon v-if="openUserId === user.id" class="h-4 w-4 shrink-0 text-slate-400" />
              <ChevronRightIcon v-else class="h-4 w-4 shrink-0 text-slate-300" />
            </button>

            <!-- Recent activity panel -->
            <div v-if="openUserId === user.id" class="bg-slate-50/60 px-5 pb-3 pt-1">
              <div v-if="activityLoading === user.id" class="space-y-1.5 py-1">
                <div v-for="i in 3" :key="i" class="h-5 animate-pulse rounded bg-slate-100" />
              </div>
              <template v-else>
                <div v-if="(activityCache[user.id] ?? []).length" class="space-y-1">
                  <div
                    v-for="(a, i) in activityCache[user.id]"
                    :key="i"
                    class="flex items-center gap-2 text-xs"
                  >
                    <AppBadge :color="actionBadge[a.action] ?? 'slate'" class="w-16 justify-center shrink-0">
                      {{ a.action }}
                    </AppBadge>
                    <span class="min-w-0 flex-1 truncate text-slate-600" :title="a.path ?? ''">
                      {{ activityLabel(a) ?? a.path ?? '—' }}
                    </span>
                    <span class="shrink-0 text-slate-400">{{ timeAgo(a.time) }}</span>
                  </div>
                </div>
                <p v-else class="py-1 text-xs text-slate-400">No recorded activity.</p>
              </template>
            </div>
          </div>
        </div>
        <p v-else class="border-t border-slate-100 px-5 py-3 text-sm text-slate-400">
          No users active in the last 30 minutes.
        </p>
      </template>
    </div>
  </AppCard>
</template>
