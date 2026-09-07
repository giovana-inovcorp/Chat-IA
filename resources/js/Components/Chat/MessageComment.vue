<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
    messageId: Number,
})

const comments = ref([])
const showPanel = ref(false)
const body = ref('')
const editingComment = ref(null)

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content
}

function formatDate(dateStr) {
    const d = new Date(dateStr)
    const date = d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric' })
    const time = d.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })
    return `${date} ${time}`
}

async function loadComments() {
    const res = await fetch(`/api/messages/${props.messageId}/comments`)
    comments.value = await res.json()
}

async function store() {
    if (!body.value.trim()) return
    const res = await fetch(`/api/messages/${props.messageId}/comments`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ body: body.value.trim() }),
    })
    if (!res.ok) return
    body.value = ''
    await loadComments()
}

async function update(comment) {
    if (!body.value.trim()) return
    const res = await fetch(`/api/messages/${props.messageId}/comments/${comment.id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ body: body.value.trim() }),
    })
    if (!res.ok) return
    editingComment.value = null
    body.value = ''
    await loadComments()
}

async function destroy(comment) {
    if (!confirm('Apagar este comentário?')) return
    await fetch(`/api/messages/comments/${comment.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
    await loadComments()
}

function startEdit(comment) {
    editingComment.value = comment
    body.value = comment.body
}

function cancelEdit() {
    editingComment.value = null
    body.value = ''
}

onMounted(loadComments)
</script>

<template>
    <div class="mt-1">
        <!-- Toggle button -->
        <button
            class="relative text-muted-foreground hover:text-foreground transition-colors text-sm"
            @click="showPanel = !showPanel"
        >
            💬
            <span
                v-if="comments.length > 0"
                class="absolute -top-1.5 -right-2 bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none"
            >
                {{ comments.length }}
            </span>
        </button>

        <!-- Panel -->
        <div v-if="showPanel" class="bg-muted/50 rounded-lg p-3 mt-2 space-y-3">

            <!-- Existing comments -->
            <div v-if="comments.length === 0" class="text-xs text-muted-foreground">
                Sem comentários ainda.
            </div>
            <div
                v-for="comment in comments"
                :key="comment.id"
                class="flex items-start justify-between gap-2"
            >
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-foreground">{{ comment.body }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ formatDate(comment.created_at) }}</p>
                </div>
                <div class="flex gap-1 shrink-0">
                    <button class="text-xs text-muted-foreground hover:text-foreground" @click="startEdit(comment)">✏️</button>
                    <button class="text-xs text-muted-foreground hover:text-destructive" @click="destroy(comment)">🗑️</button>
                </div>
            </div>

            <!-- Form -->
            <div class="space-y-2">
                <textarea
                    v-model="body"
                    :placeholder="editingComment ? 'Editar comentário...' : 'Adicionar comentário...'"
                    maxlength="500"
                    rows="2"
                    class="w-full text-sm px-3 py-2 rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                />
                <div class="flex gap-2">
                    <button
                        class="text-xs px-3 py-1.5 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
                        @click="editingComment ? update(editingComment) : store()"
                    >
                        {{ editingComment ? 'Actualizar' : 'Guardar' }}
                    </button>
                    <button
                        v-if="editingComment"
                        class="text-xs px-3 py-1.5 rounded-md border border-input text-muted-foreground hover:text-foreground transition-colors"
                        @click="cancelEdit"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
