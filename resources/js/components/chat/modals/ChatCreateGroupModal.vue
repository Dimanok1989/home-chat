<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    creating: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'create']);

const groupName = ref('');
const searchQuery = ref('');
const searchResults = ref([]);
const selectedUsers = ref([]);
const searching = ref(false);
let searchDebounceTimer = null;

watch(() => props.show, (visible) => {
    if (!visible) {
        resetForm();
    }
});

function resetForm() {
    groupName.value = '';
    searchQuery.value = '';
    searchResults.value = [];
    selectedUsers.value = [];
    searching.value = false;
}

function isSelected(userId) {
    return selectedUsers.value.some((user) => user.id === userId);
}

function toggleUser(user) {
    if (isSelected(user.id)) {
        selectedUsers.value = selectedUsers.value.filter((item) => item.id !== user.id);
        return;
    }

    selectedUsers.value = [...selectedUsers.value, user];
}

function removeUser(userId) {
    selectedUsers.value = selectedUsers.value.filter((item) => item.id !== userId);
}

async function performSearch(query) {
    const trimmed = query.trim();

    if (!trimmed) {
        searchResults.value = [];
        searching.value = false;
        return;
    }

    searching.value = true;

    try {
        const params = new URLSearchParams({ q: trimmed });
        const response = await fetch(`/api/users/search?${params}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Не удалось выполнить поиск');
        }

        const data = await response.json();
        searchResults.value = data.users ?? [];
    } catch {
        searchResults.value = [];
    } finally {
        searching.value = false;
    }
}

watch(searchQuery, (value) => {
    clearTimeout(searchDebounceTimer);

    if (!value.trim()) {
        searchResults.value = [];
        searching.value = false;
        return;
    }

    searching.value = true;
    searchDebounceTimer = setTimeout(() => {
        performSearch(value);
    }, 300);
});

function handleSubmit() {
    const name = groupName.value.trim();

    if (!name || selectedUsers.value.length === 0 || props.creating) {
        return;
    }

    emit('create', {
        name,
        userIds: selectedUsers.value.map((user) => user.id),
    });
}

function handleKeydown(event) {
    if (event.key === 'Escape' && !props.creating) {
        emit('close');
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4"
        @click.self="!creating && emit('close')"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl dark:bg-gray-800"
            role="dialog"
            aria-modal="true"
            @keydown="handleKeydown"
        >
            <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Новая группа
                </h3>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto p-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Название группы
                    </label>
                    <input
                        v-model="groupName"
                        type="text"
                        maxlength="100"
                        placeholder="Например, Команда проекта"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Участники
                    </label>

                    <div v-if="selectedUsers.length > 0" class="mb-2 flex flex-wrap gap-2">
                        <span
                            v-for="user in selectedUsers"
                            :key="user.id"
                            class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900/50 dark:text-blue-200"
                        >
                            {{ user.name }}
                            <button
                                type="button"
                                class="font-bold"
                                :disabled="creating"
                                @click="removeUser(user.id)"
                            >
                                ×
                            </button>
                        </span>
                    </div>

                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Поиск пользователей"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />

                    <p v-if="searching" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Поиск...
                    </p>

                    <ul v-else-if="searchResults.length > 0" class="mt-2 max-h-40 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <li
                            v-for="user in searchResults"
                            :key="user.id"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                :disabled="creating"
                                @click="toggleUser(user)"
                            >
                                <span>
                                    <span class="block font-medium">{{ user.name }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ user.subtitle }}</span>
                                </span>
                                <span
                                    class="text-xs font-semibold"
                                    :class="isSelected(user.id) ? 'text-blue-600' : 'text-gray-400'"
                                >
                                    {{ isSelected(user.id) ? 'Выбран' : 'Добавить' }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>

                <p v-if="error" class="text-sm text-red-600 dark:text-red-400">
                    {{ error }}
                </p>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-200 p-5 dark:border-gray-700">
                <button
                    type="button"
                    :disabled="creating"
                    class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="emit('close')"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    :disabled="creating || !groupName.trim() || selectedUsers.length === 0"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:bg-blue-300 dark:disabled:bg-blue-900"
                    @click="handleSubmit"
                >
                    {{ creating ? 'Создание...' : 'Создать' }}
                </button>
            </div>
        </div>
    </div>
</template>
