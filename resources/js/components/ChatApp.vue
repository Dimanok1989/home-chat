<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ChatConfirmModal from './chat/ChatConfirmModal.vue';
import ChatCreateGroupModal from './chat/ChatCreateGroupModal.vue';
import ChatDragOverlay from './chat/ChatDragOverlay.vue';
import ChatImageContextMenu from './chat/ChatImageContextMenu.vue';
import ChatImageViewer from './chat/ChatImageViewer.vue';
import ChatMessageInput from './chat/ChatMessageInput.vue';
import ChatMessageList from './chat/ChatMessageList.vue';
import ChatSendImageModal from './chat/ChatSendImageModal.vue';
import ChatSidebar from './chat/ChatSidebar.vue';
import ChatThemeToggle from './chat/ChatThemeToggle.vue';

const MESSAGES_PAGE_SIZE = 40;

const messages = ref([]);
const rooms = ref([]);
const activeRoomId = ref(null);
const roomPresenceUsers = ref({});
const subscribedRoomIds = new Set();
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
const deleteConfirm = ref({
    show: false,
    messageId: null,
    deleting: false,
});
const isDragging = ref(false);
const showCreateGroupModal = ref(false);
const creatingGroup = ref(false);
const groupError = ref('');

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const activeRoom = computed(() => rooms.value.find((room) => room.id === activeRoomId.value) ?? null);

const roomOnline = computed(() => {
    const result = {};

    for (const room of rooms.value) {
        if (room.type !== 'direct') {
            result[room.id] = false;
            continue;
        }

        const users = roomPresenceUsers.value[room.id] ?? [];
        result[room.id] = users.some(
            (user) => Number(user.id) !== Number(currentUserId.value),
        );
    }

    return result;
});

const allChatImages = computed(() => {
    const images = [];

    for (const message of messages.value) {
        for (const attachment of message.attachments ?? []) {
            images.push({
                ...attachment,
                message_created_at: message.created_at,
                message_is_mine: message.is_mine,
                message_is_system: message.is_system ?? false,
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

function resolveExposedRef(exposed) {
    if (exposed == null) {
        return null;
    }

    return typeof exposed === 'object' && 'value' in exposed ? exposed.value : exposed;
}

function getMessagesContainer() {
    return resolveExposedRef(messageListRef.value?.messagesContainer);
}

function getMessagesEndRef() {
    return resolveExposedRef(messageListRef.value?.messagesEndRef);
}

function buildMessagePreview(message) {
    const hasAttachments = (message.attachments ?? []).length > 0;
    const body = message.body;

    if ((!body || body === '') && hasAttachments) {
        return 'Изображение';
    }

    if (!body) {
        return '';
    }

    return body.length > 80 ? `${body.slice(0, 80)}…` : body;
}

function getRoomActivityTime(room) {
    const activityAt = room.last_message_at ?? room.created_at;

    return activityAt ? new Date(activityAt).getTime() : 0;
}

function sortRooms() {
    rooms.value = [...rooms.value].sort((a, b) => {
        const timeA = getRoomActivityTime(a);
        const timeB = getRoomActivityTime(b);

        if (timeA !== timeB) {
            return timeB - timeA;
        }

        return b.id - a.id;
    });
}

function upsertRoom(room) {
    const index = rooms.value.findIndex((item) => item.id === room.id);
    const isNew = index === -1;

    if (isNew) {
        rooms.value.push(room);
    } else {
        rooms.value[index] = { ...rooms.value[index], ...room };
    }

    sortRooms();

    if (isNew) {
        subscribeRoomPresence(room.id);
    }
}

function updateRoomPreview(message) {
    const roomId = Number(message.chat_room_id);

    if (!roomId) {
        return;
    }

    const room = rooms.value.find((item) => item.id === roomId);

    if (!room) {
        return;
    }

    room.last_message = {
        preview: buildMessagePreview(message),
        user_name: message.user_name ?? null,
        created_at: message.created_at ?? null,
    };
    room.last_message_at = message.created_at ?? room.last_message_at;
    sortRooms();
}

function updateRoomAfterDelete(payload) {
    const roomId = Number(payload.chat_room_id);

    if (!roomId) {
        return;
    }

    const room = rooms.value.find((item) => item.id === roomId);

    if (!room) {
        return;
    }

    room.last_message = payload.last_message ?? null;
    room.last_message_at = payload.last_message_at ?? room.created_at ?? null;
    sortRooms();
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
    updateRoomPreview(message);
    scrollToBottom();
    bindImageLoadScroll();
}

async function fetchMessages(beforeId = null, roomId = activeRoomId.value) {
    const params = new URLSearchParams({
        limit: String(MESSAGES_PAGE_SIZE),
        room_id: String(roomId),
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

async function loadMessages(roomId = activeRoomId.value) {
    const data = await fetchMessages(null, roomId);
    messages.value = data.messages.map(mapMessage);
    hasMoreOlder.value = data.has_more;
    initialLoadDone.value = true;
    scrollToBottom();
    bindImageLoadScroll();
}

async function loadRooms() {
    const response = await fetch('/api/chat-rooms', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Не удалось загрузить список чатов');
    }

    const data = await response.json();
    rooms.value = data.rooms ?? [];
    sortRooms();

    if (!activeRoomId.value) {
        const globalRoom = rooms.value.find((room) => room.type === 'global');
        activeRoomId.value = globalRoom?.id ?? rooms.value[0]?.id ?? null;
    }

    syncRoomPresenceSubscriptions();
}

function setRoomPresence(roomId, users) {
    roomPresenceUsers.value = {
        ...roomPresenceUsers.value,
        [roomId]: users,
    };
}

function upsertRoomPresenceUser(roomId, user) {
    if (!user?.id) {
        return;
    }

    const current = roomPresenceUsers.value[roomId] ?? [];
    const existingIndex = current.findIndex((item) => item.id === user.id);

    if (existingIndex === -1) {
        setRoomPresence(roomId, [...current, user]);
        return;
    }

    const updated = [...current];
    updated[existingIndex] = user;
    setRoomPresence(roomId, updated);
}

function removeRoomPresenceUser(roomId, user) {
    if (!user?.id) {
        return;
    }

    const current = roomPresenceUsers.value[roomId] ?? [];
    setRoomPresence(roomId, current.filter((item) => item.id !== user.id));
}

function handleMessageSent(payload) {
    updateRoomPreview(payload);

    if (Number(payload.chat_room_id) === activeRoomId.value) {
        appendMessage(payload);
    }
}

function handleMessageDeleted(payload) {
    if (Number(payload.chat_room_id) === activeRoomId.value) {
        removeMessage(payload.id);
    }

    updateRoomAfterDelete(payload);
}

function subscribeRoomPresence(roomId) {
    if (!roomId || subscribedRoomIds.has(roomId)) {
        return;
    }

    subscribedRoomIds.add(roomId);

    window.Echo.join(`chat.room.${roomId}`)
        .here((users) => {
            setRoomPresence(roomId, users);
        })
        .joining((user) => {
            upsertRoomPresenceUser(roomId, user);
        })
        .leaving((user) => {
            removeRoomPresenceUser(roomId, user);
        })
        .listen('.MessageSent', handleMessageSent)
        .listen('.MessageDeleted', handleMessageDeleted)
        .error((err) => {
            console.error('Echo presence error', err);
            error.value = 'Ошибка подключения к чату';
        });
}

function syncRoomPresenceSubscriptions() {
    for (const room of rooms.value) {
        subscribeRoomPresence(room.id);
    }
}

function leaveAllRoomChannels() {
    for (const roomId of subscribedRoomIds) {
        window.Echo.leave(`chat.room.${roomId}`);
    }

    subscribedRoomIds.clear();
    roomPresenceUsers.value = {};
}

async function selectRoom(roomId) {
    if (!roomId || roomId === activeRoomId.value) {
        return;
    }

    activeRoomId.value = roomId;
    messages.value = [];
    hasMoreOlder.value = true;
    initialLoadDone.value = false;
    error.value = '';

    await loadMessages(roomId);
}

async function startDirect(userId) {
    error.value = '';

    try {
        const response = await fetch('/api/chat-rooms/direct', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: userId }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const validationError = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null;
            throw new Error(validationError ?? data.message ?? 'Не удалось открыть диалог');
        }

        const data = await response.json();
        upsertRoom(data.room);
        await selectRoom(data.room.id);
    } catch (err) {
        error.value = err.message ?? 'Не удалось открыть диалог';
    }
}

function openCreateGroupModal() {
    groupError.value = '';
    showCreateGroupModal.value = true;
}

function closeCreateGroupModal() {
    if (creatingGroup.value) {
        return;
    }

    showCreateGroupModal.value = false;
    groupError.value = '';
}

async function createGroup({ name, userIds }) {
    creatingGroup.value = true;
    groupError.value = '';

    try {
        const response = await fetch('/api/chat-rooms/group', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name, user_ids: userIds }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const validationError = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null;
            throw new Error(validationError ?? data.message ?? 'Не удалось создать группу');
        }

        const data = await response.json();
        upsertRoom(data.room);
        showCreateGroupModal.value = false;
        await selectRoom(data.room.id);
    } catch (err) {
        groupError.value = err.message ?? 'Не удалось создать группу';
    } finally {
        creatingGroup.value = false;
    }
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

    if (!body || sending.value || !activeRoomId.value) {
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
            body: JSON.stringify({
                body,
                room_id: activeRoomId.value,
            }),
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
    if (!pendingImage.value || sending.value || !activeRoomId.value) {
        return;
    }

    sending.value = true;
    error.value = '';

    const formData = new FormData();
    formData.append('image', pendingImage.value);
    formData.append('room_id', String(activeRoomId.value));

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

function showContextMenu(event, payload) {
    const message = payload?.message ?? null;

    contextMenu.value = {
        x: event.clientX,
        y: event.clientY,
        imageUrl: payload?.imageUrl ?? null,
        messageId: message?.id ?? null,
        canDelete: Boolean(message?.is_mine && !message?.is_system),
    };
}

function showContextMenuFromViewer(event, image) {
    showContextMenu(event, {
        message: {
            id: image.message_id,
            is_mine: image.message_is_mine,
            is_system: image.message_is_system,
        },
        imageUrl: image.url,
    });
}

function hideContextMenu() {
    contextMenu.value = null;
}

function removeMessage(messageId) {
    messages.value = messages.value.filter((item) => item.id !== messageId);

    if (viewerOpen.value) {
        const remainingImages = allChatImages.value;

        if (remainingImages.length === 0) {
            closeViewer();
        } else if (viewerIndex.value >= remainingImages.length) {
            viewerIndex.value = remainingImages.length - 1;
        }
    }
}

function deleteMessage(messageId) {
    hideContextMenu();

    deleteConfirm.value = {
        show: true,
        messageId,
        deleting: false,
    };
}

function closeDeleteConfirm() {
    if (deleteConfirm.value.deleting) {
        return;
    }

    deleteConfirm.value = {
        show: false,
        messageId: null,
        deleting: false,
    };
}

async function confirmDeleteMessage() {
    const messageId = deleteConfirm.value.messageId;

    if (!messageId || deleteConfirm.value.deleting) {
        return;
    }

    deleteConfirm.value.deleting = true;

    try {
        const response = await fetch(`/api/messages/${messageId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message ?? 'Не удалось удалить сообщение');
        }

        const data = await response.json();
        removeMessage(messageId);
        updateRoomAfterDelete(data);
        deleteConfirm.value = {
            show: false,
            messageId: null,
            deleting: false,
        };
    } catch (err) {
        error.value = err.message ?? 'Ошибка удаления';
        deleteConfirm.value.deleting = false;
    }
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
        const container = getMessagesContainer();

        if (container) {
            container.scrollTop = container.scrollHeight;
            return;
        }

        const messagesEndEl = getMessagesEndRef();

        if (messagesEndEl) {
            messagesEndEl.scrollIntoView({ block: 'end', behavior: 'auto' });
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

watch(viewerOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(async () => {
    document.addEventListener('click', hideContextMenu);
    document.addEventListener('keydown', handleViewerKeydown);
    document.addEventListener('paste', handlePaste);

    try {
        await loadRooms();

        if (!activeRoomId.value) {
            throw new Error('Не найдена доступная чат-комната');
        }

        await loadMessages(activeRoomId.value);
        syncRoomPresenceSubscriptions();
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
    leaveAllRoomChannels();
});
</script>

<template>
    <div class="flex h-screen bg-gray-100 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        <ChatSidebar
            :rooms="rooms"
            :active-room-id="activeRoomId"
            :room-online="roomOnline"
            @select-room="selectRoom"
            @start-direct="startDirect"
            @open-create-group="openCreateGroupModal"
        />

        <main
            class="relative flex min-w-0 flex-1 flex-col bg-gradient-to-br from-emerald-50 via-slate-50 to-blue-100 dark:from-gray-900 dark:via-slate-900 dark:to-gray-800"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <ChatDragOverlay :visible="isDragging" />

            <header class="flex w-full shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                <h1 class="text-lg font-semibold">{{ activeRoom?.title ?? 'Чат' }}</h1>
                <ChatThemeToggle />
            </header>

            <div class="flex min-h-0 flex-1 w-full flex-col items-center">
                <div class="flex h-full w-full max-w-[800px] flex-col">
                    <ChatMessageList
                        ref="messageListRef"
                        :messages="messages"
                        :loading-older="loadingOlder"
                        @scroll="handleMessagesScroll"
                        @open-viewer="openViewer"
                        @show-context-menu="showContextMenu"
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
            <ChatCreateGroupModal
                :show="showCreateGroupModal"
                :creating="creatingGroup"
                :error="groupError"
                @close="closeCreateGroupModal"
                @create="createGroup"
            />

            <ChatConfirmModal
                :show="deleteConfirm.show"
                title="Удалить сообщение"
                message="Вы уверены, что хотите удалить это сообщение? Это действие нельзя отменить."
                confirm-label="Удалить"
                :loading="deleteConfirm.deleting"
                @close="closeDeleteConfirm"
                @confirm="confirmDeleteMessage"
            />

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
                @delete="deleteMessage"
            />

            <ChatImageViewer
                :open="viewerOpen"
                :images="allChatImages"
                :index="viewerIndex"
                @close="closeViewer"
                @go-newer="viewerGoNewer"
                @go-older="viewerGoOlder"
                @show-context-menu="showContextMenuFromViewer"
            />
        </Teleport>
    </div>
</template>
