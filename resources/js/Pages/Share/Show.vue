<script setup>
import { ref, onMounted, computed } from 'vue'
import { marked } from 'marked'

const conversation = ref(null)
const error = ref(null)
const loading = ref(true)

const firstAssistantModel = computed(() =>
    conversation.value?.messages?.find(m => m.role === 'assistant')?.model ?? 'N/A'
)

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('pt-PT', { day: '2-digit', month: 'long', year: 'numeric' })
}

onMounted(async () => {
    const token = window.location.pathname.split('/').filter(Boolean).pop()
    try {
        const res = await fetch(`/share/${token}`, {
            headers: { 'Accept': 'application/json' },
        })
        if (res.status === 403) {
            error.value = 'Esta conversa é privada. Precisas de iniciar sessão para a ver.'
            return
        }
        if (res.status === 404) {
            error.value = 'Este link de partilha não existe ou foi revogado.'
            return
        }
        conversation.value = await res.json()
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="min-h-screen bg-background text-foreground flex flex-col">

        <!-- Header -->
        <header class="border-b border-border px-6 py-4 flex items-center gap-3 shrink-0">
            <span class="font-semibold text-lg">Chat IA</span>
        </header>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-1 items-center justify-center text-muted-foreground text-sm">
            A carregar...
        </div>

        <!-- Error -->
        <div v-else-if="error" class="flex flex-1 items-center justify-center">
            <p class="text-muted-foreground text-sm text-center max-w-sm">{{ error }}</p>
        </div>

        <!-- Conversation -->
        <template v-else>
            <div class="max-w-2xl w-full mx-auto px-4 py-8 flex-1 flex flex-col">

                <!-- Conversation header -->
                <div class="mb-6">
                    <h1 class="text-xl font-semibold mb-1">{{ conversation.title }}</h1>
                    <p class="text-xs text-muted-foreground">{{ formatDate(conversation.created_at) }} · {{ firstAssistantModel }}</p>
                </div>

                <!-- Messages -->
                <div class="flex flex-col gap-4">
                    <div
                        v-for="(message, index) in conversation.messages"
                        :key="index"
                        class="flex"
                        :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[80%] rounded-2xl px-4 py-3 text-sm"
                            :class="message.role === 'user'
                                ? 'bg-primary text-primary-foreground rounded-br-sm'
                                : 'bg-muted text-foreground rounded-bl-sm'"
                        >
                            <div
                                v-if="message.role === 'assistant'"
                                class="prose prose-sm max-w-none"
                                v-html="marked(message.content)"
                            />
                            <p v-else class="whitespace-pre-wrap">{{ message.content }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="border-t border-border px-6 py-4 text-center text-xs text-muted-foreground">
                Conversa partilhada via Chat IA
            </footer>
        </template>

    </div>
</template>
