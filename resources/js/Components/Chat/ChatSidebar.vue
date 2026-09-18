<script setup>
import { ref, computed, watch } from 'vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
    conversations: Array,
    activeConversation: Object,
    loading: Boolean,
    user: Object,
    open: { type: Boolean, default: true },
})

const emit = defineEmits(['select', 'create', 'delete', 'pin', 'toggle', 'home'])

const search = ref('')
const confirmDeleteId = ref(null)
const searchResults = ref([])
const isSearching = ref(false)

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    if (val.length < 2) {
        searchResults.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        isSearching.value = true
        const res = await fetch(`/api/conversations/search?q=${encodeURIComponent(val)}`)
        searchResults.value = await res.json()
        isSearching.value = false
    }, 300)
})

const pinnedConversations = computed(() => props.conversations.filter(c => c.is_pinned))
const unpinnedConversations = computed(() => props.conversations.filter(c => !c.is_pinned))

async function togglePin(conversation) {
    const res = await fetch(`/api/conversations/${conversation.id}/pin`, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    const data = await res.json()
    if (res.status === 422) {
        alert(data.message)
        return
    }
    emit('pin', data)
}

function formatDate(dateStr) {
    const date = new Date(dateStr)
    return date.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' })
}

function selectSearchResult(result) {
    const term = search.value
    emit('select', result, term)
}

function logout() {
    const form = document.createElement('form')
    form.method = 'POST'
    form.action = '/logout'
    const csrf = document.createElement('input')
    csrf.type = 'hidden'
    csrf.name = '_token'
    csrf.value = document.querySelector('meta[name="csrf-token"]').content
    form.appendChild(csrf)
    document.body.appendChild(form)
    form.submit()
}
</script>

<template>
    <aside
        :class="['flex flex-col border-r border-border bg-muted/30 h-full shrink-0 transition-all duration-200 overflow-hidden', open ? 'w-64' : 'w-12']"
    >
        <!-- Collapsed: only toggle button -->
        <div v-if="!open" class="flex flex-col items-center pt-3">
            <button
                class="p-2 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                @click="emit('toggle')"
                title="Expandir sidebar"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" /></svg>
            </button>
        </div>

        <!-- Expanded: full content -->
        <template v-else>
            <!-- Header -->
            <div class="p-4 border-b border-border">
                <div class="flex items-center justify-between mb-3">
                    <button class="text-lg font-semibold hover:opacity-70 transition-opacity" @click="emit('home')">Chat IA</button>
                    <button
                        class="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                        @click="emit('toggle')"
                        title="Recolher sidebar"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" /></svg>
                    </button>
                </div>
                <Button class="w-full" @click="emit('create')">
                    + Nova conversa
                </Button>
            </div>

            <!-- Pesquisa -->
            <div class="p-3 border-b border-border">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Pesquisar conversas..."
                    class="w-full text-sm px-3 py-2 rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                />
            </div>

            <!-- Lista de conversas -->
            <div class="flex-1 overflow-y-auto">
                <div v-if="loading" class="p-4 text-sm text-muted-foreground text-center">
                    A carregar...
                </div>

                <!-- Resultados de pesquisa -->
                <template v-else-if="search.length >= 2">
                    <div v-if="isSearching" class="p-4 text-sm text-muted-foreground text-center">
                        A pesquisar...
                    </div>
                    <div v-else-if="searchResults.length === 0" class="p-4 text-sm text-muted-foreground text-center">
                        Nenhuma conversa encontrada para este termo.
                    </div>
                    <div
                        v-else
                        v-for="result in searchResults"
                        :key="result.id"
                        class="px-3 py-3 cursor-pointer hover:bg-muted transition-colors border-b border-border/50"
                        :class="{ 'bg-muted': activeConversation?.id === result.id }"
                        @click="selectSearchResult(result)"
                    >
                        <p class="text-sm font-medium truncate">{{ result.title }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatDate(result.updated_at) }}</p>
                        <p v-if="result.snippet" class="text-xs text-muted-foreground italic mt-0.5 truncate">{{ result.snippet }}</p>
                    </div>
                </template>

                <!-- Lista normal -->
                <template v-else>
                    <div v-if="props.conversations.length === 0" class="p-4 text-sm text-muted-foreground text-center">
                        Nenhuma conversa encontrada.
                    </div>
                    <!-- Fixadas -->
                    <template v-if="pinnedConversations.length > 0">
                        <p class="px-3 pt-3 pb-1 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Fixadas</p>
                        <div
                            v-for="conversation in pinnedConversations"
                            :key="conversation.id"
                            class="relative group flex items-center justify-between px-3 py-3 cursor-pointer hover:bg-muted transition-colors border-b border-border/50"
                            :class="{ 'bg-muted': activeConversation?.id === conversation.id }"
                            @click="emit('select', conversation)"
                        >
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate">{{ conversation.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatDate(conversation.updated_at) }}</p>
                            </div>
                            <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 ml-2 shrink-0" @click.stop>
                                <button class="text-muted-foreground hover:text-foreground transition-all" @click.stop="togglePin(conversation)" title="Desafixar conversa">📌</button>
                                <button class="text-muted-foreground hover:text-destructive transition-all" @click.stop="confirmDeleteId = conversation.id" title="Apagar conversa">✕</button>
                            </div>
                            <div v-if="confirmDeleteId === conversation.id" class="absolute right-0 top-0 h-full flex items-center gap-1 pr-2 bg-muted" @click.stop>
                                <span class="text-xs text-foreground">Tens a certeza?</span>
                                <button class="text-xs text-destructive font-semibold hover:underline" @click.stop="emit('delete', conversation); confirmDeleteId = null">Sim</button>
                                <button class="text-xs text-muted-foreground hover:underline" @click.stop="confirmDeleteId = null">Não</button>
                            </div>
                        </div>
                    </template>

                    <!-- Restantes -->
                    <div
                        v-for="conversation in unpinnedConversations"
                        :key="conversation.id"
                        class="relative group flex items-center justify-between px-3 py-3 cursor-pointer hover:bg-muted transition-colors border-b border-border/50"
                        :class="{ 'bg-muted': activeConversation?.id === conversation.id }"
                        @click="emit('select', conversation)"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ conversation.title }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatDate(conversation.updated_at) }}</p>
                        </div>
                        <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 ml-2 shrink-0" @click.stop>
                            <button class="text-muted-foreground hover:text-foreground transition-all" @click.stop="togglePin(conversation)" title="Fixar conversa">📌</button>
                            <button class="text-muted-foreground hover:text-destructive transition-all" @click.stop="confirmDeleteId = conversation.id" title="Apagar conversa">✕</button>
                        </div>
                        <div v-if="confirmDeleteId === conversation.id" class="absolute right-0 top-0 h-full flex items-center gap-1 pr-2 bg-muted" @click.stop>
                            <span class="text-xs text-foreground">Tens a certeza?</span>
                            <button class="text-xs text-destructive font-semibold hover:underline" @click.stop="emit('delete', conversation); confirmDeleteId = null">Sim</button>
                            <button class="text-xs text-muted-foreground hover:underline" @click.stop="confirmDeleteId = null">Não</button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Admin menu -->
            <div v-if="user?.is_admin" class="border-t border-border">
                <p class="text-[10px] uppercase tracking-wide text-muted-foreground px-3 pt-2">Administração</p>
                <a href="/admin" class="block text-xs px-3 py-2 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">🗂️ Logs de Auditoria</a>
                <a href="/admin/usage-limits" class="block text-xs px-3 py-2 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">🔒 Limites de Uso</a>
            </div>

            <!-- Knowledge base link -->
            <div class="border-t border-border">
                <a href="/knowledge" class="block text-xs px-3 py-2 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">📚 Base de Conhecimento</a>
            </div>

            <!-- User menu -->
            <div class="p-3 border-t border-border flex items-center justify-between gap-2">
                <span class="text-sm truncate text-foreground">{{ user?.name }}</span>
                <button
                    class="text-xs text-muted-foreground hover:text-destructive transition-colors shrink-0"
                    @click="logout"
                >
                    Sair
                </button>
            </div>
        </template>
    </aside>
</template>