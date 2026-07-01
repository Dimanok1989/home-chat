<script setup>
import { computed } from 'vue';
import ChatUserAvatar from './ChatUserAvatar.vue';

const props = defineProps({
    profile: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['back', 'logout', 'edit']);

const infoRows = computed(() => {
    if (!props.profile) {
        return [];
    }

    const rows = [];

    if (props.profile.username) {
        rows.push({ label: 'Username', value: `@${props.profile.username}` });
    }

    if (props.profile.email) {
        rows.push({ label: 'Email', value: props.profile.email });
    }

    return rows;
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
            <h2 class="text-sm font-semibold">Профиль</h2>
        </div>

        <div v-if="loading" class="flex flex-1 items-center justify-center p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Загрузка...</p>
        </div>

        <div v-else-if="!profile" class="flex flex-1 flex-col items-center justify-center p-4 text-center">
            <p class="text-sm text-red-600 dark:text-red-400">{{ error || 'Не удалось загрузить профиль' }}</p>
            <button
                type="button"
                class="mt-3 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="emit('back')"
            >
                Назад к чатам
            </button>
        </div>

        <div v-else class="flex flex-1 flex-col overflow-y-auto p-4">
            <div class="flex flex-col items-center text-center">
                <ChatUserAvatar
                    :avatar-url="profile.avatar_url"
                    :name="profile.display_name"
                    :initial="profile.initial"
                    size="lg"
                />

                <h3 class="mt-4 text-lg font-semibold">{{ profile.display_name }}</h3>
            </div>

            <dl v-if="infoRows.length > 0" class="mt-6 space-y-3">
                <div
                    v-for="row in infoRows"
                    :key="row.label"
                    class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700"
                >
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ row.label }}
                    </dt>
                    <dd class="mt-0.5 truncate text-sm text-gray-900 dark:text-gray-100">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>

            <div class="mt-auto space-y-2 pt-6">
                <button
                    type="button"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                    @click="emit('edit')"
                >
                    Редактировать профиль
                </button>
                <button
                    type="button"
                    class="w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950/30"
                    @click="emit('logout')"
                >
                    Выход из профиля
                </button>
            </div>
        </div>
    </div>
</template>
