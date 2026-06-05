import { ref, computed, nextTick } from 'vue'
import axios from 'axios'

export function useChat(authUser) {
  // ── State ──────────────────────────────────────────────────────────────────
  const conversations      = ref([])
  const activeConversation = ref(null)
  const messages           = ref([])
  const pagination         = ref(null)
  const userSearchResults  = ref([])
  const showArchived       = ref(false)

  const loadingConversations = ref(false)
  const loadingMessages      = ref(false)
  const sendingMessage       = ref(false)
  const searchingUsers       = ref(false)

  const messageInput    = ref('')
  const attachmentFile  = ref(null)
  const attachmentDataUri = ref(null)  // base64 data URI for the attachment
  const userSearchQ     = ref('')
  const messagesEl      = ref(null)

  // ── Computed ───────────────────────────────────────────────────────────────
  const totalUnread = computed(() =>
    conversations.value.reduce((sum, c) => sum + (c.unread_count ?? 0), 0)
  )

  const canSend = computed(() =>
    (messageInput.value.trim() || attachmentFile.value) && !sendingMessage.value
  )

  // ── Conversations ─────────────────────────────────────────────────────────
  async function fetchConversations(archived = false) {
    loadingConversations.value = true
    try {
      const { data } = await axios.get('/api/chat/conversations', {
        params: archived ? { archived: 1 } : {},
      })
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
    conversations.value.sort((a, b) =>
      new Date(b.updated_at) - new Date(a.updated_at)
    )
  }

  function removeConversation(convId) {
    conversations.value = conversations.value.filter(c => c.id !== convId)
    if (activeConversation.value?.id === convId) {
      activeConversation.value = null
      messages.value = []
    }
  }

  // ── Open a conversation ───────────────────────────────────────────────────
  async function openConversation(conv) {
    if (activeConversation.value?.id === conv.id) return
    activeConversation.value = conv
    messages.value = []
    pagination.value = null
    await fetchMessages(conv.id)
    await markRead(conv.id)
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
        messages.value = [...data.messages].reverse()
      } else {
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
    await nextTick()
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight - prevScrollHeight
    }
  }

  // ── Send (base64 JSON — avoids Cloudflare WAF multipart block) ────────────
  async function sendMessage() {
    if (!canSend.value || !activeConversation.value) return

    sendingMessage.value = true

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
      is_deleted:      false,
      read_at:         null,
      seen_by_count:   0,
      created_at:      new Date().toISOString(),
      _pending:        true,
    }

    messages.value.push(optimistic)
    const body             = messageInput.value.trim()
    const dataUri          = attachmentDataUri.value
    const attachName       = attachmentFile.value?.name ?? null
    messageInput.value     = ''
    attachmentFile.value   = null
    attachmentDataUri.value = null
    await scrollToBottom()

    try {
      const payload = { body }
      if (dataUri) {
        payload.attachment_base64 = dataUri
        payload.attachment_name   = attachName
      }

      const { data } = await axios.post(
        `/api/chat/conversations/${activeConversation.value.id}/messages`,
        payload
      )

      const idx = messages.value.findIndex(m => m.id === optimisticId)
      if (idx !== -1) messages.value[idx] = data.message

      upsertConversation({
        id:             activeConversation.value.id,
        latest_message: { body: data.message.body, sender_id: authUser.id, created_at: data.message.created_at },
        updated_at:     data.message.created_at,
      })
    } catch (e) {
      const idx = messages.value.findIndex(m => m.id === optimisticId)
      if (idx !== -1) messages.value[idx] = { ...messages.value[idx], _failed: true, _pending: false }
      console.error('Send failed', e)
    } finally {
      sendingMessage.value = false
    }
  }

  // ── Delete message ────────────────────────────────────────────────────────
  async function deleteMessage(convId, msgId) {
    try {
      await axios.delete(`/api/chat/conversations/${convId}/messages/${msgId}`)
      const idx = messages.value.findIndex(m => m.id === msgId)
      if (idx !== -1) {
        messages.value[idx] = { ...messages.value[idx], is_deleted: true, body: null, attachment_path: null }
      }
    } catch (e) {
      console.error('Delete failed', e)
    }
  }

  // ── Archive / unarchive ───────────────────────────────────────────────────
  async function archiveConversation(convId) {
    try {
      const { data } = await axios.post(`/api/chat/conversations/${convId}/archive`)
      // Remove from current list (it moved to/from archived)
      removeConversation(convId)
      return data.archived
    } catch (e) {
      console.error('Archive failed', e)
    }
  }

  // ── Mark read ─────────────────────────────────────────────────────────────
  async function markRead(convId) {
    try {
      await axios.post(`/api/chat/conversations/${convId}/read`)
    } catch (_) {}
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

  // ── Start group ───────────────────────────────────────────────────────────
  async function startGroup(name, participantIds) {
    try {
      const { data } = await axios.post('/api/chat/conversations', {
        type:            'group',
        name,
        participant_ids: participantIds,
      })
      upsertConversation(data.conversation)
      await openConversation(data.conversation)
      userSearchResults.value = []
      userSearchQ.value = ''
      return data.conversation
    } catch (e) {
      console.error('Start group failed', e)
      throw e
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

  // ── File → base64 ─────────────────────────────────────────────────────────
  function readFileAsDataUri(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload  = e => resolve(e.target.result)
      reader.onerror = reject
      reader.readAsDataURL(file)
    })
  }

  async function onFileSelected(file) {
    if (!file) { attachmentFile.value = null; attachmentDataUri.value = null; return }
    attachmentFile.value = file
    try {
      attachmentDataUri.value = await readFileAsDataUri(file)
    } catch (e) {
      attachmentFile.value = null
      attachmentDataUri.value = null
      console.error('File read failed', e)
    }
  }

  // ── Real-time injection ───────────────────────────────────────────────────
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
    if (activeConversation.value?.type !== 'direct') return
    messages.value.forEach(m => {
      if (message_ids.includes(m.id)) m.read_at = read_at
    })
  }

  function applyMessageDeleted({ message_id }) {
    const idx = messages.value.findIndex(m => m.id === message_id)
    if (idx !== -1) {
      messages.value[idx] = {
        ...messages.value[idx],
        is_deleted:      true,
        body:            null,
        attachment_path: null,
      }
    }
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
    const d    = new Date(iso)
    const now  = new Date()
    const diff = Math.floor((now - d) / 86400000)
    if (diff === 0) return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
    if (diff === 1) return 'Yesterday'
    if (diff < 7)   return d.toLocaleDateString('en-PH', { weekday: 'short' })
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
  }

  function initials(name) {
    return (name ?? '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
  }

  return {
    // state
    conversations, activeConversation, messages, pagination,
    userSearchResults, userSearchQ, showArchived,
    loadingConversations, loadingMessages, sendingMessage, searchingUsers,
    messageInput, attachmentFile, attachmentDataUri, messagesEl,
    // computed
    totalUnread, canSend,
    // methods
    fetchConversations, openConversation, fetchMessages, loadMoreMessages,
    sendMessage, deleteMessage, archiveConversation,
    markRead, startDM, startGroup, loadUsers, searchUsers,
    onFileSelected,
    injectIncomingMessage, applyReadReceipt, applyMessageDeleted,
    upsertConversation, removeConversation,
    // helpers
    formatTime, initials, scrollToBottom,
  }
}
