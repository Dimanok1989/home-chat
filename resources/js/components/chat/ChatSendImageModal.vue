<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    previewUrl: {
        type: String,
        default: null,
    },
    text: {
        type: String,
        default: '',
    },
    sending: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'send', 'update:text']);

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        emit('send');
    }

    if (event.key === 'Escape') {
        emit('close');
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
            <h3 class="mb-4 text-lg font-semibold text-gray-900">Отправить изображение</h3>

            <img
                v-if="previewUrl"
                :src="previewUrl"
                alt="Предпросмотр"
                class="mb-4 max-h-80 w-full rounded-xl object-contain bg-gray-50"
            />

            <textarea
                :value="text"
                rows="3"
                maxlength="1000"
                placeholder="Добавьте подпись (необязательно)..."
                class="mb-4 w-full resize-none rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                @input="emit('update:text', $event.target.value)"
                @keydown="handleKeydown"
            />

            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="emit('close')"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    :disabled="sending"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:bg-blue-300"
                    @click="emit('send')"
                >
                    {{ sending ? 'Отправка...' : 'Отправить' }}
                </button>
            </div>
        </div>
    </div>
</template>
