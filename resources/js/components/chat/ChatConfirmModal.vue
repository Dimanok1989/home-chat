<script setup>
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Подтверждение',
    },
    message: {
        type: String,
        required: true,
    },
    confirmLabel: {
        type: String,
        default: 'Подтвердить',
    },
    cancelLabel: {
        type: String,
        default: 'Отмена',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'confirm']);

function handleKeydown(event) {
    if (event.key === 'Escape' && !props.loading) {
        emit('close');
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4"
        @click.self="!loading && emit('close')"
    >
        <div
            class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-800"
            role="dialog"
            aria-modal="true"
            @keydown="handleKeydown"
        >
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ title }}
            </h3>

            <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                {{ message }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    :disabled="loading"
                    class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="emit('close')"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    :disabled="loading"
                    class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:bg-red-300 dark:disabled:bg-red-900"
                    @click="emit('confirm')"
                >
                    {{ loading ? 'Удаление...' : confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
