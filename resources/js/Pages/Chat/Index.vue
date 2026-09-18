<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue'
import ChatWindow from '@/Components/Chat/ChatWindow.vue'

const page = usePage()
const user = computed(() => page.props.auth?.user)

const isDark = ref(
    localStorage.getItem('theme') === 'dark' ||
    (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)
)

function toggleTheme() {
    isDark.value = !isDark.value
    document.documentElement.classList.toggle('dark', isDark.value)
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
}

const sidebarOpen = ref(true)
const models = ref([])
const apiConfigured = ref(true)
const conversations = ref([])
const activeConversation = ref(null)
const loadingConversations = ref(false)
const knowledgeCollections = ref([])
const searchTerm = ref('')

async function loadModels() {
    const res = await fetch('/api/models')
    const data = await res.json()
    if (res.status === 500 || !data.models || data.models.length === 0) {
        apiConfigured.value = false
    }
    models.value = data.models
}

async function loadConversations() {
    loadingConversations.value = true
    const res = await fetch('/api/conversations')
    conversations.value = await res.json()
    loadingConversations.value = false
}

async function createConversation() {
    const res = await fetch('/api/conversations', { method: 'POST' })
    const conversation = await res.json()
    conversations.value.unshift(conversation)
    activeConversation.value = conversation
}

async function selectConversation(conversation, term = '') {
    const res = await fetch(`/api/conversations/${conversation.id}`)
    activeConversation.value = await res.json()
    searchTerm.value = term
}

async function deleteConversation(conversation) {
    await fetch(`/api/conversations/${conversation.id}`, { method: 'DELETE' })
    conversations.value = conversations.value.filter(c => c.id !== conversation.id)
    if (activeConversation.value?.id === conversation.id) {
        activeConversation.value = null
    }
}

function onConversationUpdated(updatedConversation) {
    const index = conversations.value.findIndex(c => c.id === updatedConversation.id)
    if (index !== -1) {
        conversations.value[index].title = updatedConversation.title
        // Move para o topo
        const [item] = conversations.value.splice(index, 1)
        conversations.value.unshift(item)
    }
}

function onConversationPinned(updatedConversation) {
    const index = conversations.value.findIndex(c => c.id === updatedConversation.id)
    if (index !== -1) {
        conversations.value[index].is_pinned = updatedConversation.is_pinned
    }
}

function onConversationCreated(conversation) {
    conversations.value.unshift(conversation)
    activeConversation.value = conversation
}

async function loadKnowledgeCollections() {
    const res = await fetch('/api/knowledge')
    knowledgeCollections.value = await res.json()
}

onMounted(() => {
    loadModels()
    loadConversations()
    loadKnowledgeCollections()
})
</script>

<template>
    <Head title="Chat IA" />

    <div class="flex h-screen bg-background text-foreground overflow-hidden relative">
        <button
            type="button"
            @click="toggleTheme"
            class="absolute top-3 right-3 z-50 p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
            :title="isDark ? 'Modo claro' : 'Modo escuro'"
        >
            <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
        </button>

        <ChatSidebar
            :conversations="conversations"
            :active-conversation="activeConversation"
            :loading="loadingConversations"
            :user="user"
            :open="sidebarOpen"
            @select="selectConversation"
            @create="createConversation"
            @delete="deleteConversation"
            @pin="onConversationPinned"
            @toggle="sidebarOpen = !sidebarOpen"
            @home="activeConversation = null"
        />

        <ChatWindow
            :conversation="activeConversation"
            :model-list="models"
            :api-configured="apiConfigured"
            :search-term="searchTerm"
            :collections="knowledgeCollections"
            @conversation-updated="onConversationUpdated"
            @create-conversation="createConversation"
            @conversation-created="onConversationCreated"
        />
    </div>
</template>