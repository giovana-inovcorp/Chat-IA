<script setup>
import { ref } from 'vue'
import { marked } from 'marked'
import MessageComment from '@/Components/Chat/MessageComment.vue'

const props = defineProps({
    message: Object,
    highlightTerm: { type: String, default: '' },
})

const emit = defineEmits(['save-suggestion', 'dismiss-suggestion'])

const showComments = ref(false)
const copied = ref(false)

async function copyContent() {
    try {
        await navigator.clipboard.writeText(props.message.content)
    } catch {
        const textarea = document.createElement('textarea')
        textarea.value = props.message.content
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
    }
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}

function highlightContent(text) {
    if (!props.highlightTerm) return text
    const escaped = props.highlightTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    return text.replace(
        new RegExp(escaped, 'gi'),
        match => `<mark class="bg-yellow-200 rounded px-0.5">${match}</mark>`
    )
}
</script>

<template>
    <div class="group relative message-bubble">
        <div
            class="flex"
            :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
        >
            <div
                class="max-w-[75%] rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap"
                :class="message.role === 'user'
                    ? 'bg-primary text-primary-foreground rounded-br-sm'
                    : 'bg-muted text-foreground rounded-bl-sm'"
            >
                <template v-if="props.message.role === 'user'">
                    <span v-html="highlightContent(props.message.content)" />
                </template>
                <div v-else class="prose prose-sm max-w-none" v-html="highlightContent(marked(props.message.content))" />
            </div>
        </div>

        <!-- Memórias usadas -->
        <div
            v-if="message.role === 'assistant' && (message.memoriesUsed?.length || message.memories?.length)"
            class="mt-1 text-xs text-muted-foreground pl-1"
        >
            🧠 Memórias usadas: {{ (message.memoriesUsed || message.memories).map(m => m.title).join(', ') }}
        </div>

        <!-- Sugestão de memória -->
        <div
            v-if="message.role === 'assistant' && message.memorySuggestion"
            class="mt-2 border border-border rounded-lg px-3 py-2.5 bg-muted/30 text-sm flex items-start justify-between gap-3"
        >
            <div class="flex-1 min-w-0">
                <p class="text-xs text-muted-foreground mb-0.5">Guardar na memória?</p>
                <p class="font-medium text-sm truncate">{{ message.memorySuggestion.title }}</p>
                <p class="text-xs text-muted-foreground mt-0.5 line-clamp-2">{{ message.memorySuggestion.content }}</p>
                <p v-if="message.memorySuggestion.suggestionError" class="text-xs text-destructive mt-1">{{ message.memorySuggestion.suggestionError }}</p>
            </div>
            <div class="flex flex-col gap-1 shrink-0">
                <button
                    @click="emit('save-suggestion', message.memorySuggestion)"
                    class="text-xs px-2.5 py-1 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    Guardar
                </button>
                <button
                    @click="emit('dismiss-suggestion')"
                    class="text-xs px-2.5 py-1 rounded-md border border-border hover:bg-muted transition-colors"
                >
                    Rejeitar
                </button>
            </div>
        </div>

        <!-- Actions bar -->
        <div
            class="flex items-center gap-1 mt-1 opacity-0 group-hover:opacity-100 transition-opacity"
            :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
        >
            <button class="p-1 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors text-xs" @click="copyContent" :title="copied ? 'Copiado!' : 'Copiar'">
                <span v-if="copied" class="text-xs">✓</span>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-.621-.504-1.125-1.125-1.125h-1.5a9.06 9.06 0 0 0-1.5-.124" /></svg>
            </button>
            <button v-if="message.id" class="p-1 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors text-xs" @click="showComments = !showComments" title="Comentários">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>
            </button>
            <button class="p-1 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors text-xs" title="Gosto">👍</button>
            <button class="p-1 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors text-xs" title="Não gosto">👎</button>
        </div>

        <MessageComment v-if="message.id && showComments" :message-id="message.id" />
    </div>
</template>