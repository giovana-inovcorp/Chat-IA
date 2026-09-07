<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const logs = ref([])
const loading = ref(false)
const pagination = ref(null)

const filters = ref({
    model: '',
    date_from: '',
    date_to: '',
})

function buildParams(page = 1) {
    const params = new URLSearchParams()
    params.set('page', page)
    if (filters.value.model)     params.set('model', filters.value.model)
    if (filters.value.date_from) params.set('date_from', filters.value.date_from)
    if (filters.value.date_to)   params.set('date_to', filters.value.date_to)
    return params
}

async function fetchLogs(page = 1) {
    loading.value = true
    const params = buildParams(page)
    const res = await fetch(`/admin/audit-logs?${params}`)
    const data = await res.json()
    logs.value = data.data
    pagination.value = data
    loading.value = false
}

function fetchLogsPage(page) {
    fetchLogs(page)
}

function clearFilters() {
    filters.value = { model: '', date_from: '', date_to: '' }
    fetchLogs()
}

function exportCsv() {
    const params = buildParams()
    params.delete('page')
    window.open(`/admin/audit-logs/export?${params}`, '_blank')
}

onMounted(fetchLogs)
</script>

<template>
    <Head title="Logs de Auditoria" />

    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto px-6 py-8">

            <!-- Back link -->
            <a href="/dashboard" class="inline-flex items-center text-sm text-muted-foreground hover:text-foreground transition-colors mb-6">
                ← Voltar ao chat
            </a>

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900">Logs de Auditoria</h1>
                <p class="text-sm text-gray-500 mt-1">Registo de todas as interacções com modelos de IA</p>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-600">Modelo</label>
                        <input
                            v-model="filters.model"
                            type="text"
                            placeholder="ex: openai/gpt-4o-mini"
                            class="text-sm px-3 py-2 rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-ring w-52"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-600">Data de</label>
                        <input
                            v-model="filters.date_from"
                            type="date"
                            class="text-sm px-3 py-2 rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-gray-600">Data até</label>
                        <input
                            v-model="filters.date_to"
                            type="date"
                            class="text-sm px-3 py-2 rounded-md border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                    <button
                        class="px-4 py-2 text-sm rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors font-medium"
                        @click="fetchLogs()"
                    >
                        Filtrar
                    </button>
                    <button
                        class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors"
                        @click="clearFilters"
                    >
                        Limpar filtros
                    </button>
                    <button
                        class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors ml-auto"
                        @click="exportCsv"
                    >
                        Exportar CSV
                    </button>
                </div>
            </div>

            <!-- Pagination info -->
            <div v-if="pagination" class="text-sm text-gray-500 mb-3">
                A mostrar {{ pagination.to ?? 0 }} de {{ pagination.total }} registos
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div v-if="loading" class="p-8 text-center text-sm text-gray-500">
                    A carregar...
                </div>

                <div v-else-if="logs.length === 0" class="p-8 text-center text-sm text-gray-500">
                    Nenhum registo encontrado.
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 border-b border-gray-200 text-left">
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">ID</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Utilizador</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Email</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Data/Hora</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Modelo</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide text-right">Tokens In</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide text-right">Tokens Out</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide text-right">Custo (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(log, index) in logs"
                            :key="log.id"
                            :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ log.id }}</td>
                            <td class="px-4 py-3 text-gray-900 font-medium">{{ log.user?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ log.user?.email ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ new Date(log.created_at).toLocaleString('pt-PT') }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-mono">{{ log.model }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ log.input_tokens.toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ log.output_tokens.toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 font-mono text-xs">${{ Number(log.cost_usd).toFixed(6) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination links -->
            <div v-if="pagination && pagination.last_page > 1" class="flex gap-2 mt-4 justify-end">
                <button
                    v-for="page in pagination.last_page"
                    :key="page"
                    class="px-3 py-1.5 text-sm rounded-md border transition-colors"
                    :class="page === pagination.current_page
                        ? 'bg-primary text-primary-foreground border-primary'
                        : 'border-gray-300 text-gray-600 hover:bg-gray-100'"
                    @click="fetchLogsPage(page)"
                >
                    {{ page }}
                </button>
            </div>

        </div>
    </div>
</template>
