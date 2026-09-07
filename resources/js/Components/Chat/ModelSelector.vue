<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    modelList: {
        type: Array,
        default: () => [],
    },
    modelId: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['update:modelId'])

const open = ref(false)
const search = ref('')
const container = ref(null)

const selectedName = computed(() => {
    const found = props.modelList.find(m => m.id === props.modelId)
    return found ? found.name : props.modelId || 'Selecionar modelo'
})

const filtered = computed(() => {
    if (!search.value) return props.modelList
    const q = search.value.toLowerCase()
    return props.modelList.filter(m => m.name.toLowerCase().includes(q))
})

function select(id) {
    emit('update:modelId', id)
    open.value = false
    search.value = ''
}

function onClickOutside(e) {
    if (container.value && !container.value.contains(e.target)) {
        open.value = false
        search.value = ''
    }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
    <div ref="container" class="relative flex-1">
        <button
            type="button"
            class="w-full text-left text-xs px-3 py-1.5 rounded-md border border-input bg-background text-foreground focus:outline-none focus:ring-1 focus:ring-ring truncate"
            @click="open = !open"
        >
            {{ selectedName }}
        </button>

        <div
            v-if="open"
            class="absolute z-50 bottom-full mb-1 left-0 min-w-[320px] rounded-md border border-input bg-background shadow-md"
        >
            <div class="p-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar modelo..."
                    autofocus
                    class="w-full text-sm px-3 py-2 rounded border border-input bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                />
            </div>
            <ul class="max-h-64 overflow-y-auto">
                <li
                    v-for="m in filtered"
                    :key="m.id"
                    class="px-3 py-2 text-sm cursor-pointer hover:bg-muted text-foreground"
                    :class="m.id === modelId ? 'font-semibold' : ''"
                    @mousedown.prevent="select(m.id)"
                >
                    <span class="block">
                        {{ m.name }}
                        <span
                            v-if="m.id === 'openai/gpt-5.4-nano'"
                            class="text-[10px] bg-green-100 text-green-700 rounded px-1.5 py-0.5 font-medium ml-1"
                        >Rápido · Baixo custo</span>
                    </span>
                    <span class="block text-xs text-muted-foreground">{{ m.id }}</span>
                </li>
                <li v-if="!filtered.length" class="px-3 py-2 text-sm text-muted-foreground">
                    Nenhum modelo encontrado
                </li>
            </ul>
        </div>
    </div>
</template>
