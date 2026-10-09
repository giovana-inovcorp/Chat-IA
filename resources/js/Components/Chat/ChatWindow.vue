<script setup>
import { ref, watch, nextTick, computed } from 'vue'
import MessageBubble from '@/Components/Chat/MessageBubble.vue'
import MessageInput from '@/Components/Chat/MessageInput.vue'

const props = defineProps({
    conversation: Object,
    modelList: { type: Array, default: () => [] },
    apiConfigured: { type: Boolean, default: true },
    searchTerm: { type: String, default: '' },
    collections: { type: Array, default: () => [] },
})

const emit = defineEmits(['conversation-updated', 'create-conversation', 'conversation-created'])

const pendingPrompt = ref('')

const suggestions = [
    { icon: '📄', label: 'Resume um texto',     prompt: 'Resume o seguinte texto de forma clara e concisa:' },
    { icon: '💻', label: 'Explica este código', prompt: 'Explica o seguinte código passo a passo:' },
    { icon: '🌐', label: 'Traduz para inglês',  prompt: 'Traduz o seguinte texto para inglês:' },
    { icon: '📋', label: 'Cria um plano',       prompt: 'Cria um plano detalhado para:' },
]

const usageInfo = ref(null)

const warningLevel = computed(() => {
    if (!usageInfo.value) return null
    const { tokens_used, token_limit, cost_used, cost_limit } = usageInfo.value
    if ((token_limit && tokens_used >= token_limit) || (cost_limit && cost_used >= cost_limit)) return 'blocked'
    if ((token_limit && tokens_used >= token_limit * 0.8) || (cost_limit && cost_used >= cost_limit * 0.8)) return 'warning'
    return null
})

const messagesContainer = ref(null)
const messages = ref([])
const isStreaming = ref(false)
const references = ref([])
const pendingConfirmation = ref(null)
const toolStatuses = ref([])
const conversationTitle = ref('')
const showExportMenu = ref(false)
const showSharePanel = ref(false)
const shareUrl = ref('')
const shareVisibility = ref('public')
const shareLoading = ref(false)
const copyLabel = ref('Copiar')
const highlightTerm = ref('')

watch(() => props.searchTerm, (newTerm) => {
    highlightTerm.value = newTerm ?? ''
})

watch(() => props.conversation, async (newConversation, oldConversation) => {
    if (newConversation) {
        conversationTitle.value = newConversation.title
        if (newConversation.id !== oldConversation?.id && oldConversation !== null) {
            messages.value = [...(newConversation.messages || [])]
            shareUrl.value = ''
            showSharePanel.value = false
            pendingConfirmation.value = null
            if (props.searchTerm) {
                highlightTerm.value = props.searchTerm
                await nextTick()
                const bubbles = messagesContainer.value?.querySelectorAll('.message-bubble')
                if (bubbles) {
                    for (const el of bubbles) {
                        if (el.textContent.toLowerCase().includes(props.searchTerm.toLowerCase())) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' })
                            break
                        }
                    }
                }
            } else {
                highlightTerm.value = ''
                scrollToBottom()
            }
        }
    } else {
        messages.value = []
        conversationTitle.value = ''
        shareUrl.value = ''
        showSharePanel.value = false
        highlightTerm.value = ''
        pendingConfirmation.value = null
    }
}, { immediate: true, deep: true })

function exportConversation(format) {
    window.open(`/api/conversations/${props.conversation.id}/export?format=${format}`, '_blank')
    showExportMenu.value = false
}

async function shareConversation() {
    shareLoading.value = true
    const res = await fetch(`/api/conversations/${props.conversation.id}/share`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ visibility: shareVisibility.value }),
    })
    const data = await res.json()
    shareUrl.value = data.url
    shareLoading.value = false
}

async function revokeShare() {
    await fetch(`/api/conversations/${props.conversation.id}/share`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    })
    shareUrl.value = ''
    showSharePanel.value = false
}

async function copyShareUrl() {
    try {
        await navigator.clipboard.writeText(shareUrl.value)
    } catch {
        const textarea = document.createElement('textarea')
        textarea.value = shareUrl.value
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
    }
    copyLabel.value = 'Copiado!'
    setTimeout(() => { copyLabel.value = 'Copiar' }, 2000)
}

async function scrollToBottom() {
    await nextTick()
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
}

async function sendMessage({ content, model, temperature, maxTokens, file, collection_ids }) {
    let conversation = props.conversation

    if (!conversation) {
        const csrf = document.querySelector('meta[name="csrf-token"]').content
        const res = await fetch('/api/conversations', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
        })
        conversation = await res.json()
        emit('conversation-created', conversation)
    }

    const isFirstMessage = messages.value.length === 0
    references.value = []
    toolStatuses.value = []

    // Adiciona mensagem do utilizador
    messages.value.push({ role: 'user', content })
    await scrollToBottom()

    // Adiciona placeholder da resposta do assistente
    messages.value.push({ role: 'assistant', content: '' })
    const assistantIndex = messages.value.length - 1
    isStreaming.value = true

    try {
        let body, headers
        const csrf = document.querySelector('meta[name="csrf-token"]').content
        if (file) {
            const formData = new FormData()
            formData.append('content', content)
            formData.append('model', model)
            formData.append('temperature', temperature)
            formData.append('max_tokens', maxTokens)
            formData.append('file', file)
            if (collection_ids) collection_ids.forEach(id => formData.append('collection_ids[]', id))
            body = formData
            headers = { 'X-CSRF-TOKEN': csrf }
        } else {
            body = JSON.stringify({ content, model, temperature, max_tokens: maxTokens, collection_ids })
            headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }
        }

        const response = await fetch(`/api/conversations/${conversation.id}/messages`, {
            method: 'POST',
            headers,
            body,
        })

        if (!response.ok) {
            const json = await response.json()
            messages.value[assistantIndex] = {
                ...messages.value[assistantIndex],
                content: json.error ?? 'Erro ao contactar a API. Tenta novamente.',
            }
            return
        }

        await consumeStream(response, assistantIndex, { isFirstMessage, conversation })
    } catch (error) {
        messages.value[assistantIndex] = {
            ...messages.value[assistantIndex],
            content: 'Erro ao contactar a API. Tenta novamente.'
        }
        console.error(error)
    } finally {
        isStreaming.value = false
    }
}

async function consumeStream(response, assistantIndex, { isFirstMessage = false, conversation = null } = {}) {
    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''
    let streamDone = false

    while (!streamDone) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() // guarda linha incompleta para o próximo ciclo

        for (const line of lines) {
            const trimmed = line.trim()
            if (!trimmed.startsWith('data: ')) continue

            const data = trimmed.slice(6)

            if (data === '[DONE]') {
                isStreaming.value = false
                if (isFirstMessage && conversation) {
                    const updated = await fetch(`/api/conversations/${conversation.id}`)
                    const updatedConversation = await updated.json()
                    conversationTitle.value = updatedConversation.title
                    emit('conversation-updated', {
                        id: conversation.id,
                        title: updatedConversation.title,
                    })
                }
                streamDone = true
                continue
            }

            try {
                const parsed = JSON.parse(data)
                if (parsed.chunk) {
                    messages.value[assistantIndex] = {
                        ...messages.value[assistantIndex],
                        content: messages.value[assistantIndex].content + parsed.chunk
                    }
                    await scrollToBottom()
                }
                if (parsed.usage) {
                    usageInfo.value = parsed.usage
                }
                if (parsed.references) {
                    references.value = parsed.references
                }
                if (parsed.confirmation_required) {
                    pendingConfirmation.value = {
                        ...parsed.confirmation_required,
                        assistantIndex,
                    }
                }
                if (parsed.tool_status === 'a_executar') {
                    toolStatuses.value.push({ tool: parsed.tool, status: 'a_executar', result: null })
                } else if (parsed.tool_status === 'concluido' || parsed.tool_status === 'erro') {
                    for (let i = toolStatuses.value.length - 1; i >= 0; i--) {
                        if (toolStatuses.value[i].tool === parsed.tool && toolStatuses.value[i].status === 'a_executar') {
                            toolStatuses.value[i] = { tool: parsed.tool, status: parsed.tool_status, result: parsed.result }
                            break
                        }
                    }
                }
            } catch (e) {
                // ignora linhas malformadas
            }
        }
    }
}

function toolStatusLabel(status) {
    const labels = {
        a_executar: 'a executar…',
        concluido: 'concluído',
        erro: 'erro',
    }
    return labels[status] ?? status
}

function toolLabel(tool) {
    const labels = {
        calculator: 'Calculadora',
        search_users: 'Pesquisa de utilizadores',
        query_usage: 'Consulta de uso',
        create_note: 'Criar nota',
        search_knowledge: 'Pesquisa na base de conhecimento',
    }
    return labels[tool] ?? tool
}

async function confirmToolExecution() {
    if (!pendingConfirmation.value) return
    const { execution_id, assistantIndex } = pendingConfirmation.value
    const csrf = document.querySelector('meta[name="csrf-token"]').content
    pendingConfirmation.value = null
    isStreaming.value = true

    try {
        const response = await fetch(`/api/tool-executions/${execution_id}/confirm`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
        })

        if (!response.ok) {
            const json = await response.json().catch(() => ({}))
            messages.value[assistantIndex] = {
                ...messages.value[assistantIndex],
                content: json.error ?? 'Erro ao confirmar a ação. Tenta novamente.',
            }
            return
        }

        await consumeStream(response, assistantIndex)
    } catch (error) {
        console.error(error)
    } finally {
        isStreaming.value = false
    }
}

async function rejectToolExecution() {
    if (!pendingConfirmation.value) return
    const { execution_id, assistantIndex } = pendingConfirmation.value
    const csrf = document.querySelector('meta[name="csrf-token"]').content
    pendingConfirmation.value = null
    isStreaming.value = true

    try {
        const response = await fetch(`/api/tool-executions/${execution_id}/reject`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
        })

        if (!response.ok) {
            const json = await response.json().catch(() => ({}))
            messages.value[assistantIndex] = {
                ...messages.value[assistantIndex],
                content: json.error ?? 'Erro ao cancelar a ação. Tenta novamente.',
            }
            return
        }

        await consumeStream(response, assistantIndex)
    } catch (error) {
        console.error(error)
    } finally {
        isStreaming.value = false
    }
}

function excerpt(text, length = 160) {
    if (!text) return ''
    const clean = text.replace(/\s+/g, ' ').trim()
    return clean.length > length ? clean.slice(0, length) + '…' : clean
}
</script>

<template>
    <div class="flex flex-col flex-1 h-full overflow-hidden">

        <div v-if="!conversation" class="flex flex-col flex-1 items-center justify-center gap-6 px-6">
            <h1 class="text-2xl font-semibold text-foreground text-center">O que tens em mente?</h1>
            <div class="flex flex-wrap gap-2 justify-center">
                <button
                    v-for="s in suggestions"
                    :key="s.label"
                    class="text-sm px-4 py-2 rounded-full border border-border bg-background hover:bg-muted transition-colors cursor-pointer text-muted-foreground"
                    @click="pendingPrompt = s.prompt"
                >
                    {{ s.icon }} {{ s.label }}
                </button>
            </div>
            <MessageInput
                :disabled="false"
                :model-list="modelList"
                :api-configured="apiConfigured"
                :initial-content="pendingPrompt"
                :is-homepage="true"
                :collections="collections"
                @send="sendMessage"
            />
        </div>

        <template v-else>
            <div class="px-6 py-4 border-b border-border shrink-0">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold truncate">{{ conversationTitle }}</h2>
                    <div class="flex items-center gap-1 shrink-0">
                        <!-- Share button -->
                        <div class="relative">
                            <button
                                class="text-muted-foreground hover:text-foreground transition-colors p-1 rounded"
                                title="Partilhar conversa"
                                @click="showSharePanel = !showSharePanel; showExportMenu = false"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" /></svg>
                            </button>
                            <div
                                v-if="showSharePanel"
                                class="absolute right-0 top-full mt-1 w-72 bg-background border border-border rounded-lg shadow-lg z-10 p-4"
                                @mouseleave="showSharePanel = false"
                            >
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm font-medium">Partilhar conversa</p>
                                    <button class="text-muted-foreground hover:text-foreground text-xs" @click="showSharePanel = false">✕</button>
                                </div>
                                <div class="flex gap-4 mb-3">
                                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <input type="radio" v-model="shareVisibility" value="public" /> Público
                                    </label>
                                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <input type="radio" v-model="shareVisibility" value="restricted" /> Restrito
                                    </label>
                                </div>
                                <button
                                    class="w-full text-sm px-3 py-1.5 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50"
                                    :disabled="shareLoading"
                                    @click="shareConversation"
                                >
                                    {{ shareLoading ? 'A gerar...' : 'Gerar link' }}
                                </button>
                                <div v-if="shareUrl" class="mt-3 space-y-2">
                                    <div class="flex gap-1">
                                        <input
                                            :value="shareUrl"
                                            readonly
                                            class="flex-1 text-xs px-2 py-1.5 rounded border border-input bg-muted truncate"
                                        />
                                        <button
                                            class="text-xs px-2 py-1.5 rounded border border-input hover:bg-muted transition-colors shrink-0"
                                            @click="copyShareUrl"
                                        >
                                            {{ copyLabel }}
                                        </button>
                                    </div>
                                    <button
                                        class="w-full text-xs text-destructive hover:underline transition-colors"
                                        @click="revokeShare"
                                    >
                                        Revogar link
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Export button -->
                        <div class="relative">
                            <button
                                class="text-muted-foreground hover:text-foreground transition-colors p-1 rounded"
                                title="Exportar conversa"
                                @click="showExportMenu = !showExportMenu; showSharePanel = false"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            </button>
                            <div
                                v-if="showExportMenu"
                                class="absolute right-0 top-full mt-1 w-52 bg-background border border-border rounded-lg shadow-lg z-10 py-1"
                                @mouseleave="showExportMenu = false"
                            >
                                <button
                                    class="w-full text-left text-sm px-4 py-2 hover:bg-muted transition-colors"
                                    @click="exportConversation('md')"
                                >
                                    Exportar como Markdown (.md)
                                </button>
                                <button
                                    class="w-full text-left text-sm px-4 py-2 hover:bg-muted transition-colors"
                                    @click="exportConversation('pdf')"
                                >
                                    Exportar como PDF (.pdf)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                ref="messagesContainer"
                class="flex-1 overflow-y-auto px-6 py-4 space-y-4"
            >
                <div v-if="messages.length === 0" class="text-center text-muted-foreground text-sm mt-8">
                    Envia uma mensagem para começar.
                </div>

                <MessageBubble
                    v-for="(message, index) in messages"
                    :key="index"
                    :message="message"
                    :highlight-term="highlightTerm"
                />

            </div>

            <!-- Estados das ferramentas -->
            <div v-if="toolStatuses.length > 0" class="px-6 pt-2 space-y-1">
                <details v-for="(ts, i) in toolStatuses" :key="i" class="text-xs">
                    <summary
                        class="cursor-pointer inline-flex items-center gap-1.5 select-none"
                        :class="{
                            'text-muted-foreground': ts.status === 'a_executar',
                            'text-green-600': ts.status === 'concluido',
                            'text-destructive': ts.status === 'erro',
                        }"
                    >
                        <span
                            class="inline-block w-1.5 h-1.5 rounded-full"
                            :class="{
                                'bg-muted-foreground animate-pulse': ts.status === 'a_executar',
                                'bg-green-600': ts.status === 'concluido',
                                'bg-destructive': ts.status === 'erro',
                            }"
                        />
                        {{ toolLabel(ts.tool) }} — {{ toolStatusLabel(ts.status) }}
                    </summary>
                    <pre v-if="ts.result" class="mt-1 ml-3 p-2 rounded bg-muted text-[11px] overflow-x-auto">{{ JSON.stringify(ts.result, null, 2) }}</pre>
                </details>
            </div>

            <!-- Confirmação de ação -->
            <div v-if="pendingConfirmation" class="px-6 pb-3">
                <div class="border border-border rounded-lg p-4 bg-muted/50 space-y-3">
                    <p class="text-sm font-medium">
                        O assistente quer executar a ação <span class="font-semibold">{{ toolLabel(pendingConfirmation.tool) }}</span>. Confirmas?
                    </p>
                    <ul class="text-xs text-muted-foreground space-y-0.5">
                        <li v-for="(value, key) in pendingConfirmation.parameters" :key="key">
                            <span class="font-medium">{{ key }}:</span> {{ value }}
                        </li>
                    </ul>
                    <div class="flex gap-2">
                        <button
                            class="text-sm px-3 py-1.5 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50"
                            :disabled="isStreaming"
                            @click="confirmToolExecution"
                        >
                            Confirmar
                        </button>
                        <button
                            class="text-sm px-3 py-1.5 rounded-md border border-input hover:bg-muted transition-colors disabled:opacity-50"
                            :disabled="isStreaming"
                            @click="rejectToolExecution"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Fontes RAG -->
            <div v-if="references.length > 0" class="px-6 pb-3 text-xs text-muted-foreground">
                <p class="font-medium mb-1">Fontes utilizadas:</p>
                <ul class="space-y-1.5">
                    <li v-for="ref in references" :key="ref.id">
                        <div>
                            📄
                            <a :href="`/api/knowledge/documents/${ref.document_id}/download`" target="_blank" class="underline hover:text-foreground">{{ ref.document_name }}</a>
                            <span v-if="ref.page"> — pág. {{ ref.page }}</span>
                            (relevância: {{ (ref.similarity * 100).toFixed(0) }}%)
                        </div>
                        <p class="pl-5 italic text-muted-foreground/80">"{{ excerpt(ref.content) }}"</p>
                    </li>
                </ul>
            </div>

            <div v-if="isStreaming" class="px-6 pb-2 flex items-center gap-1 text-muted-foreground text-sm">
                A escrever<span class="animate-ping inline-block w-1 h-1 rounded-full bg-muted-foreground ml-1 mb-0.5" />
            </div>

            <MessageInput
                :disabled="isStreaming || !!pendingConfirmation"
                :model-list="modelList"
                :api-configured="apiConfigured"
                :usage-info="{ ...usageInfo, warningLevel: warningLevel }"
                :collections="collections"
                @send="sendMessage"
            />
        </template>
    </div>
</template>