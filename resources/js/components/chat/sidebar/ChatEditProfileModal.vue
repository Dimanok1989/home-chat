<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    profile: {
        type: Object,
        default: null,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'save']);

const firstName = ref('');
const lastName = ref('');
const avatarFile = ref(null);
const avatarPreviewUrl = ref(null);
const removeAvatar = ref(false);

watch(() => props.show, (visible) => {
    if (visible && props.profile) {
        resetForm();
    }
});

watch(() => props.profile, () => {
    if (props.show && props.profile) {
        resetForm();
    }
});

function resetForm() {
    firstName.value = props.profile?.name ?? '';
    lastName.value = props.profile?.last_name ?? '';
    avatarFile.value = null;
    removeAvatar.value = false;
    revokePreview();
}

function revokePreview() {
    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
        avatarPreviewUrl.value = null;
    }
}

function handleAvatarSelect(event) {
    const file = event.target.files?.[0];

    event.target.value = '';

    if (!file || !file.type.startsWith('image/')) {
        return;
    }

    revokePreview();
    avatarFile.value = file;
    removeAvatar.value = false;
    avatarPreviewUrl.value = URL.createObjectURL(file);
}

function handleRemoveAvatar() {
    revokePreview();
    avatarFile.value = null;
    removeAvatar.value = true;
}

function handleClose() {
    if (props.saving) {
        return;
    }

    emit('close');
}

function handleSave() {
    const name = firstName.value.trim();

    if (!name || props.saving) {
        return;
    }

    emit('save', {
        name,
        last_name: lastName.value.trim(),
        avatar: avatarFile.value,
        remove_avatar: removeAvatar.value,
    });
}

function handleKeydown(event) {
    if (event.key === 'Escape' && !props.saving) {
        handleClose();
    }
}

const currentAvatarUrl = () => {
    if (avatarPreviewUrl.value) {
        return avatarPreviewUrl.value;
    }

    if (!removeAvatar.value && props.profile?.has_avatar && props.profile?.avatar_url) {
        return props.profile.avatar_url;
    }

    return null;
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4"
        @click.self="handleClose"
    >
        <div
            class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-800"
            role="dialog"
            aria-modal="true"
            aria-labelledby="edit-profile-title"
            @keydown="handleKeydown"
        >
            <h3
                id="edit-profile-title"
                class="text-lg font-semibold text-gray-900 dark:text-gray-100"
            >
                Редактировать профиль
            </h3>

            <div class="mt-4 flex flex-col items-center">
                <div
                    class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                >
                    <img
                        v-if="currentAvatarUrl()"
                        :src="currentAvatarUrl()"
                        alt="Аватар"
                        class="h-full w-full object-cover"
                    />
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-10 w-10 text-gray-400 dark:text-gray-500"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                </div>

                <div class="mt-3 flex gap-2">
                    <label
                        class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Изменить фото
                        <input
                            type="file"
                            accept="image/*"
                            class="hidden"
                            :disabled="saving"
                            @change="handleAvatarSelect"
                        />
                    </label>
                    <button
                        v-if="currentAvatarUrl() || (profile?.has_avatar && !removeAvatar)"
                        type="button"
                        :disabled="saving"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="handleRemoveAvatar"
                    >
                        Удалить
                    </button>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <div>
                    <label
                        for="profile-first-name"
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Имя
                    </label>
                    <input
                        id="profile-first-name"
                        v-model="firstName"
                        type="text"
                        maxlength="255"
                        :disabled="saving"
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                </div>

                <div>
                    <label
                        for="profile-last-name"
                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Фамилия
                    </label>
                    <input
                        id="profile-last-name"
                        v-model="lastName"
                        type="text"
                        maxlength="255"
                        :disabled="saving"
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                </div>
            </div>

            <p v-if="error" class="mt-3 text-sm text-red-600 dark:text-red-400">
                {{ error }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    :disabled="saving"
                    class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="handleClose"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    :disabled="saving || !firstName.trim()"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:bg-blue-300 dark:disabled:bg-blue-900"
                    @click="handleSave"
                >
                    {{ saving ? 'Сохранение...' : 'Сохранить' }}
                </button>
            </div>
        </div>
    </div>
</template>
