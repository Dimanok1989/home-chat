<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ChatDragOverlay from './chat/ChatDragOverlay.vue';
import ChatImageContextMenu from './chat/ChatImageContextMenu.vue';
import ChatImageViewer from './chat/ChatImageViewer.vue';
import ChatMessageInput from './chat/ChatMessageInput.vue';
import ChatMessageList from './chat/ChatMessageList.vue';
import ChatSendImageModal from './chat/ChatSendImageModal.vue';
import ChatSidebar from './chat/ChatSidebar.vue';

const MESSAGES_PAGE_SIZE = 40;

const messages = ref([]);
const onlineUsers = ref([]);
const newMessage = ref('');
const currentUserId = ref(
    Number(document.querySelector('meta[name="user-id"]')?.getAttribute('content') ?? 0) || null,
);
const sending = ref(false);
const error = ref('');
const loadingOlder = ref(false);
const hasMoreOlder = ref(true);
const initialLoadDone = ref(false);
const messageListRef = ref(null);
const modalText = ref('');
const pendingImage = ref(null);
const pendingPreviewUrl = ref(null);
const showSendModal = ref(false);
const viewerOpen = ref(false);
const viewerIndex = ref(0);
const contextMenu = ref(null);
const isDragging = ref(false);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

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

function resolveIsMine(message) {
    if (typeof message.is_mine === 'boolean') {
        return message.is_mine;
    }

    return Boolean(
        currentUserId.value
        && message.user_id
        && Number(message.user_id) === currentUserId.value,
    );
}

function mapMessage(message) {
    return {
        ...message,
        attachments: message.attachments ?? [],
        is_mine: resolveIsMine(message),
    };
}

function getMessagesContainer() {
    return messageListRef.value?.messagesContainer?.value ?? null;
}

function getMessagesEndRef() {
    return messageListRef.value?.messagesEndRef?.value ?? null;
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

    const container = getMessagesContainer();

    if (!container) {
        return;
    }

    loadingOlder.value = true;

    const previousScrollHeight = container.scrollHeight;
    const previousScrollTop = container.scrollTop;

    try {
        const data = await fetchMessages(messages.value[0].id);

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
    const container = getMessagesContainer();

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
        appendMessage(data.message);
        closeSendModal();
    } catch (err) {
        error.value = err.message ?? 'Ошибка отправки';
    } finally {
        sending.value = false;
    }
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
        const messagesEndRef = getMessagesEndRef();

        if (messagesEndRef) {
            messagesEndRef.scrollIntoView({ block: 'end', behavior: 'instant' });
        } else {
            const container = getMessagesContainer();

            if (container) {
                container.scrollTop = container.scrollHeight;
            }
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
        const container = getMessagesContainer();
        const images = container?.querySelectorAll('img') ?? [];

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
        <ChatSidebar :online-users="onlineUsers" />

        <main
            class="relative flex min-w-0 flex-1 flex-col"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <ChatDragOverlay :visible="isDragging" />

            <header class="w-full shrink-0 border-b border-gray-200 bg-white px-6 py-4">
                <h1 class="text-lg font-semibold">Домашний чат</h1>
            </header>

            <div class="flex min-h-0 flex-1 w-full flex-col items-center">
                <div class="flex h-full w-full max-w-[1000px] flex-col">
                    <ChatMessageList
                        ref="messageListRef"
                        :messages="messages"
                        :loading-older="loadingOlder"
                        @scroll="handleMessagesScroll"
                        @open-viewer="openViewer"
                        @show-context-menu="showImageContextMenu"
                    />

                    <ChatMessageInput
                        v-model="newMessage"
                        :sending="sending"
                        :error="error"
                        @send="sendMessage"
                        @file-select="handleFileSelect"
                    />
                </div>
            </div>
        </main>

        <Teleport to="body">
            <ChatSendImageModal
                :show="showSendModal"
                :preview-url="pendingPreviewUrl"
                :text="modalText"
                :sending="sending"
                @close="closeSendModal"
                @send="sendMessageWithImage"
                @update:text="modalText = $event"
            />

            <ChatImageContextMenu
                :context-menu="contextMenu"
                @copy="copyImageToClipboard"
            />

            <ChatImageViewer
                :open="viewerOpen"
                :images="allChatImages"
                :index="viewerIndex"
                @close="closeViewer"
                @go-newer="viewerGoNewer"
                @go-older="viewerGoOlder"
                @show-context-menu="showImageContextMenu"
            />
        </Teleport>
    </div>
</template>
