<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useTheme } from '../../composables/useTheme';
import ChatEditProfileModal from './ChatEditProfileModal.vue';
import ChatSidebarProfile from './ChatSidebarProfile.vue';
import ChatUserAvatar from './ChatUserAvatar.vue';

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

const { isDark, toggleTheme } = useTheme();

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const fallbackUserName = document.querySelector('meta[name="user-name"]')?.getAttribute('content') ?? '';

const menuAvatar = computed(() => ({
    avatarUrl: profile.value?.avatar_url ?? null,
    name: profile.value?.display_name || fallbackUserName,
    initial: profile.value?.initial ?? null,
}));

const searchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);
let searchDebounceTimer = null;

const menuOpen = ref(false);
const menuRef = ref(null);
const sidebarView = ref('chats');
const profile = ref(null);
const profileLoading = ref(false);
const profileError = ref('');
const showEditProfileModal = ref(false);
const savingProfile = ref(false);
const editProfileError = ref('');

const showSearchResults = computed(() => searchQuery.value.trim().length > 0 && sidebarView.value === 'chats');

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

function roomAvatar(room) {
    if (room.type === 'direct' && room.peer) {
        return {
            avatarUrl: room.peer.avatar_url,
            name: room.peer.display_name,
            initial: room.peer.initial,
            online: Boolean(props.roomOnline[room.id]),
        };
    }

    return {
        avatarUrl: null,
        name: room.title,
        initial: null,
        online: false,
    };
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

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
}

function closeMenu() {
    menuOpen.value = false;
}

function handleToggleTheme() {
    toggleTheme();
    closeMenu();
}

function handleDocumentClick(event) {
    if (!menuRef.value?.contains(event.target)) {
        closeMenu();
    }
}

async function loadProfile({ showLoading = false } = {}) {
    if (showLoading) {
        profileLoading.value = true;
    }
    profileError.value = '';

    try {
        const response = await fetch('/api/profile', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Не удалось загрузить профиль');
        }

        const data = await response.json();
        profile.value = data.profile;
    } catch (err) {
        if (showLoading) {
            profileError.value = err.message ?? 'Не удалось загрузить профиль';
        }
    } finally {
        if (showLoading) {
            profileLoading.value = false;
        }
    }
}

async function openProfile() {
    closeMenu();
    sidebarView.value = 'profile';

    if (!profile.value) {
        await loadProfile({ showLoading: true });
    }
}

function backToChats() {
    sidebarView.value = 'chats';
}

function openEditProfile() {
    editProfileError.value = '';
    showEditProfileModal.value = true;
}

function closeEditProfile() {
    if (savingProfile.value) {
        return;
    }

    showEditProfileModal.value = false;
    editProfileError.value = '';
}

async function saveProfile({ name, last_name, avatar, remove_avatar }) {
    savingProfile.value = true;
    editProfileError.value = '';

    const formData = new FormData();
    formData.append('name', name);
    formData.append('last_name', last_name ?? '');

    if (avatar) {
        formData.append('avatar', avatar);
    }

    if (remove_avatar) {
        formData.append('remove_avatar', '1');
    }

    try {
        const response = await fetch('/api/profile', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: formData,
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const validationError = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null;
            throw new Error(validationError ?? data.message ?? 'Не удалось сохранить профиль');
        }

        const data = await response.json();
        profile.value = data.profile;
        showEditProfileModal.value = false;
    } catch (err) {
        editProfileError.value = err.message ?? 'Не удалось сохранить профиль';
    } finally {
        savingProfile.value = false;
    }
}

async function logout() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/logout';

    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = '_token';
    tokenInput.value = csrfToken;
    form.appendChild(tokenInput);

    document.body.appendChild(form);
    form.submit();
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    void loadProfile();
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
    clearTimeout(searchDebounceTimer);
});
</script>

<template>
    <aside class="flex h-full w-full shrink-0 flex-col bg-white dark:bg-gray-900 md:my-3 md:ml-2 md:h-auto md:w-80 md:rounded-lg md:border md:border-gray-100 dark:md:border-gray-800">
        <div class="border-b border-gray-200 p-3 dark:border-gray-700 md:hidden">
            <h1 class="text-lg font-semibold">{{ sidebarView === 'profile' ? 'Профиль' : 'Чаты' }}</h1>
        </div>

        <div v-if="sidebarView === 'chats'" class="border-b border-gray-200 p-3 dark:border-gray-700">
            <div class="flex gap-2">
                <div ref="menuRef" class="relative shrink-0">
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        aria-label="Меню"
                        aria-haspopup="true"
                        :aria-expanded="menuOpen"
                        @click.stop="toggleMenu"
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
                            <line x1="4" x2="20" y1="12" y2="12" />
                            <line x1="4" x2="20" y1="6" y2="6" />
                            <line x1="4" x2="20" y1="18" y2="18" />
                        </svg>
                    </button>

                    <div
                        v-if="menuOpen"
                        class="absolute left-0 z-20 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="openProfile"
                        >
                            <ChatUserAvatar
                                :avatar-url="menuAvatar.avatarUrl"
                                :name="menuAvatar.name"
                                :initial="menuAvatar.initial"
                                size="xs"
                            />
                            <span>Профиль</span>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                            :aria-label="isDark ? 'Включить светлую тему' : 'Включить тёмную тему'"
                            @click="handleToggleTheme"
                        >
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center text-gray-600 dark:text-gray-300">
                                <svg
                                    v-if="isDark"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="12" cy="12" r="4" />
                                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                                </svg>
                            </span>
                            <span>{{ isDark ? 'Светлая тема' : 'Тёмная тема' }}</span>
                        </button>
                    </div>
                </div>

                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Поиск по имени, email, username"
                    class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
            </div>

            <button
                type="button"
                class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="emit('openCreateGroup')"
            >
                Новая группа
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <ChatSidebarProfile
                v-if="sidebarView === 'profile'"
                :profile="profile"
                :loading="profileLoading"
                :error="profileError"
                @back="backToChats"
                @logout="logout"
                @edit="openEditProfile"
            />

            <template v-else>
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
                                class="flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left transition-colors"
                                :class="room.id === activeRoomId
                                    ? 'bg-blue-50 dark:bg-blue-950/40'
                                    : 'hover:bg-gray-100 dark:hover:bg-gray-800'"
                                @click="handleRoomClick(room.id)"
                            >
                                <ChatUserAvatar
                                    :avatar-url="roomAvatar(room).avatarUrl"
                                    :name="roomAvatar(room).name"
                                    :initial="roomAvatar(room).initial"
                                    :online="roomAvatar(room).online"
                                    size="md"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold">{{ room.title }}</span>
                                    <span
                                        class="mt-0.5 block truncate text-xs"
                                        :class="isEmptyRoomPreview(room)
                                            ? 'italic text-gray-500/50 dark:text-gray-400/50'
                                            : 'text-gray-500 dark:text-gray-400'"
                                    >
                                        {{ formatRoomPreview(room) }}
                                    </span>
                                </span>
                            </button>
                        </li>
                    </ul>

                    <p v-if="rooms.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                        Нет чатов
                    </p>
                </div>
            </template>
        </div>

        <Teleport to="body">
            <ChatEditProfileModal
                :show="showEditProfileModal"
                :profile="profile"
                :saving="savingProfile"
                :error="editProfileError"
                @close="closeEditProfile"
                @save="saveProfile"
            />
        </Teleport>
    </aside>
</template>
