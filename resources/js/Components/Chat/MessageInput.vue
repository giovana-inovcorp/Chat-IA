<script setup>
import { ref, watch } from 'vue'
import ModelSelector from '@/Components/Chat/ModelSelector.vue'
import PromptLibrary from '@/Components/Chat/PromptLibrary.vue'

const props = defineProps({
    disabled: Boolean,
    modelList: {
        type: Array,
        default: () => [],
    },
    apiConfigured: {
        type: Boolean,
        default: true,
    },
    initialContent: { type: String, default: '' },
    isHomepage: { type: Boolean, default: false },
    usageInfo: { type: Object, default: null },
    collections: { type: Array, default: () => [] },
})

const emit = defineEmits(['send'])

const content = ref('')

watch(() => props.initialContent, (val) => {
    if (val) content.value = val
})
const model = ref('openai/gpt-4o-mini')
const temperature = ref(0.7)
const maxTokens = ref(1024)
const showConfig = ref(false)
const showPromptLibrary = ref(false)
const attachedFile = ref(null)
const fileInput = ref(null)
const selectedCollectionId = ref('')

function onPromptSelect(text) {
    content.value = text
}

function onFileChange(e) {
    const file = e.target.files[0]
    if (!file) return
    if (file.size > 5242880) {
        alert('O ficheiro é demasiado grande. Máximo 5MB.')
        e.target.value = ''
        return
    }
    attachedFile.value = file
    e.target.value = ''
}

function removeFile() {
    attachedFile.value = null
}

function submit() {
    if (!content.value.trim() || props.disabled) return

    emit('send', {
        content: content.value.trim(),
        model: model.value,
        temperature: temperature.value,
        maxTokens: maxTokens.value,
        file: attachedFile.value ?? null,
        collection_id: selectedCollectionId.value || null,
    })

    content.value = ''
    attachedFile.value = null
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        submit()
    }
}
</script>

<template>
    <div :class="['p-4 shrink-0', isHomepage ? 'max-w-xl w-full mx-auto' : 'border-t border-border']">

        <!-- Selector de modelo, coleção e config -->
        <div :class="['flex items-center gap-2 mb-3', isHomepage ? 'justify-center' : '']">
            <ModelSelector
                v-model:modelId="model"
                :modelList="modelList"
            />
            <select
                v-if="collections.length > 0"
                v-model="selectedCollectionId"
                class="text-xs h-8 px-2 rounded border border-input bg-background text-muted-foreground hover:border-ring transition-colors focus:outline-none focus:ring-2 focus:ring-ring"
            >
                <option value="">🗂️ Sem contexto</option>
                <option v-for="collection in collections" :key="collection.id" :value="collection.id">
                    🗂️ {{ collection.name }}
                </option>
            </select>
            <button
                class="text-xs text-muted-foreground hover:text-foreground transition-colors"
                @click="showConfig = !showConfig"
            >
                ⚙ Config
            </button>
        </div>

        <!-- Painel de configuração -->
        <div v-if="showConfig" class="flex gap-4 mb-3 p-3 rounded-lg bg-muted text-xs">
            <label class="flex flex-col gap-1">
                <span class="text-muted-foreground">Temperature (0–2)</span>
                <input
                    v-model.number="temperature"
                    type="number"
                    min="0" max="2" step="0.1"
                    class="w-20 px-2 py-1 rounded border border-input bg-background"
                />
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-muted-foreground">Max tokens</span>
                <input
                    v-model.number="maxTokens"
                    type="number"
                    min="1" max="8192" step="128"
                    class="w-24 px-2 py-1 rounded border border-input bg-background"
                />
            </label>
        </div>

        <!-- Campo de texto + botão enviar -->
        <div v-if="!apiConfigured" class="mb-3 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-600 text-xs">
            A chave de API não está configurada. Contacta o administrador.
        </div>
        <div v-if="usageInfo?.warningLevel === 'warning'" class="mb-3 px-3 py-2 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs">
            Atingiste 80% do teu limite mensal.
        </div>

        <!-- Ficheiro anexado -->
        <div v-if="attachedFile" class="flex items-center gap-2 mb-3 px-3 py-1.5 rounded-lg bg-muted text-xs text-foreground">
            <span class="truncate flex-1">📄 {{ attachedFile.name }}</span>
            <button class="text-muted-foreground hover:text-destructive transition-colors shrink-0" @click="removeFile">✕</button>
        </div>

        <input
            ref="fileInput"
            type="file"
            class="hidden"
            accept=".txt,.md,.pdf,.js,.ts,.php,.py,.html,.css,.json,.xml,.csv"
            @change="onFileChange"
        />

        <div class="flex gap-2 items-end">
            <button
                class="self-end flex items-center justify-center rounded-full w-8 h-8 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors shrink-0"
                title="Biblioteca de prompts"
                @click="showPromptLibrary = true"
            >
                📚
            </button>
            <button
                class="self-end flex items-center justify-center rounded-full w-8 h-8 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors shrink-0"
                title="Anexar ficheiro"
                type="button"
                @click="fileInput.click()"
            >
                📎
            </button>
            <textarea
                v-model="content"
                :disabled="disabled || !apiConfigured"
                placeholder="Escreve uma mensagem..."
                rows="1"
                class="flex-1 resize-none text-sm px-4 py-3 rounded-xl border border-input bg-background focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50 disabled:cursor-not-allowed"
                style="min-height: 48px; max-height: 160px;"
                @keydown="onKeydown"
                @input="$event.target.style.height = 'auto'; $event.target.style.height = $event.target.scrollHeight + 'px'"
            />
            <button
                :disabled="disabled || !content.trim() || !apiConfigured"
                class="self-end flex items-center justify-center rounded-full w-10 h-10 bg-primary text-primary-foreground disabled:opacity-50 disabled:cursor-not-allowed transition-colors hover:bg-primary/90"
                @click="submit"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                    <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
                </svg>
            </button>
        </div>

        <PromptLibrary v-model="showPromptLibrary" @select="onPromptSelect" />
    </div>
</template>