<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const csrf = () => document.querySelector('meta[name="csrf-token"]').content

const collections = ref([])
const newName = ref('')
const newDescription = ref('')
const creating = ref(false)
const selectedCollection = ref(null)
const documents = ref([])
const uploadFile = ref(null)
const selectedFile = ref(null)
const uploading = ref(false)
let pollInterval = null

function onFileChange(event) {
    selectedFile.value = event.target.files[0] ?? null
}

async function fetchCollections() {
    const res = await fetch('/api/knowledge')
    collections.value = await res.json()
}

async function createCollection() {
    if (!newName.value.trim()) return
    creating.value = true
    const res = await fetch('/api/knowledge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify({ name: newName.value, description: newDescription.value }),
    })
    const data = await res.json()
    data.documents_count = 0
    collections.value.push(data)
    newName.value = ''
    newDescription.value = ''
    creating.value = false
}

async function deleteCollection(collection) {
    if (!confirm(`Apagar a coleção "${collection.name}"? Todos os documentos serão eliminados.`)) return
    await fetch(`/api/knowledge/${collection.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf() },
    })
    collections.value = collections.value.filter(c => c.id !== collection.id)
    if (selectedCollection.value?.id === collection.id) {
        selectedCollection.value = null
        documents.value = []
        stopPolling()
    }
}

async function openDocuments(collection) {
    selectedCollection.value = collection
    await fetchDocuments()
    startPolling()
}

async function fetchDocuments() {
    if (!selectedCollection.value) return
    const res = await fetch(`/api/knowledge/${selectedCollection.value.id}/documents`)
    const data = await res.json()
    documents.value = data.documents ?? []
    const col = collections.value.find(c => c.id === selectedCollection.value.id)
    if (col) col.documents_count = documents.value.length
}

function startPolling() {
    stopPolling()
    pollInterval = setInterval(() => {
        const needsRefresh = documents.value.some(d => d.status === 'pending' || d.status === 'processing')
        if (needsRefresh) fetchDocuments()
    }, 5000)
}

function stopPolling() {
    if (pollInterval) clearInterval(pollInterval)
    pollInterval = null
}

async function uploadDocument() {
    if (!selectedFile.value) return
    uploading.value = true
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    const res = await fetch(`/api/knowledge/${selectedCollection.value.id}/documents`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf() },
        body: formData,
    })
    const doc = await res.json()
    documents.value.push(doc)
    selectedFile.value = null
    if (uploadFile.value?.[0]) uploadFile.value[0].value = ''
    uploading.value = false
    startPolling()
}

async function deleteDocument(doc) {
    if (!confirm(`Apagar "${doc.original_name}"?`)) return
    await fetch(`/api/knowledge/documents/${doc.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf() },
    })
    documents.value = documents.value.filter(d => d.id !== doc.id)
    const col = collections.value.find(c => c.id === selectedCollection.value.id)
    if (col) col.documents_count = documents.value.length
}

const statusClass = {
    pending:    'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    ready:      'bg-green-100 text-green-800',
    error:      'bg-red-100 text-red-800',
}

const statusLabel = {
    pending:    'Pendente',
    processing: 'A processar',
    ready:      'Pronto',
    error:      'Erro',
}

onMounted(fetchCollections)
onUnmounted(stopPolling)
</script>

<template>
    <Head title="Base de Conhecimento" />

    <div class="min-h-screen bg-background text-foreground">
        <div class="max-w-4xl mx-auto px-4 py-8">

            <!-- Back link -->
            <a href="/dashboard" class="text-sm text-muted-foreground hover:text-foreground transition-colors">← Voltar ao chat</a>

            <!-- Header -->
            <div class="mt-4 mb-8">
                <h1 class="text-2xl font-bold">Base de Conhecimento</h1>
                <p class="text-muted-foreground text-sm mt-1">Gere as tuas coleções de documentos para RAG</p>
            </div>

            <!-- Create collection -->
            <div class="border border-border rounded-lg p-5 mb-8 bg-muted/20">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground mb-4">Criar nova coleção</h2>
                <div class="flex flex-col gap-3">
                    <input
                        v-model="newName"
                        maxlength="100"
                        placeholder="Nome da coleção"
                        class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <textarea
                        v-model="newDescription"
                        maxlength="500"
                        placeholder="Descrição (opcional)"
                        rows="2"
                        class="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                    />
                    <div class="flex justify-end">
                        <button
                            :disabled="creating || !newName.trim()"
                            @click="createCollection"
                            class="px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                        >
                            Criar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Collections list -->
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground mb-3">As minhas coleções</h2>

                <div v-if="collections.length === 0" class="text-sm text-muted-foreground py-6 text-center border border-dashed border-border rounded-lg">
                    Nenhuma coleção criada ainda.
                </div>

                <div v-for="collection in collections" :key="collection.id" class="mb-3">
                    <div class="border border-border rounded-lg p-4 bg-background flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm">{{ collection.name }}</span>
                                <span class="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-full">{{ collection.documents_count }} doc{{ collection.documents_count !== 1 ? 's' : '' }}</span>
                            </div>
                            <p v-if="collection.description" class="text-xs text-muted-foreground mt-1 truncate">{{ collection.description }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                @click="openDocuments(collection)"
                                class="text-xs px-3 py-1.5 rounded-md border border-border hover:bg-muted transition-colors"
                            >
                                Ver documentos
                            </button>
                            <button
                                @click="deleteCollection(collection)"
                                class="text-xs px-3 py-1.5 rounded-md border border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground transition-colors"
                            >
                                Apagar
                            </button>
                        </div>
                    </div>

                    <!-- Documents panel -->
                    <div v-if="selectedCollection?.id === collection.id" class="border border-t-0 border-border rounded-b-lg bg-muted/10 p-4">
                        <h3 class="text-sm font-semibold mb-4">{{ collection.name }} — Documentos</h3>

                        <!-- Upload -->
                        <div class="flex items-center gap-3 mb-5">
                            <input
                                ref="uploadFile"
                                type="file"
                                accept=".txt,.md,.pdf,.docx"
                                @change="onFileChange"
                                class="text-sm text-muted-foreground file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-muted file:text-foreground hover:file:bg-muted/80"
                            />
                            <button
                                :disabled="uploading"
                                @click="uploadDocument"
                                class="text-xs px-3 py-1.5 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors shrink-0"
                            >
                                {{ uploading ? 'A carregar...' : 'Carregar documento' }}
                            </button>
                        </div>

                        <!-- Document list -->
                        <div v-if="documents.length === 0" class="text-sm text-muted-foreground text-center py-4">
                            Nenhum documento nesta coleção.
                        </div>

                        <div v-for="doc in documents" :key="doc.id" class="flex items-center justify-between py-2 border-b border-border/50 last:border-0 gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm truncate">{{ doc.original_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ doc.segments_count ?? 0 }} segmentos</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusClass[doc.status]]">
                                    {{ statusLabel[doc.status] }}
                                </span>
                                <button
                                    @click="deleteDocument(doc)"
                                    class="text-xs text-muted-foreground hover:text-destructive transition-colors"
                                >
                                    Apagar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
