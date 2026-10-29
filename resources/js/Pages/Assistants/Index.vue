<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const csrf = () => document.querySelector('meta[name="csrf-token"]').content

const assistants = ref([])
const collections = ref([])
const models = ref([])
const tools = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingId = ref(null)
const showArchived = ref(false)
const saving = ref(false)

const defaultForm = () => ({
    name: '',
    description: '',
    icon: '',
    instructions: '',
    model: 'openai/gpt-4o-mini',
    temperature: 0.7,
    max_tokens: 1024,
    is_memory_active: true,
    collection_ids: [],
    allowed_tools: [],
})
const form = ref(defaultForm())

const activeAssistants   = computed(() => assistants.value.filter(a => a.status === 'active'))
const archivedAssistants = computed(() => assistants.value.filter(a => a.status === 'archived'))
const visibleAssistants  = computed(() => showArchived.value ? archivedAssistants.value : activeAssistants.value)

async function fetchAll() {
    loading.value = true
    const [a, c, m, t] = await Promise.all([
        fetch('/api/assistants').then(r => r.json()),
        fetch('/api/knowledge').then(r => r.json()),
        fetch('/api/models').then(r => r.json()),
        fetch('/api/tools').then(r => r.json()),
    ])
    assistants.value = a
    collections.value = c
    models.value = m.models ?? []
    tools.value = t
    loading.value = false
}

function openCreate() {
    editingId.value = null
    form.value = defaultForm()
    showForm.value = true
}

function openEdit(assistant) {
    editingId.value = assistant.id
    form.value = {
        name: assistant.name,
        description: assistant.description ?? '',
        icon: assistant.icon ?? '',
        instructions: assistant.instructions ?? '',
        model: assistant.model,
        temperature: parseFloat(assistant.temperature),
        max_tokens: assistant.max_tokens,
        is_memory_active: assistant.is_memory_active,
        collection_ids: assistant.knowledge_collections?.map(c => c.id) ?? [],
        allowed_tools: assistant.allowed_tools ?? [],
    }
    showForm.value = true
}

function cancelForm() {
    showForm.value = false
    editingId.value = null
}

async function saveForm() {
    if (!form.value.name.trim()) return
    saving.value = true
    const url    = editingId.value ? `/api/assistants/${editingId.value}` : '/api/assistants'
    const method = editingId.value ? 'PUT' : 'POST'
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(form.value),
    })
    const data = await res.json()
    if (editingId.value) {
        const idx = assistants.value.findIndex(a => a.id === editingId.value)
        if (idx !== -1) assistants.value.splice(idx, 1, data)
    } else {
        assistants.value.push(data)
    }
    saving.value = false
    cancelForm()
}

async function toggleFavorite(assistant) {
    const res = await fetch(`/api/assistants/${assistant.id}/favorite`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrf() },
    })
    const data = await res.json()
    const idx = assistants.value.findIndex(a => a.id === assistant.id)
    if (idx !== -1) assistants.value[idx] = { ...assistants.value[idx], ...data }
}

async function toggleArchive(assistant) {
    const res = await fetch(`/api/assistants/${assistant.id}/archive`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrf() },
    })
    const data = await res.json()
    const idx = assistants.value.findIndex(a => a.id === assistant.id)
    if (idx !== -1) assistants.value[idx] = { ...assistants.value[idx], ...data }
}

async function duplicate(assistant) {
    const res = await fetch(`/api/assistants/${assistant.id}/duplicate`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf() },
    })
    const data = await res.json()
    assistants.value.push(data)
}

async function deleteAssistant(assistant) {
    if (!confirm(`Apagar o assistente "${assistant.name}"?`)) return
    await fetch(`/api/assistants/${assistant.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf() },
    })
    assistants.value = assistants.value.filter(a => a.id !== assistant.id)
}

function toggleCollection(id) {
    const idx = form.value.collection_ids.indexOf(id)
    if (idx === -1) form.value.collection_ids.push(id)
    else form.value.collection_ids.splice(idx, 1)
}

function toggleTool(name) {
    const idx = form.value.allowed_tools.indexOf(name)
    if (idx === -1) form.value.allowed_tools.push(name)
    else form.value.allowed_tools.splice(idx, 1)
}

onMounted(fetchAll)
</script>

<template>
    <Head title="Assistentes" />

    <div class="min-h-screen bg-background text-foreground">
        <div class="max-w-4xl mx-auto px-4 py-8">

            <!-- Back link -->
            <a href="/dashboard" class="text-sm text-muted-foreground hover:text-foreground transition-colors">← Voltar ao chat</a>

            <!-- Header -->
            <div class="mt-4 mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Assistentes</h1>
                    <p class="text-muted-foreground text-sm mt-1">Cria assistentes com personalidade, instruções e base de conhecimento</p>
                </div>
                <button
                    @click="openCreate"
                    class="px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    + Novo assistente
                </button>
            </div>

            <!-- Form -->
            <div v-if="showForm" class="border border-border rounded-lg p-5 mb-8 bg-muted/20">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground mb-4">
                    {{ editingId ? 'Editar assistente' : 'Criar assistente' }}
                </h2>

                <div class="flex flex-col gap-4">
                    <!-- Name + Icon -->
                    <div class="flex gap-3">
                        <input
                            v-model="form.icon"
                            maxlength="10"
                            placeholder="🤖"
                            class="w-16 px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring text-center"
                        />
                        <input
                            v-model="form.name"
                            maxlength="100"
                            placeholder="Nome do assistente"
                            class="flex-1 px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>

                    <!-- Description -->
                    <input
                        v-model="form.description"
                        maxlength="500"
                        placeholder="Descrição (opcional)"
                        class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                    />

                    <!-- Instructions -->
                    <textarea
                        v-model="form.instructions"
                        maxlength="5000"
                        placeholder="Instruções do sistema (ex: És um especialista em…)"
                        rows="4"
                        class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                    />

                    <!-- Model -->
                    <div>
                        <label class="block text-xs text-muted-foreground mb-1">Modelo</label>
                        <select
                            v-model="form.model"
                            class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option v-for="m in models" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>

                    <!-- Temperature + Max Tokens -->
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-xs text-muted-foreground mb-1">Temperatura: {{ form.temperature }}</label>
                            <input
                                v-model.number="form.temperature"
                                type="range" min="0" max="2" step="0.05"
                                class="w-full"
                            />
                        </div>
                        <div class="w-40">
                            <label class="block text-xs text-muted-foreground mb-1">Máx. tokens</label>
                            <input
                                v-model.number="form.max_tokens"
                                type="number" min="1" max="8192"
                                class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <!-- Memory active -->
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input v-model="form.is_memory_active" type="checkbox" class="rounded" />
                        Memória ativa
                    </label>

                    <!-- Knowledge collections -->
                    <div v-if="collections.length > 0">
                        <label class="block text-xs text-muted-foreground mb-2">Coleções de conhecimento</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="col in collections"
                                :key="col.id"
                                type="button"
                                @click="toggleCollection(col.id)"
                                :class="[
                                    'text-xs px-3 py-1 rounded-full border transition-colors',
                                    form.collection_ids.includes(col.id)
                                        ? 'bg-primary text-primary-foreground border-primary'
                                        : 'border-border text-muted-foreground hover:bg-muted'
                                ]"
                            >
                                {{ col.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Ferramentas permitidas -->
                    <div v-if="tools.length > 0">
                        <label class="block text-xs text-muted-foreground mb-2">Ferramentas permitidas</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="tool in tools"
                                :key="tool.name"
                                type="button"
                                @click="toggleTool(tool.name)"
                                :title="tool.description"
                                :class="[
                                    'text-xs px-3 py-1 rounded-full border transition-colors',
                                    form.allowed_tools.includes(tool.name)
                                        ? 'bg-primary text-primary-foreground border-primary'
                                        : 'border-border text-muted-foreground hover:bg-muted'
                                ]"
                            >
                                {{ tool.name }}
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Sem ferramentas selecionadas, o assistente não usa nenhuma.</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2 pt-1">
                        <button
                            @click="cancelForm"
                            class="px-4 py-2 text-sm rounded-md border border-border hover:bg-muted transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            :disabled="saving || !form.name.trim()"
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
                <!-- Toggle archived -->
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ showArchived ? 'Arquivados' : 'Ativos' }}
                        <span class="text-xs font-normal normal-case ml-1">({{ visibleAssistants.length }})</span>
                    </h2>
                    <button
                        v-if="archivedAssistants.length > 0 || showArchived"
                        @click="showArchived = !showArchived"
                        class="text-xs text-muted-foreground hover:text-foreground transition-colors"
                    >
                        {{ showArchived ? '← Ver ativos' : `Ver arquivados (${archivedAssistants.length})` }}
                    </button>
                </div>

                <div v-if="visibleAssistants.length === 0" class="text-sm text-muted-foreground py-6 text-center border border-dashed border-border rounded-lg">
                    {{ showArchived ? 'Nenhum assistente arquivado.' : 'Nenhum assistente criado ainda.' }}
                </div>

                <div
                    v-for="assistant in visibleAssistants"
                    :key="assistant.id"
                    class="mb-3 border border-border rounded-lg p-4 bg-background flex items-start justify-between gap-4"
                >
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <span class="text-2xl leading-none mt-0.5">{{ assistant.icon || '🤖' }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-sm">{{ assistant.name }}</span>
                                <span v-if="assistant.is_favorite" class="text-xs">⭐</span>
                                <span class="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-full">{{ assistant.model }}</span>
                                <span v-if="assistant.knowledge_collections?.length" class="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-full">
                                    {{ assistant.knowledge_collections.length }} coleção{{ assistant.knowledge_collections.length !== 1 ? 'ões' : '' }}
                                </span>
                            </div>
                            <p v-if="assistant.description" class="text-xs text-muted-foreground mt-1 truncate">{{ assistant.description }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="toggleFavorite(assistant)" class="text-xs text-muted-foreground hover:text-foreground transition-colors" :title="assistant.is_favorite ? 'Remover dos favoritos' : 'Favorito'">
                            {{ assistant.is_favorite ? '★' : '☆' }}
                        </button>
                        <button @click="openEdit(assistant)" class="text-xs px-3 py-1.5 rounded-md border border-border hover:bg-muted transition-colors">Editar</button>
                        <button @click="duplicate(assistant)" class="text-xs px-3 py-1.5 rounded-md border border-border hover:bg-muted transition-colors">Duplicar</button>
                        <button @click="toggleArchive(assistant)" class="text-xs px-3 py-1.5 rounded-md border border-border hover:bg-muted transition-colors">
                            {{ assistant.status === 'active' ? 'Arquivar' : 'Restaurar' }}
                        </button>
                        <button @click="deleteAssistant(assistant)" class="text-xs px-3 py-1.5 rounded-md border border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground transition-colors">Apagar</button>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>
