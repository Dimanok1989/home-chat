<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
    activeRoomId: {
        type: Number,
        default: null,
    },
    roomOnline: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['selectRoom', 'startDirect', 'openCreateGroup']);

const searchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);
let searchDebounceTimer = null;

const showSearchResults = computed(() => searchQuery.value.trim().length > 0);

function formatRoomPreview(room) {
    const lastMessage = room.last_message;

    if (!lastMessage?.preview) {
        return 'Нет сообщений';
    }

    if (room.type === 'global' || room.type === 'group') {
        const author = lastMessage.user_name ? `${lastMessage.user_name}: ` : '';

        return `${author}${lastMessage.preview}`;
    }

    return lastMessage.preview;
}

function isEmptyRoomPreview(room) {
    return !room.last_message?.preview;
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

function handleUserClick(user) {
    searchQuery.value = '';
    searchResults.value = [];
    emit('startDirect', user.id);
}

function handleRoomClick(roomId) {
    emit('selectRoom', roomId);
}
</script>

<template>
    <aside class="flex w-72 shrink-0 flex-col border-r border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 p-3 dark:border-gray-700">
            <input
                v-model="searchQuery"
                type="search"
                placeholder="Поиск по имени, email, username"
                class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            />

            <button
                type="button"
                class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="emit('openCreateGroup')"
            >
                Новая группа
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div v-if="showSearchResults" class="p-2">
                <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Пользователи
                </p>

                <p v-if="searching" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    Поиск...
                </p>

                <ul v-else-if="searchResults.length > 0">
                    <li
                        v-for="user in searchResults"
                        :key="user.id"
                        class="mb-1"
                    >
                        <button
                            type="button"
                            class="w-full rounded-lg px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800"
                            @click="handleUserClick(user)"
                        >
                            <span class="block truncate text-sm font-medium">{{ user.name }}</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ user.subtitle }}
                            </span>
                        </button>
                    </li>
                </ul>

                <p v-else class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    Никого не найдено
                </p>
            </div>

            <div v-else class="p-2">
                <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Чаты
                </p>

                <ul>
                    <li
                        v-for="room in rooms"
                        :key="room.id"
                        class="mb-1"
                    >
                        <button
                            type="button"
                            class="w-full rounded-lg px-3 py-2 text-left transition-colors"
                            :class="room.id === activeRoomId
                                ? 'bg-blue-50 dark:bg-blue-950/40'
                                : 'hover:bg-gray-100 dark:hover:bg-gray-800'"
                            @click="handleRoomClick(room.id)"
                        >
                            <span class="flex items-center gap-2">
                                <span
                                    v-if="room.type === 'direct' && roomOnline[room.id]"
                                    class="h-2 w-2 shrink-0 rounded-full bg-green-500"
                                    title="В сети"
                                ></span>
                                <span class="truncate text-sm font-semibold">{{ room.title }}</span>
                            </span>
                            <span
                                class="mt-0.5 block truncate text-xs"
                                :class="isEmptyRoomPreview(room)
                                    ? 'italic text-gray-500/50 dark:text-gray-400/50'
                                    : 'text-gray-500 dark:text-gray-400'"
                            >
                                {{ formatRoomPreview(room) }}
                            </span>
                        </button>
                    </li>
                </ul>

                <p v-if="rooms.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    Нет чатов
                </p>
            </div>
        </div>
    </aside>
</template>
