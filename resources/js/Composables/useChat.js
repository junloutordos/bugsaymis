import { ref, computed, nextTick } from 'vue'
import axios from 'axios'

export function useChat(authUser) {
  // ── State ──────────────────────────────────────────────────────────────────
  const conversations     = ref([])
  const activeConversation = ref(null)
  const messages          = ref([])
  const pagination        = ref(null)
  const userSearchResults = ref([])

  const loadingConversations = ref(false)
  const loadingMessages      = ref(false)
  const sendingMessage       = ref(false)
  const searchingUsers       = ref(false)

  const messageInput   = ref('')
  const attachmentFile = ref(null)
  const userSearchQ    = ref('')
  const messagesEl     = ref(null)   // template ref for the scroll container

  // ── Computed ───────────────────────────────────────────────────────────────
  const totalUnread = computed(() =>
    conversations.value.reduce((sum, c) => sum + (c.unread_count ?? 0), 0)
  )

  const canSend = computed(() =>
    (messageInput.value.trim() || attachmentFile.value) && !sendingMessage.value
  )

  // ── Conversations ─────────────────────────────────────────────────────────
  async function fetchConversations() {
    loadingConversations.value = true
    try {
      const { data } = await axios.get('/api/chat/conversations')
      conversations.value = data.conversations
    } finally {
      loadingConversations.value = false
    }
  }

  function upsertConversation(updated) {
    const idx = conversations.value.findIndex(c => c.id === updated.id)
    if (idx !== -1) {
      conversations.value[idx] = { ...conversations.value[idx], ...updated }
    } else {
      conversations.value.unshift(updated)
    }
    // Re-sort by latest activity
    conversations.value.sort((a, b) =>
      new Date(b.updated_at) - new Date(a.updated_at)
    )
  }

  // ── Open a conversation ───────────────────────────────────────────────────
  async function openConversation(conv) {
    if (activeConversation.value?.id === conv.id) return

    activeConversation.value = conv
    messages.value = []
    pagination.value = null
    await fetchMessages(conv.id)
    await markRead(conv.id)

    // Clear unread badge locally
    const c = conversations.value.find(c => c.id === conv.id)
    if (c) c.unread_count = 0
  }

  // ── Messages ──────────────────────────────────────────────────────────────
  async function fetchMessages(convId, page = 1) {
    loadingMessages.value = true
    try {
      const { data } = await axios.get(
        `/api/chat/conversations/${convId}/messages`,
        { params: { page, per_page: 30 } }
      )

      if (page === 1) {
        // Reverse so oldest-first for display
        messages.value = [...data.messages].reverse()
      } else {
        // Prepend older messages (load-more)
        messages.value = [...[...data.messages].reverse(), ...messages.value]
      }

      pagination.value = data.pagination
      if (page === 1) await scrollToBottom()
    } finally {
      loadingMessages.value = false
    }
  }

  async function loadMoreMessages() {
    if (!activeConversation.value || !pagination.value) return
    if (pagination.value.current_page >= pagination.value.last_page) return

    const prevScrollHeight = messagesEl.value?.scrollHeight ?? 0
    await fetchMessages(activeConversation.value.id, pagination.value.current_page + 1)

    // Maintain scroll position after prepending
    await nextTick()
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight - prevScrollHeight
    }
  }

  // ── Send ──────────────────────────────────────────────────────────────────
  async function sendMessage() {
    if (!canSend.value || !activeConversation.value) return

    sendingMessage.value = true
    const formData = new FormData()
    if (messageInput.value.trim()) formData.append('body', messageInput.value.trim())
    if (attachmentFile.value)      formData.append('attachment', attachmentFile.value)

    const optimisticId = `tmp-${Date.now()}`
    const optimistic = {
      id:              optimisticId,
      conversation_id: activeConversation.value.id,
      sender_id:       authUser.id,
      sender_name:     authUser.name,
      sender_avatar:   authUser.avatar ?? null,
      body:            messageInput.value.trim(),
      attachment_path: null,
      attachment_type: null,
      read_at:         null,
      created_at:      new Date().toISOString(),
      _pending:        true,
    }

    messages.value.push(optimistic)
    messageInput.value  = ''
    attachmentFile.value = null
    await scrollToBottom()

    try {
      const { data } = await axios.post(
        `/api/chat/conversations/${activeConversation.value.id}/messages`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      )

      // Replace optimistic with confirmed message
      const idx = messages.value.findIndex(m => m.id === optimisticId)
      if (idx !== -1) messages.value[idx] = data.message

      // Update conversation preview
      upsertConversation({
        id:             activeConversation.value.id,
        latest_message: { body: data.message.body, sender_id: authUser.id, created_at: data.message.created_at },
        updated_at:     data.message.created_at,
      })
    } catch (e) {
      // Mark optimistic as failed
      const idx = messages.value.findIndex(m => m.id === optimisticId)
      if (idx !== -1) messages.value[idx]._failed = true
      console.error('Send failed', e)
    } finally {
      sendingMessage.value = false
    }
  }

  // ── Mark read ─────────────────────────────────────────────────────────────
  async function markRead(convId) {
    try {
      await axios.post(`/api/chat/conversations/${convId}/read`)
    } catch (e) {
      // Non-critical — silently ignore
    }
  }

  // ── Start new DM ──────────────────────────────────────────────────────────
  async function startDM(userId) {
    try {
      const { data } = await axios.post('/api/chat/conversations', {
        type:    'direct',
        user_id: userId,
      })
      upsertConversation(data.conversation)
      await openConversation(data.conversation)
      userSearchResults.value = []
      userSearchQ.value = ''
    } catch (e) {
      console.error('Start DM failed', e)
    }
  }

  // ── User search ───────────────────────────────────────────────────────────
  let userSearchTimer = null

  async function loadUsers() {
    searchingUsers.value = true
    try {
      const { data } = await axios.get('/api/chat/users')
      userSearchResults.value = data.users
    } finally {
      searchingUsers.value = false
    }
  }

  function searchUsers(q) {
    userSearchQ.value = q
    clearTimeout(userSearchTimer)
    if (!q.trim()) {
      // Show all users when search is cleared
      loadUsers()
      return
    }
    userSearchTimer = setTimeout(async () => {
      searchingUsers.value = true
      try {
        const { data } = await axios.get('/api/chat/users', { params: { search: q } })
        userSearchResults.value = data.users
      } finally {
        searchingUsers.value = false
      }
    }, 300)
  }

  // ── Real-time injection (called by Phase 7 Echo listeners) ───────────────
  function injectIncomingMessage(msg) {
    if (activeConversation.value?.id === msg.conversation_id) {
      messages.value.push(msg)
      scrollToBottom()
      markRead(msg.conversation_id)
    } else {
      const c = conversations.value.find(c => c.id === msg.conversation_id)
      if (c) c.unread_count = (c.unread_count ?? 0) + 1
    }
    upsertConversation({
      id:             msg.conversation_id,
      latest_message: { body: msg.body, sender_id: msg.sender_id, created_at: msg.created_at },
      updated_at:     msg.created_at,
    })
  }

  function applyReadReceipt({ message_ids, read_at }) {
    // read_at on individual messages only applies to DMs — group chats
    // use conversation_user.last_read_at on the pivot, not per-message read_at
    if (activeConversation.value?.type !== 'direct') return
    messages.value.forEach(m => {
      if (message_ids.includes(m.id)) m.read_at = read_at
    })
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  async function scrollToBottom() {
    await nextTick()
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight
    }
  }

  function formatTime(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    const now = new Date()
    const diffDays = Math.floor((now - d) / 86400000)
    if (diffDays === 0) return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
    if (diffDays === 1) return 'Yesterday'
    if (diffDays < 7)   return d.toLocaleDateString('en-PH', { weekday: 'short' })
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
  }

  function initials(name) {
    return (name ?? '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
  }

  return {
    // state
    conversations, activeConversation, messages, pagination,
    userSearchResults, userSearchQ,
    loadingConversations, loadingMessages, sendingMessage, searchingUsers,
    messageInput, attachmentFile, messagesEl,
    // computed
    totalUnread, canSend,
    // methods
    fetchConversations, openConversation, fetchMessages, loadMoreMessages,
    sendMessage, markRead, startDM, loadUsers, searchUsers,
    injectIncomingMessage, applyReadReceipt,
    upsertConversation,
    // helpers
    formatTime, initials, scrollToBottom,
  }
}
