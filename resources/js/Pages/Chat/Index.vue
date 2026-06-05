<script setup>
import { onMounted, onUnmounted, ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useChat } from '@/Composables/useChat.js'
import {
  PaperAirplaneIcon,
  PaperClipIcon,
  MagnifyingGlassIcon,
  ArrowLeftIcon,
  UserCircleIcon,
  XMarkIcon,
  ChevronUpIcon,
  ArchiveBoxIcon,
  EllipsisVerticalIcon,
  TrashIcon,
  UserGroupIcon,
  CheckIcon as CheckOutline,
} from '@heroicons/vue/24/outline'
import { CheckIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  authUser: { type: Object, required: true },
})

const {
  conversations, activeConversation, messages, pagination,
  userSearchResults, userSearchQ, showArchived,
  loadingConversations, loadingMessages, sendingMessage, searchingUsers,
  messageInput, attachmentFile, messagesEl,
  totalUnread, canSend,
  fetchConversations, openConversation, loadMoreMessages,
  sendMessage, deleteMessage, archiveConversation,
  startDM, startGroup, loadUsers, searchUsers, onFileSelected,
  injectIncomingMessage, applyReadReceipt, applyMessageDeleted,
  formatTime, initials,
} = useChat(props.authUser)

// ── Real-time ─────────────────────────────────────────────────────────────
const echoConnected = ref(false)
const subscribedIds = new Set()

function subscribeToConversation(convId) {
  if (subscribedIds.has(convId) || !window.Echo) return
  subscribedIds.add(convId)
  window.Echo.private(`conversation.${convId}`)
    .listen('.message.sent', ({ message }) => {
      if (message.sender_id !== props.authUser.id) injectIncomingMessage(message)
    })
    .listen('.message.read', (payload) => {
      applyReadReceipt(payload)
    })
    .listen('.message.deleted', (payload) => {
      applyMessageDeleted(payload)
    })
}

function subscribeToPersonalChannel() {
  if (!window.Echo) return
  window.Echo.private(`user.${props.authUser.id}`)
    .listen('.new.message', ({ message }) => {
      if (message.sender_id !== props.authUser.id) injectIncomingMessage(message)
    })
}

function unsubscribeAll() {
  subscribedIds.forEach(id => { try { window.Echo?.leave(`conversation.${id}`) } catch (_) {} })
  subscribedIds.clear()
  try { window.Echo?.leave(`user.${props.authUser.id}`) } catch (_) {}
}

watch(conversations, list => list.forEach(c => subscribeToConversation(c.id)), { deep: false, immediate: true })
watch(activeConversation, conv => { if (conv) subscribeToConversation(conv.id) })

function monitorEchoConnection() {
  if (!window.Echo?.connector?.pusher) return
  const pusher = window.Echo.connector.pusher
  pusher.connection.bind('connected',    () => { echoConnected.value = true  })
  pusher.connection.bind('disconnected', () => { echoConnected.value = false })
  pusher.connection.bind('unavailable',  () => { echoConnected.value = false })
  echoConnected.value = pusher.connection.state === 'connected'
}

onUnmounted(unsubscribeAll)

// ── Local UI state ────────────────────────────────────────────────────────
const showSidebar    = ref(true)
const showNewChat    = ref(false)
const newChatMode    = ref('dm')          // 'dm' | 'group'
const convSearch     = ref('')
const fileInput      = ref(null)
const newChatInput   = ref(null)

// Group creation state
const groupName         = ref('')
const selectedGroupUsers = ref([])       // array of user objects

// Message context menu
const contextMenu    = ref({ visible: false, msgId: null, x: 0, y: 0 })
let longPressTimer   = null

// ── Tabs: Chats vs Archived ───────────────────────────────────────────────
async function switchTab(archived) {
  showArchived.value = archived
  await fetchConversations(archived)
  activeConversation.value = null
  showSidebar.value = true
}

watch(showNewChat, open => {
  if (open) {
    newChatMode.value = 'dm'
    groupName.value = ''
    selectedGroupUsers.value = []
    loadUsers()
    setTimeout(() => newChatInput.value?.focus(), 50)
  } else {
    userSearchQ.value = ''
  }
})

const filteredConversations = computed(() => {
  const q = convSearch.value.toLowerCase().trim()
  if (!q) return conversations.value
  return conversations.value.filter(c => (c.name ?? '').toLowerCase().includes(q))
})

// ── File attachment (base64) ──────────────────────────────────────────────
function pickFile() { fileInput.value?.click() }
async function onFileChange(e) {
  const file = e.target.files[0] ?? null
  e.target.value = ''
  await onFileSelected(file)
}

// ── Open conversation ─────────────────────────────────────────────────────
async function selectConversation(conv) {
  await openConversation(conv)
  showSidebar.value = false
}

// ── Send on Enter ─────────────────────────────────────────────────────────
function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage() }
}

// ── Archive ───────────────────────────────────────────────────────────────
async function handleArchive(convId, e) {
  e.stopPropagation()
  await archiveConversation(convId)
}

// ── Delete message ────────────────────────────────────────────────────────
function openContextMenu(e, msg) {
  if (msg.sender_id !== props.authUser.id || msg.is_deleted || msg._pending) return
  e.preventDefault()
  const rect = e.currentTarget?.getBoundingClientRect?.() ?? { left: e.clientX, top: e.clientY }
  contextMenu.value = { visible: true, msgId: msg.id, convId: msg.conversation_id, x: e.clientX, y: e.clientY }
}

function startLongPress(e, msg) {
  longPressTimer = setTimeout(() => openContextMenu(e, msg), 400)
}

function cancelLongPress() {
  clearTimeout(longPressTimer)
}

async function confirmDelete() {
  const { msgId, convId } = contextMenu.value
  contextMenu.value.visible = false
  await deleteMessage(convId, msgId)
}

function closeContextMenu() { contextMenu.value.visible = false }

// Close on outside click
function onDocClick(e) {
  if (contextMenu.value.visible) closeContextMenu()
}

// ── Group multi-select ────────────────────────────────────────────────────
function toggleGroupUser(u) {
  const idx = selectedGroupUsers.value.findIndex(s => s.id === u.id)
  if (idx === -1) selectedGroupUsers.value.push(u)
  else selectedGroupUsers.value.splice(idx, 1)
}

function isGroupSelected(uid) {
  return selectedGroupUsers.value.some(u => u.id === uid)
}

async function createGroup() {
  if (!groupName.value.trim() || selectedGroupUsers.value.length < 1) return
  try {
    await startGroup(groupName.value.trim(), selectedGroupUsers.value.map(u => u.id))
    showNewChat.value = false
    showSidebar.value = false
  } catch (_) {}
}

// ── Message status helpers ────────────────────────────────────────────────
// DMs: _pending → single gray tick | sent+unread → single gray tick | read → double blue tick
function msgStatus(msg) {
  if (msg._pending) return 'sending'
  if (msg._failed)  return 'failed'
  if (msg.read_at)  return 'seen'
  return 'sent'
}

// ── Init ──────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchConversations(false)
  monitorEchoConnection()
  subscribeToPersonalChannel()
  document.addEventListener('click', onDocClick)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
})
</script>

<template>
  <Head title="Chat" />
  <AdminLayout title="Chat">

    <!-- Full-height messenger shell (uses dvh for mobile keyboard safety) -->
    <div class="flex overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
         style="height: min(calc(100dvh - 8rem), 800px)">

      <!-- ══════════════════════════════════════════════
           SIDEBAR
      ══════════════════════════════════════════════ -->
      <aside :class="[
        'flex flex-col shrink-0 border-r border-slate-100 bg-white transition-all duration-200',
        'w-full sm:w-72 lg:w-80',
        showSidebar
          ? 'translate-x-0'
          : '-translate-x-full sm:translate-x-0 absolute sm:relative inset-y-0 left-0 z-10',
        !showSidebar ? 'absolute inset-y-0 left-0 z-10' : 'relative',
      ]">

        <!-- Sidebar header -->
        <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold text-slate-800">
              Messages
              <span v-if="totalUnread > 0"
                class="ml-1 inline-flex items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold text-white">
                {{ totalUnread > 99 ? '99+' : totalUnread }}
              </span>
            </h2>
            <span :title="echoConnected ? 'Connected' : 'Connecting…'"
                  :class="['inline-block h-2 w-2 rounded-full transition-colors', echoConnected ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse']" />
          </div>
          <button @click="showNewChat = true"
                  class="flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-100 transition-colors">
            <span class="text-base leading-none">+</span> New
          </button>
        </div>

        <!-- Tabs: Chats / Archived -->
        <div class="flex border-b border-slate-100">
          <button @click="switchTab(false)"
                  :class="['flex-1 py-2 text-xs font-medium transition-colors', !showArchived ? 'text-indigo-600 border-b-2 border-indigo-500' : 'text-slate-400 hover:text-slate-600']">
            Chats
          </button>
          <button @click="switchTab(true)"
                  :class="['flex-1 py-2 text-xs font-medium transition-colors flex items-center justify-center gap-1', showArchived ? 'text-indigo-600 border-b-2 border-indigo-500' : 'text-slate-400 hover:text-slate-600']">
            <ArchiveBoxIcon class="h-3.5 w-3.5" /> Archived
          </button>
        </div>

        <!-- Search -->
        <div class="px-3 py-2 border-b border-slate-50">
          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
            <input v-model="convSearch" type="text" placeholder="Search…"
                   class="w-full rounded-lg border-0 bg-slate-50 pl-8 pr-3 py-1.5 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <!-- Conversation list -->
        <div class="flex-1 overflow-y-auto">
          <div v-if="loadingConversations" class="flex justify-center py-12">
            <svg class="animate-spin h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
          </div>

          <p v-else-if="!filteredConversations.length" class="py-12 text-center text-xs text-slate-400">
            {{ showArchived ? 'No archived conversations.' : 'No conversations yet.' }}
          </p>

          <div v-for="conv in filteredConversations" :key="conv.id"
               class="group relative flex items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 cursor-pointer active:bg-slate-100"
               :class="activeConversation?.id === conv.id ? 'bg-indigo-50 border-l-2 border-indigo-500' : ''"
               @click="selectConversation(conv)">

            <!-- Avatar -->
            <div class="relative shrink-0">
              <img v-if="conv.avatar" :src="conv.avatar" class="h-10 w-10 rounded-full object-cover" />
              <div v-else class="flex h-10 w-10 items-center justify-center rounded-full font-bold text-sm"
                   :class="conv.type === 'group' ? 'bg-violet-100 text-violet-600' : 'bg-indigo-100 text-indigo-600'">
                <UserGroupIcon v-if="conv.type === 'group'" class="h-5 w-5" />
                <span v-else>{{ initials(conv.name) }}</span>
              </div>
              <span v-if="conv.unread_count > 0"
                    class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[9px] font-bold text-white ring-2 ring-white">
                {{ conv.unread_count > 9 ? '9+' : conv.unread_count }}
              </span>
            </div>

            <!-- Info -->
            <div class="min-w-0 flex-1">
              <div class="flex items-baseline justify-between gap-1">
                <p :class="['truncate text-xs font-semibold', conv.unread_count > 0 ? 'text-slate-900' : 'text-slate-700']">
                  {{ conv.name ?? 'Unknown' }}
                </p>
                <span class="shrink-0 text-[10px] text-slate-400">
                  {{ formatTime(conv.latest_message?.created_at ?? conv.updated_at) }}
                </span>
              </div>
              <p :class="['truncate text-[11px]', conv.unread_count > 0 ? 'font-semibold text-slate-700' : 'text-slate-400']">
                <span v-if="conv.latest_message?.is_deleted" class="italic">Message deleted</span>
                <template v-else>
                  <span v-if="conv.latest_message?.sender_id === authUser.id" class="text-slate-400">You: </span>
                  {{ conv.latest_message?.body || '📎 Attachment' }}
                </template>
              </p>
            </div>

            <!-- Archive button (visible on hover) -->
            <button @click.stop="handleArchive(conv.id, $event)"
                    :title="showArchived ? 'Unarchive' : 'Archive'"
                    class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg p-1 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50">
              <ArchiveBoxIcon class="h-4 w-4" />
            </button>
          </div>
        </div>
      </aside>


      <!-- ══════════════════════════════════════════════
           MAIN — Chat Window
      ══════════════════════════════════════════════ -->
      <div class="flex flex-1 flex-col min-w-0">

        <!-- Empty state -->
        <div v-if="!activeConversation" class="flex flex-1 flex-col items-center justify-center gap-3 text-slate-400">
          <UserCircleIcon class="h-14 w-14 opacity-20" />
          <p class="text-sm">Select a conversation or start a new one</p>
        </div>

        <template v-else>
          <!-- Chat header -->
          <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 shrink-0">
            <button class="sm:hidden p-1.5 -ml-1 rounded-lg hover:bg-slate-100 text-slate-500"
                    @click="showSidebar = true">
              <ArrowLeftIcon class="h-5 w-5" />
            </button>

            <div class="relative shrink-0">
              <img v-if="activeConversation.avatar" :src="activeConversation.avatar" class="h-9 w-9 rounded-full object-cover" />
              <div v-else class="flex h-9 w-9 items-center justify-center rounded-full font-bold text-xs"
                   :class="activeConversation.type === 'group' ? 'bg-violet-100 text-violet-600' : 'bg-indigo-100 text-indigo-600'">
                <UserGroupIcon v-if="activeConversation.type === 'group'" class="h-4 w-4" />
                <span v-else>{{ initials(activeConversation.name) }}</span>
              </div>
            </div>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-800">{{ activeConversation.name }}</p>
              <p v-if="activeConversation.type === 'group'" class="text-[11px] text-slate-400">
                {{ activeConversation.participants?.length }} members
              </p>
            </div>
          </div>

          <!-- Messages area -->
          <div ref="messagesEl"
               class="flex-1 overflow-y-auto px-3 py-3 space-y-1"
               @scroll.passive="($event.target.scrollTop === 0 && !loadingMessages) && loadMoreMessages()">

            <div v-if="pagination && pagination.current_page < pagination.last_page" class="flex justify-center py-2">
              <button @click="loadMoreMessages" :disabled="loadingMessages"
                      class="flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500 hover:bg-slate-200 transition-colors disabled:opacity-50">
                <ChevronUpIcon class="h-3 w-3" />
                {{ loadingMessages ? 'Loading…' : 'Load older' }}
              </button>
            </div>

            <div v-if="loadingMessages && !messages.length" class="flex justify-center py-12">
              <svg class="animate-spin h-6 w-6 text-indigo-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
            </div>

            <template v-for="(msg, idx) in messages" :key="msg.id ?? idx">
              <!-- Date separator -->
              <div v-if="idx === 0 || new Date(messages[idx-1].created_at).toDateString() !== new Date(msg.created_at).toDateString()"
                   class="flex items-center gap-2 py-2">
                <div class="flex-1 h-px bg-slate-100"></div>
                <span class="text-[10px] text-slate-400 shrink-0">
                  {{ new Date(msg.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                </span>
                <div class="flex-1 h-px bg-slate-100"></div>
              </div>

              <!-- Message row -->
              <div :class="['flex gap-2 items-end', msg.sender_id === authUser.id ? 'flex-row-reverse' : 'flex-row']">

                <!-- Incoming avatar -->
                <div v-if="msg.sender_id !== authUser.id" class="shrink-0 mb-1">
                  <img v-if="msg.sender_avatar" :src="msg.sender_avatar" class="h-6 w-6 rounded-full object-cover" />
                  <div v-else class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-slate-500 text-[9px] font-bold">
                    {{ initials(msg.sender_name) }}
                  </div>
                </div>

                <div :class="['group/msg max-w-[75%] flex flex-col gap-0.5', msg.sender_id === authUser.id ? 'items-end' : 'items-start']">
                  <!-- Sender name in groups -->
                  <span v-if="activeConversation.type === 'group' && msg.sender_id !== authUser.id"
                        class="px-1 text-[10px] text-slate-400">
                    {{ msg.sender_name }}
                  </span>

                  <!-- Bubble wrapper with long-press / right-click -->
                  <div class="relative"
                       @contextmenu.prevent="openContextMenu($event, msg)"
                       @touchstart.passive="startLongPress($event, msg)"
                       @touchend.passive="cancelLongPress"
                       @touchmove.passive="cancelLongPress">

                    <!-- Bubble -->
                    <div :class="[
                      'rounded-2xl px-3.5 py-2 text-sm leading-relaxed break-words',
                      msg.is_deleted
                        ? 'bg-slate-100 text-slate-400 italic text-xs'
                        : msg._failed
                        ? 'bg-red-50 text-red-600 ring-1 ring-red-200'
                        : msg._pending
                        ? 'opacity-60 ' + (msg.sender_id === authUser.id ? 'bg-indigo-400 text-white' : 'bg-slate-100 text-slate-800')
                        : msg.sender_id === authUser.id
                        ? 'bg-indigo-600 text-white rounded-br-sm'
                        : 'bg-slate-100 text-slate-800 rounded-bl-sm',
                    ]">
                      <span v-if="msg.is_deleted">Message deleted</span>
                      <template v-else>
                        <div v-if="msg.attachment_path" class="mb-1">
                          <img v-if="msg.attachment_type === 'image'"
                               :src="msg.attachment_path"
                               class="max-w-[200px] rounded-xl object-cover"
                               loading="lazy" />
                          <a v-else :href="msg.attachment_path" target="_blank"
                             :class="['flex items-center gap-1.5 text-xs underline underline-offset-2', msg.sender_id === authUser.id ? 'text-indigo-100' : 'text-indigo-600']">
                            <PaperClipIcon class="h-3.5 w-3.5" /> Attachment
                          </a>
                        </div>
                        <span v-if="msg.body" class="whitespace-pre-wrap">{{ msg.body }}</span>
                        <span v-if="msg._failed" class="block text-[10px] mt-0.5 text-red-400">Failed to send</span>
                      </template>
                    </div>

                    <!-- Desktop delete button (hover, own messages only) -->
                    <button v-if="msg.sender_id === authUser.id && !msg.is_deleted && !msg._pending"
                            @click="openContextMenu($event, msg)"
                            class="absolute -top-2 -left-6 hidden group-hover/msg:flex items-center justify-center h-5 w-5 rounded-full bg-white border border-slate-200 shadow-sm text-slate-400 hover:text-red-500 transition-colors">
                      <EllipsisVerticalIcon class="h-3.5 w-3.5" />
                    </button>
                  </div>

                  <!-- Time + status row -->
                  <div :class="['flex items-center gap-1 px-1', msg.sender_id === authUser.id ? 'flex-row-reverse' : '']">
                    <span class="text-[10px] text-slate-400">{{ formatTime(msg.created_at) }}</span>

                    <!-- DM status ticks -->
                    <template v-if="msg.sender_id === authUser.id && activeConversation.type === 'direct' && !msg.is_deleted">
                      <!-- Double blue: seen -->
                      <span v-if="msgStatus(msg) === 'seen'" class="flex text-indigo-500" title="Seen">
                        <CheckIcon class="h-3 w-3 -mr-1.5" />
                        <CheckIcon class="h-3 w-3" />
                      </span>
                      <!-- Single gray: sent -->
                      <CheckIcon v-else-if="msgStatus(msg) === 'sent'" class="h-3 w-3 text-slate-400" title="Sent" />
                      <!-- Clock: sending -->
                      <span v-else-if="msgStatus(msg) === 'sending'" class="text-[9px] text-slate-400">⏳</span>
                    </template>

                    <!-- Group: seen by count -->
                    <span v-if="msg.sender_id === authUser.id && activeConversation.type === 'group' && !msg.is_deleted && !msg._pending && msg.seen_by_count > 0"
                          class="text-[10px] text-slate-400 flex items-center gap-0.5">
                      <CheckIcon class="h-3 w-3 text-indigo-400" />
                      {{ msg.seen_by_count }}
                    </span>
                  </div>
                </div>
              </div>
            </template>

            <p v-if="!loadingMessages && !messages.length" class="py-8 text-center text-xs text-slate-400">
              No messages yet. Say hello! 👋
            </p>
          </div>

          <!-- Attachment preview -->
          <div v-if="attachmentFile" class="flex items-center gap-2 border-t border-slate-100 bg-slate-50 px-4 py-2 shrink-0">
            <PaperClipIcon class="h-4 w-4 text-slate-400 shrink-0" />
            <span class="truncate text-xs text-slate-600">{{ attachmentFile.name }}</span>
            <button @click="onFileSelected(null)" class="ml-auto text-slate-400 hover:text-slate-600">
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>

          <!-- Input bar — pb-safe for iOS home indicator -->
          <div class="border-t border-slate-100 bg-white px-3 pt-3 shrink-0"
               style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
            <div class="flex items-end gap-2">
              <button type="button" @click="pickFile"
                      class="shrink-0 rounded-xl p-2.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors active:bg-slate-200">
                <PaperClipIcon class="h-5 w-5" />
              </button>
              <input ref="fileInput" type="file" class="hidden" @change="onFileChange"
                     accept="image/*,.pdf,.doc,.docx,.xlsx,.zip" />

              <textarea v-model="messageInput" @keydown="handleKeydown"
                        placeholder="Type a message…" rows="1"
                        class="flex-1 resize-none rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors max-h-32 overflow-y-auto"
                        style="field-sizing: content" />

              <button type="button" @click="sendMessage" :disabled="!canSend"
                      :class="['shrink-0 flex items-center justify-center h-10 w-10 rounded-xl transition-all', canSend ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm active:scale-95' : 'bg-slate-100 text-slate-300 cursor-not-allowed']">
                <PaperAirplaneIcon class="h-4 w-4" />
              </button>
            </div>
            <p class="mt-1 pb-1 text-[10px] text-slate-400">Enter to send · Shift+Enter for new line</p>
          </div>
        </template>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         CONTEXT MENU (delete message)
    ══════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="contextMenu.visible"
           class="fixed z-[200] rounded-xl bg-white border border-slate-200 shadow-xl py-1 min-w-[140px]"
           :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
           @click.stop>
        <button @click="confirmDelete"
                class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
          <TrashIcon class="h-4 w-4" /> Delete message
        </button>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════════
         NEW CONVERSATION MODAL
    ══════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition enter-active-class="transition-opacity duration-150" leave-active-class="transition-opacity duration-150"
                  enter-from-class="opacity-0" leave-to-class="opacity-0">
        <div v-if="showNewChat"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 backdrop-blur-sm px-0 sm:px-4"
             @click.self="showNewChat = false">

          <div class="flex flex-col w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl overflow-hidden"
               style="max-height: 90dvh">

            <!-- Modal header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 shrink-0">
              <h3 class="text-sm font-semibold text-slate-800">New Conversation</h3>
              <button @click="showNewChat = false"
                      class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition-colors">
                <XMarkIcon class="h-4 w-4" />
              </button>
            </div>

            <!-- DM / Group tabs -->
            <div class="flex border-b border-slate-100 shrink-0">
              <button @click="newChatMode = 'dm'"
                      :class="['flex-1 py-2.5 text-xs font-medium transition-colors', newChatMode === 'dm' ? 'text-indigo-600 border-b-2 border-indigo-500' : 'text-slate-400 hover:text-slate-600']">
                Direct Message
              </button>
              <button @click="newChatMode = 'group'"
                      :class="['flex-1 py-2.5 text-xs font-medium flex items-center justify-center gap-1.5 transition-colors', newChatMode === 'group' ? 'text-indigo-600 border-b-2 border-indigo-500' : 'text-slate-400 hover:text-slate-600']">
                <UserGroupIcon class="h-3.5 w-3.5" /> Group Chat
              </button>
            </div>

            <!-- Group name (group mode only) -->
            <div v-if="newChatMode === 'group'" class="px-4 pt-3 pb-0 shrink-0">
              <input v-model="groupName" type="text" placeholder="Group name (required)"
                     class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white" />
              <p v-if="selectedGroupUsers.length > 0" class="mt-1.5 text-xs text-slate-500">
                {{ selectedGroupUsers.length }} selected: {{ selectedGroupUsers.map(u => u.name.split(' ')[0]).join(', ') }}
              </p>
            </div>

            <!-- Search -->
            <div class="px-4 pt-3 pb-2 shrink-0">
              <div class="relative">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                <input ref="newChatInput"
                       :value="userSearchQ"
                       @input="searchUsers($event.target.value)"
                       type="text"
                       placeholder="Search people…"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white" />
              </div>
            </div>

            <!-- User list -->
            <div class="flex-1 overflow-y-auto">
              <div v-if="searchingUsers" class="flex justify-center py-10">
                <svg class="animate-spin h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
              </div>

              <ul v-else-if="userSearchResults.length" class="divide-y divide-slate-50 py-1">
                <li v-for="u in userSearchResults" :key="u.id">
                  <button @click="newChatMode === 'dm' ? (startDM(u.id), showNewChat = false, showSidebar = false) : toggleGroupUser(u)"
                          :class="['flex items-center gap-3 w-full px-4 py-3 text-left transition-colors', isGroupSelected(u.id) ? 'bg-indigo-50' : 'hover:bg-indigo-50']">
                    <div class="relative shrink-0">
                      <img v-if="u.avatar" :src="u.avatar" class="h-10 w-10 rounded-full object-cover" />
                      <div v-else class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-bold text-sm">
                        {{ initials(u.name) }}
                      </div>
                      <!-- Checkmark for group selection -->
                      <div v-if="newChatMode === 'group' && isGroupSelected(u.id)"
                           class="absolute inset-0 flex items-center justify-center rounded-full bg-indigo-600/80">
                        <CheckOutline class="h-5 w-5 text-white" />
                      </div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-medium text-slate-800 truncate">{{ u.name }}</p>
                      <p class="text-xs text-slate-400 truncate">{{ u.position ?? 'Staff' }}</p>
                    </div>
                    <span v-if="newChatMode === 'dm'" class="shrink-0 text-indigo-400 text-xs font-medium opacity-0 group-hover:opacity-100">Message →</span>
                  </button>
                </li>
              </ul>

              <div v-else class="flex flex-col items-center justify-center py-10 text-slate-400">
                <UserCircleIcon class="h-10 w-10 opacity-20 mb-2" />
                <p class="text-xs">{{ userSearchQ ? 'No users found' : 'No users available' }}</p>
              </div>
            </div>

            <!-- Group create footer -->
            <div v-if="newChatMode === 'group'" class="px-5 py-3 border-t border-slate-100 shrink-0">
              <button @click="createGroup"
                      :disabled="!groupName.trim() || selectedGroupUsers.length < 1"
                      class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white py-2.5 text-sm font-medium transition-colors">
                Create Group ({{ selectedGroupUsers.length }} selected)
              </button>
            </div>

            <!-- DM footer hint -->
            <div v-else class="px-5 py-3 border-t border-slate-50 bg-slate-50/60 shrink-0">
              <p class="text-[11px] text-slate-400">Tap a person to open a direct message.</p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AdminLayout>
</template>
