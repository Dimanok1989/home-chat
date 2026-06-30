<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const MESSAGES_PAGE_SIZE = 40;

const messages = ref([]);
const onlineUsers = ref([]);
const newMessage = ref('');
const guestId = ref(
    document.querySelector('meta[name="guest-id"]')?.getAttribute('content') ?? '',
);
const sending = ref(false);
const error = ref('');
const loadingOlder = ref(false);
const hasMoreOlder = ref(true);
const initialLoadDone = ref(false);
const messagesContainer = ref(null);
const messagesEndRef = ref(null);
const fileInputRef = ref(null);
const modalText = ref('');
const pendingImage = ref(null);
const pendingPreviewUrl = ref(null);
const showSendModal = ref(false);
const viewerOpen = ref(false);
const viewerIndex = ref(0);
const contextMenu = ref(null);
const isDragging = ref(false);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const onlineCount = computed(() => onlineUsers.value.length);

const allChatImages = computed(() => {
    const images = [];

    for (const message of messages.value) {
        for (const attachment of message.attachments ?? []) {
            images.push({
                ...attachment,
                message_created_at: message.created_at,
            });
        }
    }

    return images.sort((a, b) => {
        const timeA = new Date(a.message_created_at ?? a.created_at).getTime();
        const timeB = new Date(b.message_created_at ?? b.created_at).getTime();

        return timeB - timeA;
    });
});

const currentViewerImage = computed(() => allChatImages.value[viewerIndex.value] ?? null);

function formatTime(isoString) {
    if (!isoString) {
        return '';
    }

    return new Date(isoString).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function resolveIsMine(message) {
    if (typeof message.is_mine === 'boolean') {
        return message.is_mine;
    }

    return Boolean(message.guest_id && message.guest_id === guestId.value);
}

function syncGuestId(id) {
    if (id) {
        guestId.value = id;
    }
}

function mapMessage(message) {
    return {
        ...message,
        attachments: message.attachments ?? [],
        is_mine: resolveIsMine(message),
    };
}

function appendMessage(message) {
    const existingIndex = messages.value.findIndex((item) => item.id === message.id);
    const isMine = resolveIsMine(message);

    if (existingIndex !== -1) {
        if (isMine && !messages.value[existingIndex].is_mine) {
            messages.value[existingIndex].is_mine = true;
        }

        return;
    }

    messages.value.push(mapMessage(message));
    scrollToBottom();
    bindImageLoadScroll();
}

async function fetchMessages(beforeId = null) {
    const params = new URLSearchParams({
        limit: String(MESSAGES_PAGE_SIZE),
    });

    if (beforeId !== null) {
        params.set('before_id', String(beforeId));
    }

    const response = await fetch(`/api/messages?${params}`, {
        headers: {
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Не удалось загрузить сообщения');
    }

    return response.json();
}

async function loadMessages() {
    const data = await fetchMessages();
    syncGuestId(data.guest_id);
    messages.value = data.messages.map(mapMessage);
    hasMoreOlder.value = data.has_more;
    initialLoadDone.value = true;
    scrollToBottom();
    bindImageLoadScroll();
}

async function loadOlderMessages() {
    if (loadingOlder.value || !hasMoreOlder.value || messages.value.length === 0) {
        return;
    }

    const container = messagesContainer.value;

    if (!container) {
        return;
    }

    loadingOlder.value = true;

    const previousScrollHeight = container.scrollHeight;
    const previousScrollTop = container.scrollTop;

    try {
        const data = await fetchMessages(messages.value[0].id);
        syncGuestId(data.guest_id);

        const existingIds = new Set(messages.value.map((item) => item.id));
        const olderMessages = data.messages
            .map(mapMessage)
            .filter((item) => !existingIds.has(item.id));

        if (olderMessages.length > 0) {
            messages.value = [...olderMessages, ...messages.value];

            await nextTick();

            container.scrollTop = container.scrollHeight - previousScrollHeight + previousScrollTop;
        }

        hasMoreOlder.value = data.has_more;
    } catch (err) {
        error.value = err.message ?? 'Не удалось загрузить старые сообщения';
    } finally {
        loadingOlder.value = false;
    }
}

function handleMessagesScroll() {
    const container = messagesContainer.value;

    if (!container || loadingOlder.value || !hasMoreOlder.value) {
        return;
    }

    if (container.scrollTop < 120) {
        loadOlderMessages();
    }
}

async function sendMessage() {
    const body = newMessage.value.trim();

    if (!body || sending.value) {
        return;
    }

    sending.value = true;
    error.value = '';

    try {
        const response = await fetch('/api/messages', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ body }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message ?? 'Не удалось отправить сообщение');
        }

        const data = await response.json();
        syncGuestId(data.message.guest_id);
        appendMessage(data.message);
        newMessage.value = '';
    } catch (err) {
        error.value = err.message ?? 'Ошибка отправки';
    } finally {
        sending.value = false;
    }
}

function openSendModal(file) {
    if (!file || !file.type.startsWith('image/')) {
        error.value = 'Можно прикрепить только изображение';
        return;
    }

    closeSendModal();
    pendingImage.value = file;
    pendingPreviewUrl.value = URL.createObjectURL(file);
    modalText.value = '';
    showSendModal.value = true;
    error.value = '';
}

function closeSendModal() {
    showSendModal.value = false;
    modalText.value = '';
    pendingImage.value = null;

    if (pendingPreviewUrl.value) {
        URL.revokeObjectURL(pendingPreviewUrl.value);
        pendingPreviewUrl.value = null;
    }
}

async function sendMessageWithImage() {
    if (!pendingImage.value || sending.value) {
        return;
    }

    sending.value = true;
    error.value = '';

    const formData = new FormData();
    formData.append('image', pendingImage.value);

    const body = modalText.value.trim();

    if (body) {
        formData.append('body', body);
    }

    try {
        const response = await fetch('/api/messages', {
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
            throw new Error(validationError ?? data.message ?? 'Не удалось отправить сообщение');
        }

        const data = await response.json();
        syncGuestId(data.message.guest_id);
        appendMessage(data.message);
        closeSendModal();
    } catch (err) {
        error.value = err.message ?? 'Ошибка отправки';
    } finally {
        sending.value = false;
    }
}

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function handleModalKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessageWithImage();
    }

    if (event.key === 'Escape') {
        closeSendModal();
    }
}

function handleAttachClick() {
    fileInputRef.value?.click();
}

function handleFileSelect(event) {
    const file = event.target.files?.[0];

    if (file) {
        openSendModal(file);
    }

    event.target.value = '';
}

function extractImageFromDataTransfer(dataTransfer) {
    if (!dataTransfer) {
        return null;
    }

    const items = dataTransfer.items;

    if (items) {
        for (const item of items) {
            if (item.kind === 'file' && item.type.startsWith('image/')) {
                return item.getAsFile();
            }
        }
    }

    const file = dataTransfer.files?.[0];

    if (file?.type.startsWith('image/')) {
        return file;
    }

    return null;
}

function handlePaste(event) {
    const file = extractImageFromDataTransfer(event.clipboardData);

    if (!file) {
        return;
    }

    event.preventDefault();
    openSendModal(file);
}

function handleDragOver(event) {
    if (extractImageFromDataTransfer(event.dataTransfer)) {
        event.preventDefault();
        isDragging.value = true;
    }
}

function handleDragLeave(event) {
    if (event.currentTarget === event.target) {
        isDragging.value = false;
    }
}

function handleDrop(event) {
    const file = extractImageFromDataTransfer(event.dataTransfer);

    event.preventDefault();
    isDragging.value = false;

    if (file) {
        openSendModal(file);
    }
}

function openViewer(attachment) {
    const index = allChatImages.value.findIndex((item) => item.id === attachment.id);

    if (index === -1) {
        return;
    }

    viewerIndex.value = index;
    viewerOpen.value = true;
}

function closeViewer() {
    viewerOpen.value = false;
}

function viewerGoNewer() {
    if (viewerIndex.value > 0) {
        viewerIndex.value -= 1;
    }
}

function viewerGoOlder() {
    if (viewerIndex.value < allChatImages.value.length - 1) {
        viewerIndex.value += 1;
    }
}

function handleViewerKeydown(event) {
    if (!viewerOpen.value) {
        return;
    }

    if (event.key === 'Escape') {
        closeViewer();
    }

    if (event.key === 'ArrowLeft') {
        viewerGoNewer();
    }

    if (event.key === 'ArrowRight') {
        viewerGoOlder();
    }
}

function showImageContextMenu(event, url) {
    contextMenu.value = {
        x: event.clientX,
        y: event.clientY,
        url,
    };
}

function hideContextMenu() {
    contextMenu.value = null;
}

async function copyImageToClipboard(url) {
    hideContextMenu();

    try {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error('Не удалось загрузить изображение');
        }

        const blob = await response.blob();

        await navigator.clipboard.write([
            new ClipboardItem({
                [blob.type]: blob,
            }),
        ]);
    } catch (err) {
        error.value = err.message ?? 'Не удалось скопировать изображение';
    }
}

function scrollToBottom() {
    const scroll = () => {
        if (messagesEndRef.value) {
            messagesEndRef.value.scrollIntoView({ block: 'end', behavior: 'instant' });
        } else if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    };

    nextTick(() => {
        scroll();
        requestAnimationFrame(scroll);
    });
}

function bindImageLoadScroll() {
    if (!initialLoadDone.value) {
        return;
    }

    nextTick(() => {
        const images = messagesContainer.value?.querySelectorAll('img') ?? [];

        for (const img of images) {
            if (!img.complete) {
                img.addEventListener('load', scrollToBottom, { once: true });
            }
        }
    });
}

function upsertOnlineUser(user) {
    if (!user?.id) {
        return;
    }

    const existingIndex = onlineUsers.value.findIndex((item) => item.id === user.id);

    if (existingIndex === -1) {
        onlineUsers.value.push(user);
        return;
    }

    onlineUsers.value[existingIndex] = user;
}

function removeOnlineUser(user) {
    onlineUsers.value = onlineUsers.value.filter((item) => item.id !== user.id);
}

watch(viewerOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(async () => {
    document.addEventListener('click', hideContextMenu);
    document.addEventListener('keydown', handleViewerKeydown);
    document.addEventListener('paste', handlePaste);

    try {
        await loadMessages();

        window.Echo.join('chat')
            .here((users) => {
                onlineUsers.value = users;
            })
            .joining((user) => {
                upsertOnlineUser(user);
            })
            .leaving((user) => {
                removeOnlineUser(user);
            })
            .listen('.MessageSent', (payload) => {
                appendMessage(payload);
            })
            .error((err) => {
                console.error('Echo presence error', err);
                error.value = 'Ошибка подключения к чату';
            });
    } catch (err) {
        error.value = err.message ?? 'Ошибка инициализации чата';
    }
});

onUnmounted(() => {
    document.removeEventListener('click', hideContextMenu);
    document.removeEventListener('keydown', handleViewerKeydown);
    document.removeEventListener('paste', handlePaste);
    document.body.style.overflow = '';
    closeSendModal();
    window.Echo.leave('chat');
});
</script>

<template>
    <div class="flex h-screen bg-gray-100 text-gray-900">
        <aside class="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-4 py-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Онлайн</h2>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ onlineCount }}</p>
            </div>

            <ul class="flex-1 overflow-y-auto p-3">
                <li
                    v-for="user in onlineUsers"
                    :key="user.id"
                    class="mb-2 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm"
                >
                    <span class="h-2 w-2 shrink-0 rounded-full bg-green-500"></span>
                    <span class="truncate font-medium">{{ user.name }}</span>
                </li>

                <li v-if="onlineUsers.length === 0" class="px-3 py-2 text-sm text-gray-500">
                    Пока никого нет
                </li>
            </ul>
        </aside>

        <main
            class="relative flex min-w-0 flex-1 flex-col"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <div
                v-if="isDragging"
                class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center bg-blue-500/10 backdrop-blur-[1px]"
            >
                <p class="rounded-xl bg-white px-6 py-3 text-sm font-medium text-blue-700 shadow-lg">
                    Отпустите изображение для отправки
                </p>
            </div>

            <header class="w-full shrink-0 border-b border-gray-200 bg-white px-6 py-4">
                <h1 class="text-lg font-semibold">Домашний чат</h1>
            </header>

            <div class="flex min-h-0 flex-1 w-full flex-col items-center">
                <div class="flex h-full w-full max-w-[1000px] flex-col">
            <div
                ref="messagesContainer"
                class="messages-scroll flex-1 space-y-3 overflow-y-auto px-6 py-4"
                @scroll="handleMessagesScroll"
            >
                <div
                    v-if="loadingOlder"
                    class="py-2 text-center text-xs text-gray-400"
                >
                    Загрузка...
                </div>

                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex"
                    :class="message.is_mine ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[75%] rounded-2xl px-4 py-2 shadow-sm"
                        :class="message.is_mine
                            ? 'bg-blue-600 text-white'
                            : 'border border-gray-200 bg-white text-gray-900'"
                    >
                        <p v-if="!message.is_mine" class="mb-1 text-xs font-medium text-gray-500">
                            {{ message.ip_address }}
                        </p>

                        <div
                            v-if="message.attachments?.length"
                            class="mb-2 space-y-2"
                        >
                            <img
                                v-for="attachment in message.attachments"
                                :key="attachment.id"
                                :src="attachment.url"
                                :alt="attachment.original_name"
                                class="max-h-64 cursor-pointer rounded-lg object-cover"
                                @click="openViewer(attachment)"
                                @contextmenu.prevent="showImageContextMenu($event, attachment.url)"
                            />
                        </div>

                        <p
                            v-if="message.body"
                            class="whitespace-pre-wrap break-words text-sm"
                        >
                            {{ message.body }}
                        </p>

                        <p
                            class="mt-1 text-right text-xs"
                            :class="message.is_mine ? 'text-blue-100' : 'text-gray-400'"
                        >
                            {{ formatTime(message.created_at) }}
                        </p>
                    </div>
                </div>

                <p v-if="messages.length === 0" class="text-center text-sm text-gray-500">
                    Сообщений пока нет. Напишите первым!
                </p>

                <div ref="messagesEndRef" aria-hidden="true" class="h-px shrink-0" />
            </div>

            <div class="border-t border-gray-200 bg-white px-6 py-4">
                <p v-if="error" class="mb-2 text-sm text-red-600">{{ error }}</p>

                <form class="flex gap-3" @submit.prevent="sendMessage">
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="handleFileSelect"
                    />

                    <button
                        type="button"
                        class="flex shrink-0 items-center justify-center rounded-xl border border-gray-300 px-3 py-3 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                        title="Прикрепить изображение"
                        @click="handleAttachClick"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                        </svg>
                    </button>

                    <input
                        v-model="newMessage"
                        type="text"
                        maxlength="1000"
                        placeholder="Введите сообщение..."
                        class="flex-1 rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        @keydown="handleKeydown"
                    />

                    <button
                        type="submit"
                        :disabled="sending || !newMessage.trim()"
                        class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
                    >
                        {{ sending ? '...' : 'Отправить' }}
                    </button>
                </form>
            </div>
                </div>
            </div>
        </main>

        <Teleport to="body">
            <div
                v-if="showSendModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="closeSendModal"
            >
                <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Отправить изображение</h3>

                    <img
                        v-if="pendingPreviewUrl"
                        :src="pendingPreviewUrl"
                        alt="Предпросмотр"
                        class="mb-4 max-h-80 w-full rounded-xl object-contain bg-gray-50"
                    />

                    <textarea
                        v-model="modalText"
                        rows="3"
                        maxlength="1000"
                        placeholder="Добавьте подпись (необязательно)..."
                        class="mb-4 w-full resize-none rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        @keydown="handleModalKeydown"
                    />

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            @click="closeSendModal"
                        >
                            Отмена
                        </button>
                        <button
                            type="button"
                            :disabled="sending"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:bg-blue-300"
                            @click="sendMessageWithImage"
                        >
                            {{ sending ? 'Отправка...' : 'Отправить' }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="contextMenu"
                class="fixed z-[60] min-w-[180px] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                :style="{ left: `${contextMenu.x}px`, top: `${contextMenu.y}px` }"
            >
                <button
                    type="button"
                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                    @click="copyImageToClipboard(contextMenu.url)"
                >
                    Копировать изображение
                </button>
            </div>

            <div
                v-if="viewerOpen && currentViewerImage"
                class="fixed inset-0 z-50 flex flex-col bg-black/90"
                @click.self="closeViewer"
            >
                <div class="flex items-center justify-between px-4 py-3 text-white">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1 text-sm hover:bg-white/10"
                        @click="closeViewer"
                    >
                        Закрыть
                    </button>
                    <p class="text-sm text-white/80">
                        {{ viewerIndex + 1 }} / {{ allChatImages.length }}
                    </p>
                    <div class="w-16"></div>
                </div>

                <div class="relative flex flex-1 items-center justify-center px-4 pb-4">
                    <button
                        v-if="viewerIndex > 0"
                        type="button"
                        class="absolute left-4 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
                        title="Новее"
                        @click="viewerGoNewer"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                    </button>

                    <img
                        :src="currentViewerImage.url"
                        :alt="currentViewerImage.original_name"
                        class="max-h-full max-w-full object-contain"
                        @contextmenu.prevent="showImageContextMenu($event, currentViewerImage.url)"
                    />

                    <button
                        v-if="viewerIndex < allChatImages.length - 1"
                        type="button"
                        class="absolute right-4 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
                        title="Старше"
                        @click="viewerGoOlder"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18l6-6-6-6" />
                        </svg>
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.messages-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.messages-scroll::-webkit-scrollbar {
    display: none;
}
</style>
