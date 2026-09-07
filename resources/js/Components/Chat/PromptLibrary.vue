<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
    modelValue: Boolean,
})

const emit = defineEmits(['update:modelValue', 'select'])

const prompts = ref([])
const search = ref('')
const form = ref({ name: '', text: '' })
const editingId = ref(null)

const filtered = computed(() => {
    if (!search.value) return prompts.value
    return prompts.value.filter(p =>
        p.name.toLowerCase().includes(search.value.toLowerCase())
    )
})

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content
}

async function loadPrompts() {
    const res = await fetch('/api/prompts')
    prompts.value = await res.json()
}

async function save() {
    const method = editingId.value ? 'PUT' : 'POST'
    const url = editingId.value ? `/api/prompts/${editingId.value}` : '/api/prompts'

    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify(form.value),
    })

    if (!res.ok) return

    resetForm()
    await loadPrompts()
}

async function deletePrompt(prompt) {
    if (!confirm(`Apagar o prompt "${prompt.name}"?`)) return

    await fetch(`/api/prompts/${prompt.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })

    await loadPrompts()
}

function startEdit(prompt) {
    editingId.value = prompt.id
    form.value = { name: prompt.name, text: prompt.text }
}

function resetForm() {
    editingId.value = null
    form.value = { name: '', text: '' }
}

function selectPrompt(prompt) {
    emit('select', prompt.text)
    emit('update:modelValue', false)
}

function close() {
    resetForm()
    emit('update:modelValue', false)
}

onMounted(loadPrompts)
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-xl w-full max-w-lg max-h-[80vh] overflow-y-auto p-6 relative">

                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-foreground">Biblioteca de Prompts</h2>
                    <button class="text-muted-foreground hover:text-foreground transition-colors" @click="close">✕</button>
                </div>

                <!-- Form -->
                <div class="mb-5 space-y-2">
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                        {{ editingId ? 'Editar prompt' : 'Novo prompt' }}
                    </p>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="Nome"
                        maxlength="80"
                        class="w-full text-sm px-3 py-2 rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <textarea
                        v-model="form.text"
                        placeholder="Texto do prompt"
                        maxlength="2000"
                        rows="3"
                        class="w-full text-sm px-3 py-2 rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                    />
                    <div class="flex gap-2">
                        <button
                            class="text-sm px-4 py-1.5 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
                            @click="save"
                        >
                            Guardar
                        </button>
                        <button
                            v-if="editingId"
                            class="text-sm px-4 py-1.5 rounded-md border border-input text-muted-foreground hover:text-foreground transition-colors"
                            @click="resetForm"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>

                <hr class="border-border mb-4" />

                <!-- Search -->
                <input
                    v-model="search"
                    type="text"
                    placeholder="Pesquisar prompts..."
                    class="w-full text-sm px-3 py-2 rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring mb-3"
                />

                <!-- List -->
                <div v-if="filtered.length === 0" class="text-sm text-muted-foreground text-center py-4">
                    Nenhum prompt encontrado.
                </div>

                <div
                    v-for="prompt in filtered"
                    :key="prompt.id"
                    class="flex items-start justify-between gap-2 px-3 py-3 rounded-lg hover:bg-muted cursor-pointer transition-colors group"
                    @click="selectPrompt(prompt)"
                >
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-foreground truncate">{{ prompt.name }}</p>
                        <p class="text-xs text-muted-foreground truncate">{{ prompt.text.slice(0, 80) }}{{ prompt.text.length > 80 ? '…' : '' }}</p>
                    </div>
                    <div class="flex gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                            @click.stop="startEdit(prompt)"
                            title="Editar"
                        >✏️</button>
                        <button
                            class="text-sm text-muted-foreground hover:text-destructive transition-colors"
                            @click.stop="deletePrompt(prompt)"
                            title="Apagar"
                        >🗑️</button>
                    </div>
                </div>

            </div>
        </div>
    </Teleport>
</template>
