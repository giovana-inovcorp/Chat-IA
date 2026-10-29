<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'

const csrf = () => document.querySelector('meta[name="csrf-token"]').content
const page = usePage()

const memories = ref([])
const loading = ref(true)
const memoryEnabled = ref(page.props.auth?.user?.memory_enabled ?? true)
const savingGlobal = ref(false)
const errorMessage = ref('')

const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const defaultForm = () => ({ title: '', content: '', category: '' })
const form = ref(defaultForm())

async function fetchMemories() {
    loading.value = true
    const res = await fetch('/api/memories', {
        headers: { 'Accept': 'application/json' },
    })
    memories.value = await res.json()
    loading.value = false
}

async function toggleGlobal() {
    savingGlobal.value = true
    await fetch('/api/memory-settings', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ enabled: memoryEnabled.value }),
    })
    savingGlobal.value = false
}

function openCreate() {
    editingId.value = null
    form.value = defaultForm()
    errorMessage.value = ''
    showForm.value = true
}

function openEdit(memory) {
    editingId.value = memory.id
    form.value = { title: memory.title, content: memory.content, category: memory.category ?? '' }
    errorMessage.value = ''
    showForm.value = true
}

function cancelForm() {
    showForm.value = false
    editingId.value = null
    errorMessage.value = ''
}

async function saveForm() {
    if (!form.value.title.trim() || !form.value.content.trim()) return
    saving.value = true
    errorMessage.value = ''
    const url    = editingId.value ? `/api/memories/${editingId.value}` : '/api/memories'
    const method = editingId.value ? 'PUT' : 'POST'
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
        body: JSON.stringify(form.value),
    })
    const data = await res.json()
    if (!res.ok) {
        errorMessage.value = data.message ?? 'Erro ao guardar.'
        saving.value = false
        return
    }
    if (editingId.value) {
        const idx = memories.value.findIndex(m => m.id === editingId.value)
        if (idx !== -1) memories.value.splice(idx, 1, data)
    } else {
        memories.value.unshift(data)
    }
    saving.value = false
    cancelForm()
}

async function toggleMemory(memory) {
    errorMessage.value = ''
    const res = await fetch(`/api/memories/${memory.id}/toggle`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (!res.ok) {
        errorMessage.value = data.message ?? 'Erro ao alternar estado.'
        return
    }
    const idx = memories.value.findIndex(m => m.id === memory.id)
    if (idx !== -1) memories.value.splice(idx, 1, data)
}

async function deleteMemory(memory) {
    if (!confirm(`Apagar "${memory.title}"? Esta ação não pode ser revertida.`)) return
    await fetch(`/api/memories/${memory.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
    })
    memories.value = memories.value.filter(m => m.id !== memory.id)
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const activeCount = computed(() => memories.value.filter(m => m.status === 'active').length)

onMounted(fetchMemories)
</script>

<template>
    <Head title="Memórias" />

    <div class="min-h-screen bg-background text-foreground">
        <div class="max-w-4xl mx-auto px-4 py-8">

            <a href="/dashboard" class="text-sm text-muted-foreground hover:text-foreground transition-colors">← Voltar ao chat</a>

            <div class="mt-4 mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Memórias</h1>
                    <p class="text-muted-foreground text-sm mt-1">Informação sobre ti que o assistente usa para personalizar respostas</p>
                </div>
                <button
                    @click="openCreate"
                    class="px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    + Nova memória
                </button>
            </div>

            <!-- Global switch -->
            <div class="border border-border rounded-lg p-4 mb-6 bg-muted/20 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium">Memória ativa</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Quando desativada, nenhuma memória é injetada nas conversas</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="memoryEnabled"
                        @change="toggleGlobal"
                        :disabled="savingGlobal"
                        class="sr-only peer"
                    />
                    <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:bg-primary transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            <!-- Error banner -->
            <div v-if="errorMessage" class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center justify-between">
                <span>{{ errorMessage }}</span>
                <button @click="errorMessage = ''" class="ml-3 text-red-500 hover:text-red-700">✕</button>
            </div>

            <!-- Form -->
            <div v-if="showForm" class="border border-border rounded-lg p-5 mb-8 bg-muted/20">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground mb-4">
                    {{ editingId ? 'Editar memória' : 'Criar memória' }}
                </h2>
                <div class="flex flex-col gap-4">
                    <input
                        v-model="form.title"
                        maxlength="100"
                        placeholder="Título (ex: Linguagem preferida)"
                        class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <div>
                        <textarea
                            v-model="form.content"
                            maxlength="500"
                            placeholder="Conteúdo (ex: Prefiro respostas em português europeu)"
                            rows="3"
                            class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        />
                        <p class="text-xs text-muted-foreground text-right mt-0.5">{{ form.content.length }}/500</p>
                    </div>
                    <input
                        v-model="form.category"
                        maxlength="50"
                        placeholder="Categoria (opcional, ex: Preferências)"
                        class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <div class="flex justify-end gap-2 pt-1">
                        <button @click="cancelForm" class="px-4 py-2 text-sm rounded-md border border-border hover:bg-muted transition-colors">Cancelar</button>
                        <button
                            :disabled="saving || !form.title.trim() || !form.content.trim()"
                            @click="saveForm"
                            class="px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                        >
                            {{ saving ? 'A guardar…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- List -->
            <div v-if="loading" class="text-sm text-muted-foreground text-center py-8">A carregar…</div>

            <template v-else>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Memórias
                        <span class="text-xs font-normal normal-case ml-1">({{ activeCount }} ativas de {{ memories.length }})</span>
                    </h2>
                </div>

                <div v-if="memories.length === 0" class="text-sm text-muted-foreground py-6 text-center border border-dashed border-border rounded-lg">
                    Nenhuma memória criada ainda.
                </div>

                <div
                    v-for="memory in memories"
                    :key="memory.id"
                    class="mb-3 border border-border rounded-lg p-4 bg-background"
                    :class="memory.status === 'inactive' ? 'opacity-60' : ''"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-sm">{{ memory.title }}</span>
                                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', memory.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                                    {{ memory.status === 'active' ? 'Ativa' : 'Inativa' }}
                                </span>
                                <span class="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-full">
                                    {{ memory.source === 'manual' ? 'Manual' : 'Sugerida' }}
                                </span>
                                <span v-if="memory.category" class="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-full">{{ memory.category }}</span>
                            </div>
                            <p class="text-sm text-muted-foreground mt-1.5">{{ memory.content }}</p>
                            <p class="text-xs text-muted-foreground mt-1">Último uso: {{ formatDate(memory.last_used_at) }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="openEdit(memory)" class="text-xs px-3 py-1.5 rounded-md border border-border hover:bg-muted transition-colors">Editar</button>
                            <button @click="toggleMemory(memory)" class="text-xs px-3 py-1.5 rounded-md border border-border hover:bg-muted transition-colors">
                                {{ memory.status === 'active' ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button @click="deleteMemory(memory)" class="text-xs px-3 py-1.5 rounded-md border border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground transition-colors">Apagar</button>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>
