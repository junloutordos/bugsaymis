<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
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
} from '@heroicons/vue/24/outline'
import { CheckIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  authUser: { type: Object, required: true },
})

const {
  conversations, activeConversation, messages, pagination,
  userSearchResults, userSearchQ,
  loadingConversations, loadingMessages, sendingMessage, searchingUsers,
  messageInput, attachmentFile, messagesEl,
  totalUnread, canSend,
  fetchConversations, openConversation, loadMoreMessages,
  sendMessage, startDM, loadUsers, searchUsers,
  injectIncomingMessage, applyReadReceipt,
  formatTime, initials,
} = useChat(props.authUser)

// ── Real-time: Echo channel subscriptions ─────────────────────────────────
const echoConnected  = ref(false)
const subscribedIds  = new Set()

function subscribeToConversation(convId) {
  if (subscribedIds.has(convId) || !window.Echo) return
  subscribedIds.add(convId)

  window.Echo
    .private(`conversation.${convId}`)
    .listen('.message.sent', ({ message }) => {
      // Ignore our own messages — we already added them optimistically
      if (message.sender_id !== props.authUser.id) {
        injectIncomingMessage(message)
      }
    })
    .listen('.message.read', (payload) => {
      applyReadReceipt(payload)
    })
}

function subscribeToPersonalChannel() {
  if (!window.Echo) return
  window.Echo
    .private(`user.${props.authUser.id}`)
    .listen('.new.message', ({ message }) => {
      // Fires when a message arrives in a conversation we may not be viewing.
      // injectIncomingMessage handles both active-conversation append and badge increment.
      if (message.sender_id !== props.authUser.id) {
        injectIncomingMessage(message)
      }
    })
}

function unsubscribeAll() {
  subscribedIds.forEach(id => {
    try { window.Echo?.leave(`conversation.${id}`) } catch (_) {}
  })
  subscribedIds.clear()
  try { window.Echo?.leave(`user.${props.authUser.id}`) } catch (_) {}
}

// Subscribe to all loaded conversations — immediate:true catches conversations
// already loaded before the watcher is registered (avoids race condition)
watch(conversations, (list) => {
  list.forEach(c => subscribeToConversation(c.id))
}, { deep: false, immediate: true })

// Subscribe immediately when a new conversation becomes active
watch(activeConversation, (conv) => {
  if (conv) subscribeToConversation(conv.id)
})

// Monitor Echo connection state for the status indicator
function monitorEchoConnection() {
  if (!window.Echo?.connector?.pusher) return
  const pusher = window.Echo.connector.pusher
  pusher.connection.bind('connected',    () => { echoConnected.value = true  })
  pusher.connection.bind('disconnected', () => { echoConnected.value = false })
  pusher.connection.bind('unavailable',  () => { echoConnected.value = false })
  // Reflect current state immediately
  echoConnected.value = pusher.connection.state === 'connected'
}

onUnmounted(unsubscribeAll)

// ── Local UI state ─────────────────────────────────────────────────────────
const showSidebar       = ref(true)   // mobile: toggle sidebar vs chat
const showNewChat       = ref(false)  // new conversation picker modal
const convSearch        = ref('')     // sidebar conversation filter
const fileInput         = ref(null)
const newChatInput      = ref(null)   // template ref for autofocus

// Load all users when the picker opens; clear when it closes
watch(showNewChat, (open) => {
  if (open) {
    loadUsers()
    // autofocus the search input after the panel renders
    setTimeout(() => newChatInput.value?.focus(), 50)
  } else {
    userSearchQ.value = ''
  }
})

// Filtered conversations (sidebar search)
const filteredConversations = () => {
  const q = convSearch.value.toLowerCase().trim()
  if (!q) return conversations.value
  return conversations.value.filter(c =>
    (c.name ?? '').toLowerCase().includes(q)
  )
}

// ── File attachment ────────────────────────────────────────────────────────
function pickFile() { fileInput.value?.click() }
function onFileChange(e) {
  attachmentFile.value = e.target.files[0] ?? null
  e.target.value = ''
}

// ── Open conversation (handles mobile panel switch) ───────────────────────
async function selectConversation(conv) {
  await openConversation(conv)
  showSidebar.value = false   // on mobile, switch to chat panel
}

// ── Send on Enter (Shift+Enter = newline) ─────────────────────────────────
function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    sendMessage()
  }
}

// ── Init ──────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchConversations()
  monitorEchoConnection()
  subscribeToPersonalChannel()
})
</script>

<template>
  <Head title="Chat" />
  <AdminLayout title="Chat">

    <!-- ── Full-height messenger shell ──────────────────────────────────── -->
    <div class="flex h-[calc(100vh-8rem)] overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">

      <!-- ══════════════════════════════════════════════════════════════════
           SIDEBAR — Conversation List
      ══════════════════════════════════════════════════════════════════════ -->
      <aside
        :class="[
          'flex flex-col w-full sm:w-72 lg:w-80 shrink-0 border-r border-slate-100',
          'transition-transform duration-200',
          showSidebar ? 'translate-x-0' : '-translate-x-full sm:translate-x-0',
          'absolute sm:relative inset-y-0 left-0 z-10 bg-white',
        ]"
      >
        <!-- Header -->
        <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold text-slate-800">
              Messages
              <span v-if="totalUnread > 0"
                class="ml-1.5 inline-flex items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold text-white">
                {{ totalUnread > 99 ? '99+' : totalUnread }}
              </span>
            </h2>
            <!-- WebSocket connection indicator -->
            <span
              :title="echoConnected ? 'Real-time connected' : 'Connecting…'"
              :class="[
                'inline-block h-2 w-2 rounded-full transition-colors',
                echoConnected ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse',
              ]"
            ></span>
          </div>
          <button
            @click="showNewChat = !showNewChat"
            class="flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-100 transition-colors"
          >
            <span class="text-base leading-none">+</span> New
          </button>
        </div>


        <!-- Conversation search -->
        <div class="px-3 py-2 border-b border-slate-50">
          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
            <input
              v-model="convSearch"
              type="text"
              placeholder="Search conversations…"
              class="w-full rounded-lg border-0 bg-slate-50 pl-8 pr-3 py-1.5 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <!-- Conversation list -->
        <div class="flex-1 overflow-y-auto">
          <div v-if="loadingConversations" class="flex items-center justify-center py-12">
            <svg class="animate-spin h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
          </div>

          <p v-else-if="!conversations.length" class="py-12 text-center text-xs text-slate-400">
            No conversations yet.<br/>Start a new one above.
          </p>

          <button
            v-for="conv in filteredConversations()"
            :key="conv.id"
            @click="selectConversation(conv)"
            :class="[
              'flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50',
              activeConversation?.id === conv.id ? 'bg-indigo-50 border-l-2 border-indigo-500' : '',
            ]"
          >
            <!-- Avatar -->
            <div class="relative shrink-0">
              <img v-if="conv.avatar" :src="conv.avatar" class="h-10 w-10 rounded-full object-cover" />
              <div v-else class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-bold text-sm">
                {{ initials(conv.name) }}
              </div>
              <!-- Unread dot -->
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
                <span v-if="conv.latest_message?.sender_id === authUser.id" class="text-slate-400">You: </span>
                {{ conv.latest_message?.body || '📎 Attachment' }}
              </p>
            </div>
          </button>
        </div>
      </aside>

      <!-- ══════════════════════════════════════════════════════════════════
           MAIN — Chat Window
      ══════════════════════════════════════════════════════════════════════ -->
      <div class="flex flex-1 flex-col min-w-0">

        <!-- No conversation selected -->
        <div v-if="!activeConversation" class="flex flex-1 flex-col items-center justify-center gap-3 text-slate-400">
          <UserCircleIcon class="h-14 w-14 opacity-20" />
          <p class="text-sm">Select a conversation or start a new one</p>
        </div>

        <template v-else>
          <!-- Chat Header -->
          <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
            <!-- Back button (mobile) -->
            <button
              class="sm:hidden p-1 rounded-lg hover:bg-slate-100 text-slate-500"
              @click="showSidebar = true"
            >
              <ArrowLeftIcon class="h-4 w-4" />
            </button>

            <img v-if="activeConversation.avatar" :src="activeConversation.avatar" class="h-8 w-8 rounded-full object-cover" />
            <div v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-bold text-xs">
              {{ initials(activeConversation.name) }}
            </div>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-800">{{ activeConversation.name }}</p>
              <p v-if="activeConversation.type === 'group'" class="text-[11px] text-slate-400">
                {{ activeConversation.participants?.length }} members
              </p>
            </div>
          </div>

          <!-- Messages area -->
          <div
            ref="messagesEl"
            class="flex-1 overflow-y-auto px-4 py-4 space-y-1"
            @scroll.passive="($event.target.scrollTop === 0 && !loadingMessages) && loadMoreMessages()"
          >
            <!-- Load more -->
            <div v-if="pagination && pagination.current_page < pagination.last_page" class="flex justify-center py-2">
              <button
                @click="loadMoreMessages"
                :disabled="loadingMessages"
                class="flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500 hover:bg-slate-200 transition-colors disabled:opacity-50"
              >
                <ChevronUpIcon class="h-3 w-3" />
                {{ loadingMessages ? 'Loading…' : 'Load older messages' }}
              </button>
            </div>

            <!-- Loading spinner (initial load) -->
            <div v-if="loadingMessages && !messages.length" class="flex justify-center py-12">
              <svg class="animate-spin h-6 w-6 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
            </div>

            <!-- Messages -->
            <template v-for="(msg, idx) in messages" :key="msg.id">
              <!-- Date separator -->
              <div
                v-if="idx === 0 || new Date(messages[idx-1].created_at).toDateString() !== new Date(msg.created_at).toDateString()"
                class="flex items-center gap-2 py-2"
              >
                <div class="flex-1 h-px bg-slate-100"></div>
                <span class="text-[10px] text-slate-400 shrink-0">
                  {{ new Date(msg.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                </span>
                <div class="flex-1 h-px bg-slate-100"></div>
              </div>

              <!-- Message bubble -->
              <div :class="['flex gap-2 items-end', msg.sender_id === authUser.id ? 'flex-row-reverse' : 'flex-row']">
                <!-- Avatar (only for received messages) -->
                <div v-if="msg.sender_id !== authUser.id" class="shrink-0 mb-1">
                  <img v-if="msg.sender_avatar" :src="msg.sender_avatar" class="h-6 w-6 rounded-full object-cover" />
                  <div v-else class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-slate-500 text-[9px] font-bold">
                    {{ initials(msg.sender_name) }}
                  </div>
                </div>

                <div :class="['group max-w-[70%]', msg.sender_id === authUser.id ? 'items-end' : 'items-start', 'flex flex-col gap-0.5']">
                  <!-- Sender name (group chats, received) -->
                  <span
                    v-if="activeConversation.type === 'group' && msg.sender_id !== authUser.id"
                    class="px-1 text-[10px] text-slate-400"
                  >
                    {{ msg.sender_name }}
                  </span>

                  <!-- Bubble -->
                  <div :class="[
                    'rounded-2xl px-3.5 py-2 text-sm leading-relaxed',
                    msg._failed  ? 'bg-red-50 text-red-600 ring-1 ring-red-200' :
                    msg._pending ? 'bg-indigo-400 text-white opacity-70' :
                    msg.sender_id === authUser.id
                      ? 'bg-indigo-600 text-white rounded-br-sm'
                      : 'bg-slate-100 text-slate-800 rounded-bl-sm',
                  ]">
                    <!-- Attachment -->
                    <div v-if="msg.attachment_path" class="mb-1">
                      <img v-if="msg.attachment_type === 'image'"
                        :src="msg.attachment_path"
                        class="max-w-[220px] rounded-xl object-cover"
                        loading="lazy"
                      />
                      <a v-else
                        :href="msg.attachment_path"
                        target="_blank"
                        :class="['flex items-center gap-1.5 text-xs underline underline-offset-2', msg.sender_id === authUser.id ? 'text-indigo-100' : 'text-indigo-600']"
                      >
                        <PaperClipIcon class="h-3.5 w-3.5" /> Attachment
                      </a>
                    </div>

                    <span v-if="msg.body" class="whitespace-pre-wrap break-words">{{ msg.body }}</span>
                    <span v-if="msg._failed" class="block text-[10px] mt-0.5 text-red-400">Failed to send</span>
                  </div>

                  <!-- Time + read receipt row -->
                  <div :class="['flex items-center gap-1 px-1', msg.sender_id === authUser.id ? 'flex-row-reverse' : '']">
                    <span class="text-[10px] text-slate-400">{{ formatTime(msg.created_at) }}</span>
                    <!-- Read tick (DM sent messages only) -->
                    <CheckIcon
                      v-if="msg.sender_id === authUser.id && activeConversation.type === 'direct' && msg.read_at && !msg._pending"
                      class="h-3 w-3 text-indigo-400"
                    />
                  </div>
                </div>
              </div>
            </template>

            <p v-if="!loadingMessages && !messages.length" class="py-8 text-center text-xs text-slate-400">
              No messages yet. Say hello! 👋
            </p>
          </div>

          <!-- Attachment preview bar -->
          <div v-if="attachmentFile" class="flex items-center gap-2 border-t border-slate-100 bg-slate-50 px-4 py-2">
            <PaperClipIcon class="h-4 w-4 text-slate-400 shrink-0" />
            <span class="truncate text-xs text-slate-600">{{ attachmentFile.name }}</span>
            <button @click="attachmentFile = null" class="ml-auto text-slate-400 hover:text-slate-600">
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>

          <!-- Message Input Bar -->
          <div class="border-t border-slate-100 bg-white px-4 py-3">
            <div class="flex items-end gap-2">
              <!-- Attachment button -->
              <button
                type="button"
                @click="pickFile"
                class="shrink-0 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                title="Attach file"
              >
                <PaperClipIcon class="h-5 w-5" />
              </button>
              <input ref="fileInput" type="file" class="hidden" @change="onFileChange" accept="image/*,.pdf,.doc,.docx,.xlsx,.zip" />

              <!-- Text area -->
              <textarea
                v-model="messageInput"
                @keydown="handleKeydown"
                placeholder="Type a message…"
                rows="1"
                class="flex-1 resize-none rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors max-h-32 overflow-y-auto"
                style="field-sizing: content"
              ></textarea>

              <!-- Send button -->
              <button
                type="button"
                @click="sendMessage"
                :disabled="!canSend"
                :class="[
                  'shrink-0 flex items-center justify-center h-9 w-9 rounded-xl transition-all',
                  canSend
                    ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm'
                    : 'bg-slate-100 text-slate-300 cursor-not-allowed',
                ]"
              >
                <PaperAirplaneIcon class="h-4 w-4" />
              </button>
            </div>
            <p class="mt-1.5 text-[10px] text-slate-400">Enter to send · Shift+Enter for new line</p>
          </div>
        </template>
      </div>

    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         NEW CONVERSATION MODAL — full user picker
    ══════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-150"
        leave-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showNewChat"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
          @click.self="showNewChat = false"
        >
          <div class="flex flex-col w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden">
            <!-- Modal header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
              <h3 class="text-sm font-semibold text-slate-800">New Message</h3>
              <button
                @click="showNewChat = false"
                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
              >
                <XMarkIcon class="h-4 w-4" />
              </button>
            </div>

            <!-- Search input -->
            <div class="px-4 pt-3 pb-2">
              <div class="relative">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                <input
                  ref="newChatInput"
                  :value="userSearchQ"
                  @input="searchUsers($event.target.value)"
                  type="text"
                  placeholder="Search by name…"
                  class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-4 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors"
                />
              </div>
            </div>

            <!-- User list -->
            <div class="flex-1 overflow-y-auto" style="max-height: 380px; min-height: 120px;">
              <!-- Loading -->
              <div v-if="searchingUsers" class="flex items-center justify-center py-10">
                <svg class="animate-spin h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
              </div>

              <!-- Results -->
              <ul v-else-if="userSearchResults.length" class="divide-y divide-slate-50 py-1">
                <li v-for="u in userSearchResults" :key="u.id">
                  <button
                    @click="startDM(u.id); showNewChat = false"
                    class="flex items-center gap-3 w-full px-4 py-3 text-left hover:bg-indigo-50 transition-colors group"
                  >
                    <!-- Avatar -->
                    <div class="relative shrink-0">
                      <img v-if="u.avatar" :src="u.avatar" class="h-10 w-10 rounded-full object-cover" />
                      <div v-else
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-bold text-sm group-hover:bg-indigo-200 transition-colors"
                      >
                        {{ initials(u.name) }}
                      </div>
                    </div>

                    <!-- Info -->
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-medium text-slate-800 truncate">{{ u.name }}</p>
                      <p class="text-xs text-slate-400 truncate">{{ u.position ?? 'Staff' }}</p>
                    </div>

                    <!-- Arrow hint -->
                    <span class="shrink-0 text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity text-xs font-medium">
                      Message →
                    </span>
                  </button>
                </li>
              </ul>

              <!-- Empty state -->
              <div v-else class="flex flex-col items-center justify-center py-10 text-slate-400">
                <UserCircleIcon class="h-10 w-10 opacity-20 mb-2" />
                <p class="text-xs">{{ userSearchQ ? 'No users found' : 'No users available' }}</p>
              </div>
            </div>

            <!-- Footer hint -->
            <div class="px-5 py-3 border-t border-slate-50 bg-slate-50/60">
              <p class="text-[11px] text-slate-400">Click a person to open a direct message conversation.</p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AdminLayout>
</template>
