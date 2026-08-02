<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from '../../../composables/useToast';

const emit = defineEmits(['back']);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const { message: toastMessage, visible: toastVisible, type: toastType, showError: showToastError, showInfo: showToastInfo, hide: hideToast } = useToast();

const invitations = ref([]);
const loading = ref(false);
const generating = ref(false);
const error = ref('');
const expiresInHours = ref(168); // default 7 days

async function loadInvitations() {
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch('/api/invitations', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Не удалось загрузить приглашения');
        }

        const data = await response.json();
        invitations.value = data.invitations ?? [];
    } catch (err) {
        error.value = err.message ?? 'Не удалось загрузить приглашения';
    } finally {
        loading.value = false;
    }
}

async function generateInvitation() {
    generating.value = true;
    error.value = '';

    try {
        const response = await fetch('/api/invitations', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                expires_in_hours: expiresInHours.value > 0 ? expiresInHours.value : null,
            }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const validationError = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null;
            throw new Error(validationError ?? data.message ?? 'Не удалось создать приглашение');
        }

        const data = await response.json();
        invitations.value.unshift(data.invitation);
        showToastInfo('Ссылка приглашения создана');
    } catch (err) {
        error.value = err.message ?? 'Не удалось создать приглашение';
        showToastError(error.value);
    } finally {
        generating.value = false;
    }
}

async function copyLink(url) {
    try {
        await navigator.clipboard.writeText(url);
        showToastInfo('Ссылка скопирована');
    } catch {
        showToastError('Не удалось скопировать ссылку');
    }
}

function formatExpiresAt(isoString) {
    if (!isoString) {
        return 'Без срока';
    }

    return new Date(isoString).toLocaleString([], {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatCreatedAt(isoString) {
    if (!isoString) {
        return '';
    }

    return new Date(isoString).toLocaleString([], {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

onMounted(() => {
    void loadInvitations();
});
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="flex items-center gap-2 border-b border-gray-200 px-3 py-2 dark:border-gray-700">
            <button
                type="button"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                aria-label="Назад к чатам"
                @click="emit('back')"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M19 12H5" />
                    <path d="M12 19l-7-7 7-7" />
                </svg>
            </button>
            <h2 class="text-sm font-semibold">Пригласить пользователя</h2>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <!-- Generate invitation -->
            <div class="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Создать ссылку приглашения
                </h3>

                <div class="mb-3">
                    <label for="expires" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                        Срок действия (часов)
                    </label>
                    <input
                        id="expires"
                        v-model.number="expiresInHours"
                        type="number"
                        min="1"
                        max="720"
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        placeholder="168"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        По умолчанию 7 дней (168 часов). Максимум 720 часов (30 дней).
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="generating"
                    class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                    @click="generateInvitation"
                >
                    {{ generating ? 'Создание...' : 'Сгенерировать ссылку' }}
                </button>

                <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ error }}
                </p>
            </div>

            <!-- Invitations list -->
            <div>
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Активные приглашения
                </h3>

                <div v-if="loading" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                    Загрузка...
                </div>

                <div v-else-if="invitations.length === 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                    Нет активных приглашений
                </div>

                <ul v-else class="space-y-3">
                    <li
                        v-for="invitation in invitations"
                        :key="invitation.id"
                        class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                    >
                        <div class="mb-2 min-w-0">
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ invitation.url }}
                            </p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Создана: {{ formatCreatedAt(invitation.created_at) }}
                                &middot;
                                Истекает: {{ formatExpiresAt(invitation.expires_at) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-700"
                            @click="copyLink(invitation.url)"
                        >
                            Скопировать ссылку
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="toastVisible"
                class="fixed bottom-4 left-1/2 z-50 -translate-x-1/2"
            >
                <div
                    class="rounded-xl px-4 py-3 text-sm font-medium shadow-lg"
                    :class="type === 'error'
                        ? 'bg-red-600 text-white'
                        : 'bg-gray-900 text-white dark:bg-gray-700'"
                >
                    {{ toastMessage }}
                </div>
            </div>
        </Teleport>
    </div>
</template>