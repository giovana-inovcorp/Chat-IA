<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const users = ref([])
const loading = ref(false)
const editingId = ref(null)
const editForm = ref({ monthly_token_limit: '', monthly_cost_limit_usd: '' })
const saving = ref(false)
const resetting = ref(null)

async function fetchUsers() {
    loading.value = true
    const res = await fetch('/admin/users')
    users.value = await res.json()
    loading.value = false
}

function startEdit(user) {
    editingId.value = user.id
    editForm.value = {
        monthly_token_limit: user.monthly_token_limit ?? '',
        monthly_cost_limit_usd: user.monthly_cost_limit_usd ?? '',
    }
}

function cancelEdit() {
    editingId.value = null
}

async function saveEdit(user) {
    saving.value = true
    const res = await fetch(`/admin/users/${user.id}/limits`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            monthly_token_limit: editForm.value.monthly_token_limit === '' ? null : Number(editForm.value.monthly_token_limit),
            monthly_cost_limit_usd: editForm.value.monthly_cost_limit_usd === '' ? null : Number(editForm.value.monthly_cost_limit_usd),
        }),
    })
    const updated = await res.json()
    const index = users.value.findIndex(u => u.id === user.id)
    if (index !== -1) users.value[index] = updated
    editingId.value = null
    saving.value = false
}

async function resetUsage(user) {
    if (!confirm(`Repor contadores de ${user.name}?`)) return
    resetting.value = user.id
    await fetch(`/admin/users/${user.id}/reset-usage`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    await fetchUsers()
    resetting.value = null
}

function usagePercent(used, limit) {
    if (!limit) return null
    return Math.min(100, Math.round((used / limit) * 100))
}

function progressColor(pct) {
    if (pct >= 100) return 'bg-red-500'
    if (pct >= 80) return 'bg-yellow-400'
    return 'bg-green-500'
}

onMounted(fetchUsers)
</script>

<template>
    <Head title="Limites de Uso" />

    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto px-6 py-8">

            <!-- Back links -->
            <div class="flex items-center gap-4 mb-6">
                <a href="/dashboard" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                    ← Voltar ao chat
                </a>
                <span class="text-gray-300">|</span>
                <a href="/admin" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                    ← Logs de Auditoria
                </a>
            </div>

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900">Limites de Uso</h1>
                <p class="text-sm text-gray-500 mt-1">Gestão de limites mensais por utilizador</p>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div v-if="loading" class="p-8 text-center text-sm text-gray-500">
                    A carregar...
                </div>

                <div v-else-if="users.length === 0" class="p-8 text-center text-sm text-gray-500">
                    Nenhum utilizador encontrado.
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 border-b border-gray-200 text-left">
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Utilizador</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Email</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide text-center">Admin</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Tokens usados</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Limite tokens</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Custo usado</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Limite custo (USD)</th>
                            <th class="px-4 py-3 font-medium text-gray-600 text-xs uppercase tracking-wide">Acções</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(user, index) in users" :key="user.id">
                            <!-- Normal row -->
                            <tr
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                                class="border-b border-gray-100"
                            >
                                <td class="px-4 py-3 text-gray-900 font-medium">{{ user.name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ user.email }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="user.is_admin" class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-xs font-medium">Admin</span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>

                                <!-- Tokens used + progress -->
                                <td class="px-4 py-3">
                                    <div class="text-gray-700 tabular-nums">{{ Number(user.tokens_used_this_month).toLocaleString() }}</div>
                                    <div v-if="usagePercent(user.tokens_used_this_month, user.monthly_token_limit) !== null" class="mt-1 w-24 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div
                                            :class="progressColor(usagePercent(user.tokens_used_this_month, user.monthly_token_limit))"
                                            class="h-full rounded-full transition-all"
                                            :style="`width: ${usagePercent(user.tokens_used_this_month, user.monthly_token_limit)}%`"
                                        />
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-gray-500">
                                    {{ user.monthly_token_limit ? Number(user.monthly_token_limit).toLocaleString() : '—' }}
                                </td>

                                <!-- Cost used + progress -->
                                <td class="px-4 py-3">
                                    <div class="text-gray-700 font-mono text-xs">${{ Number(user.cost_used_this_month).toFixed(4) }}</div>
                                    <div v-if="usagePercent(user.cost_used_this_month, user.monthly_cost_limit_usd) !== null" class="mt-1 w-24 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div
                                            :class="progressColor(usagePercent(user.cost_used_this_month, user.monthly_cost_limit_usd))"
                                            class="h-full rounded-full transition-all"
                                            :style="`width: ${usagePercent(user.cost_used_this_month, user.monthly_cost_limit_usd)}%`"
                                        />
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-gray-500 font-mono text-xs">
                                    {{ user.monthly_cost_limit_usd ? '$' + Number(user.monthly_cost_limit_usd).toFixed(4) : '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            class="text-xs px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors"
                                            @click="startEdit(user)"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            class="text-xs px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors disabled:opacity-50"
                                            :disabled="resetting === user.id"
                                            @click="resetUsage(user)"
                                        >
                                            {{ resetting === user.id ? 'A repor...' : 'Repor' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Inline edit row -->
                            <tr v-if="editingId === user.id" class="bg-blue-50 border-b border-gray-100">
                                <td colspan="8" class="px-4 py-3">
                                    <div class="flex items-center gap-4 flex-wrap">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-gray-600">Limite de tokens (vazio = sem limite)</label>
                                            <input
                                                v-model="editForm.monthly_token_limit"
                                                type="number"
                                                min="0"
                                                placeholder="Sem limite"
                                                class="text-sm px-3 py-1.5 rounded border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-ring w-44"
                                            />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-gray-600">Limite de custo USD (vazio = sem limite)</label>
                                            <input
                                                v-model="editForm.monthly_cost_limit_usd"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="Sem limite"
                                                class="text-sm px-3 py-1.5 rounded border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-ring w-44"
                                            />
                                        </div>
                                        <div class="flex items-end gap-2 pb-0.5">
                                            <button
                                                class="text-sm px-3 py-1.5 rounded bg-primary text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50 font-medium"
                                                :disabled="saving"
                                                @click="saveEdit(user)"
                                            >
                                                {{ saving ? 'A guardar...' : 'Guardar' }}
                                            </button>
                                            <button
                                                class="text-sm px-3 py-1.5 rounded border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors"
                                                @click="cancelEdit"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</template>
