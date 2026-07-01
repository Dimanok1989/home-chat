<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useMediaQuery } from '../composables/useMediaQuery';
import { useToast } from '../composables/useToast';
import { buildMessagePreview } from '../utils/chatFormat';
import ChatConfirmModal from './chat/modals/ChatConfirmModal.vue';
import ChatCreateGroupModal from './chat/modals/ChatCreateGroupModal.vue';
import ChatDragOverlay from './chat/images/ChatDragOverlay.vue';
import ChatImageContextMenu from './chat/images/ChatImageContextMenu.vue';
import ChatRoomContextMenu from './chat/sidebar/ChatRoomContextMenu.vue';
import ChatImageViewer from './chat/images/ChatImageViewer.vue';
import ChatMessageInput from './chat/messages/ChatMessageInput.vue';
import ChatMessageList from './chat/messages/ChatMessageList.vue';
import ChatSendImageModal from './chat/images/ChatSendImageModal.vue';
import ChatSidebar from './chat/sidebar/ChatSidebar.vue';
import ChatSpinner from './chat/shared/ChatSpinner.vue';
import ChatToast from './chat/shared/ChatToast.vue';

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
const appReady = ref(false);
const messageListRef = ref(null);
const messageInputRef = ref(null);
const modalText = ref('');
const pendingImage = ref(null);
const pendingPreviewUrl = ref(null);
const showSendModal = ref(false);
const viewerOpen = ref(false);
const viewerIndex = ref(0);
const contextMenu = ref(null);
const roomContextMenu = ref(null);
const replyingTo = ref(null);
const highlightedMessageId = ref(null);
let highlightTimeout = null;
const deleteConfirm = ref({
    show: false,
    messageId: null,
    deleting: false,
});
const roomDeleteConfirm = ref({
    show: false,
    roomId: null,
    deleting: false,
});
const isDragging = ref(false);
const showCreateGroupModal = ref(false);
const creatingGroup = ref(false);
const groupError = ref('');
const isMobile = useMediaQuery('(max-width: 767px)');
const mobileChatOpen = ref(false);
const pendingDirectUser = ref(null);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const { message: toastMessage, visible: toastVisible, showError: showToastError, hide: hideToast } = useToast();

function parseApiError(data, fallback) {
    if (data?.errors) {
        const messages = Object.values(data.errors).flat();

        if (messages.length > 0) {
            return messages.join(' ');
        }
    }

    return data?.message ?? fallback;
}

function notifyError(message, fallback = 'Произошла ошибка') {
    const text = message ?? fallback;
    showToastError(text);
    error.value = text;
}

const CHAT_BASE_PATH = '/chat';

function getRoomIdFromUrl() {
    const match = window.location.pathname.match(/^\/chat\/(\d+)\/?$/);

    if (!match) {
        return null;
    }

    const id = Number(match[1]);

    return Number.isInteger(id) && id > 0 ? id : null;
}

function getChatUrl(roomId = null) {
    if (roomId) {
        return `${CHAT_BASE_PATH}/${roomId}`;
    }

    return CHAT_BASE_PATH;
}

function syncUrlWithRoom(roomId, { replace } = {}) {
    const url = getChatUrl(roomId);
    const state = { roomId: roomId ?? null };
    const shouldReplace = replace ?? getRoomIdFromUrl() !== null;

    if (shouldReplace) {
        history.replaceState(state, '', url);
    } else {
        history.pushState(state, '', url);
    }
}

function isKnownRoom(roomId) {
    return rooms.value.some((room) => room.id === roomId);
}

const activeRoom = computed(() => {
    if (activeRoomId.value) {
        return rooms.value.find((room) => room.id === activeRoomId.value) ?? null;
    }

    if (pendingDirectUser.value) {
        return {
            type: 'direct',
            title: pendingDirectUser.value.display_name,
            peer: pendingDirectUser.value,
        };
    }

    return null;
});

const isChatOpen = computed(() => Boolean(activeRoomId.value || pendingDirectUser.value));

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

function clearReply() {
    replyingTo.value = null;
}

function cancelReply() {
    clearReply();
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
        user_id: message.user_id ?? null,
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
    try {
        const data = await fetchMessages(null, roomId);
        messages.value = data.messages.map(mapMessage);
        hasMoreOlder.value = data.has_more;
    } catch (err) {
        notifyError(err.message, 'Не удалось загрузить сообщения');
    } finally {
        await nextTick();
        initialLoadDone.value = true;
    }
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
    const roomId = Number(payload.chat_room_id);

    if (!rooms.value.some((item) => item.id === roomId)) {
        void loadRooms().then(() => {
            updateRoomPreview(payload);

            if (roomId === activeRoomId.value) {
                appendMessage(payload);
            }
        });

        return;
    }

    updateRoomPreview(payload);

    if (roomId === activeRoomId.value) {
        appendMessage(payload);
    }
}

function handleMessageDeleted(payload) {
    if (Number(payload.chat_room_id) === activeRoomId.value) {
        removeMessage(payload.id);
    }

    updateRoomAfterDelete(payload);
}

function handleProfileUpdated(payload) {
    const userId = Number(payload.id);

    if (!userId) {
        return;
    }

    const peer = {
        id: userId,
        display_name: payload.display_name,
        has_avatar: payload.has_avatar,
        avatar_url: payload.avatar_url ?? null,
        initial: payload.initial,
    };

    for (const room of rooms.value) {
        if (room.type === 'direct' && Number(room.peer?.id) === userId) {
            room.peer = peer;
            room.title = payload.display_name;
        }

        if (Number(room.last_message?.user_id) === userId) {
            room.last_message = {
                ...room.last_message,
                user_name: payload.name,
            };
        }
    }

    for (const roomId of Object.keys(roomPresenceUsers.value)) {
        const users = roomPresenceUsers.value[roomId] ?? [];
        const index = users.findIndex((item) => Number(item.id) === userId);

        if (index !== -1) {
            const updated = [...users];
            updated[index] = { ...updated[index], name: payload.name };
            setRoomPresence(Number(roomId), updated);
        }
    }

    if (Number(activeRoomId.value) > 0) {
        for (const message of messages.value) {
            if (Number(message.user_id) === userId) {
                message.user_name = payload.name;
            }
        }
    }
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
        .listen('.ProfileUpdated', handleProfileUpdated)
        .error((err) => {
            console.error('Echo presence error', err);
            notifyError('Ошибка подключения к чату');
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

function closeActiveRoom() {
    activeRoomId.value = null;
    pendingDirectUser.value = null;
    messages.value = [];
    hasMoreOlder.value = true;
    initialLoadDone.value = true;
    error.value = '';
    mobileChatOpen.value = false;
    clearReply();
}

async function openRoom(roomId, { updateUrl = false, replaceUrl = false } = {}) {
    if (!roomId || !isKnownRoom(roomId)) {
        return false;
    }

    pendingDirectUser.value = null;
    const isSameRoom = roomId === activeRoomId.value;

    if (!isSameRoom) {
        activeRoomId.value = roomId;
        messages.value = [];
        hasMoreOlder.value = true;
        initialLoadDone.value = false;
        error.value = '';
        clearReply();

        await loadMessages(roomId);
    }

    if (updateUrl) {
        syncUrlWithRoom(roomId, { replace: replaceUrl || getRoomIdFromUrl() !== null });
    }

    if (isMobile.value) {
        mobileChatOpen.value = true;
    }

    return true;
}

function goBackToMenu() {
    if (isMobile.value && mobileChatOpen.value) {
        if (pendingDirectUser.value || getRoomIdFromUrl() === null) {
            closeActiveRoom();
            syncUrlWithRoom(null, { replace: true });
            return;
        }

        history.back();
    }
}

function handlePopState() {
    const roomId = getRoomIdFromUrl();

    if (roomId && isKnownRoom(roomId)) {
        void openRoom(roomId);
        return;
    }

    closeActiveRoom();
}

async function selectRoom(roomId) {
    if (!roomId) {
        return;
    }

    const isSameRoom = roomId === activeRoomId.value;

    if (isSameRoom && (!isMobile.value || mobileChatOpen.value)) {
        return;
    }

    const opened = await openRoom(roomId, { updateUrl: true });

    if (!opened) {
        syncUrlWithRoom(null, { replace: true });
    }
}

function mapSearchUserToPeer(user) {
    const name = user.name ?? '';

    return {
        id: user.id,
        display_name: name,
        has_avatar: false,
        avatar_url: null,
        initial: name ? name.charAt(0).toUpperCase() : '?',
    };
}

function openPendingDirectChat(user) {
    pendingDirectUser.value = mapSearchUserToPeer(user);
    activeRoomId.value = null;
    messages.value = [];
    hasMoreOlder.value = false;
    initialLoadDone.value = true;
    error.value = '';
    clearReply();
    syncUrlWithRoom(null, { replace: true });

    if (isMobile.value) {
        mobileChatOpen.value = true;
    }
}

function activateRoomFromSend(data) {
    if (!data.room) {
        return;
    }

    pendingDirectUser.value = null;
    upsertRoom(data.room);
    activeRoomId.value = data.room.id;
    syncUrlWithRoom(data.room.id, { replace: true });
}

async function startDirect(user) {
    error.value = '';
    const userId = Number(user.id);

    const existing = rooms.value.find(
        (room) => room.type === 'direct' && Number(room.peer?.id) === userId,
    );

    if (existing) {
        await selectRoom(existing.id);
        return;
    }

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

        if (response.ok) {
            const data = await response.json();
            upsertRoom(data.room);
            await selectRoom(data.room.id);
            return;
        }

        if (response.status === 404) {
            openPendingDirectChat(user);
            return;
        }

        const data = await response.json().catch(() => ({}));
        const validationError = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : null;
        throw new Error(validationError ?? data.message ?? 'Не удалось открыть диалог');
    } catch (err) {
        notifyError(err.message, 'Не удалось открыть диалог');
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
        const message = err.message ?? 'Не удалось создать группу';
        groupError.value = message;
        notifyError(message, 'Не удалось создать группу');
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

    const distanceFromBottom = container.scrollHeight - container.scrollTop;

    try {
        const data = await fetchMessages(messages.value[0].id);

        const existingIds = new Set(messages.value.map((item) => item.id));
        const olderMessages = data.messages
            .map(mapMessage)
            .filter((item) => !existingIds.has(item.id));

        if (olderMessages.length > 0) {
            messages.value = [...olderMessages, ...messages.value];

            await nextTick();

            container.scrollTop = container.scrollHeight - distanceFromBottom;
        }

        hasMoreOlder.value = data.has_more;
    } catch (err) {
        notifyError(err.message, 'Не удалось загрузить старые сообщения');
    } finally {
        loadingOlder.value = false;
    }
}

function handleMessagesScroll() {
    const container = getMessagesContainer();

    if (!container || loadingOlder.value || !hasMoreOlder.value) {
        return;
    }

    const distanceFromTop = container.scrollHeight - container.scrollTop - container.clientHeight;

    if (distanceFromTop < 120) {
        loadOlderMessages();
    }
}

async function sendMessage() {
    const body = newMessage.value.trim();

    if (!body || sending.value || !isChatOpen.value) {
        return;
    }

    sending.value = true;
    error.value = '';

    const payload = {
        body,
        reply_to_id: replyingTo.value?.id ?? null,
    };

    if (activeRoomId.value) {
        payload.room_id = activeRoomId.value;
    } else if (pendingDirectUser.value) {
        payload.user_id = pendingDirectUser.value.id;
    }

    try {
        const response = await fetch('/api/messages', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(parseApiError(data, 'Не удалось отправить сообщение'));
        }

        const data = await response.json();
        activateRoomFromSend(data);
        appendMessage(data.message);
        newMessage.value = '';
        clearReply();
    } catch (err) {
        notifyError(err.message, 'Ошибка отправки');
    } finally {
        sending.value = false;
    }
}

function openSendModal(file) {
    if (!file || !file.type.startsWith('image/')) {
        notifyError('Можно прикрепить только изображение');
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
    if (!pendingImage.value || sending.value || !isChatOpen.value) {
        return;
    }

    sending.value = true;
    error.value = '';

    const formData = new FormData();
    formData.append('image', pendingImage.value);

    if (activeRoomId.value) {
        formData.append('room_id', String(activeRoomId.value));
    } else if (pendingDirectUser.value) {
        formData.append('user_id', String(pendingDirectUser.value.id));
    }

    const body = modalText.value.trim();

    if (body) {
        formData.append('body', body);
    }

    if (replyingTo.value?.id) {
        formData.append('reply_to_id', String(replyingTo.value.id));
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
            throw new Error(parseApiError(data, 'Не удалось отправить сообщение'));
        }

        const data = await response.json();
        activateRoomFromSend(data);
        appendMessage(data.message);
        closeSendModal();
        clearReply();
    } catch (err) {
        notifyError(err.message, 'Ошибка отправки');
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
    hideRoomContextMenu();

    const message = payload?.message ?? null;

    contextMenu.value = {
        x: event.clientX,
        y: event.clientY,
        imageUrl: payload?.imageUrl ?? null,
        messageId: message?.id ?? null,
        message,
        canDelete: Boolean(message?.is_mine && !message?.is_system),
        canReply: Boolean(message && !message?.is_system),
    };
}

function showContextMenuFromViewer(event, image) {
    const message = messages.value.find((item) => item.id === image.message_id);

    showContextMenu(event, {
        message: message ?? {
            id: image.message_id,
            is_mine: image.message_is_mine,
            is_system: image.message_is_system,
            user_name: null,
            body: null,
            attachments: [{ url: image.url }],
        },
        imageUrl: image.url,
    });
}

function replyToMessage(message) {
    hideAllContextMenus();
    replyingTo.value = message;

    nextTick(() => {
        messageInputRef.value?.focusInput?.();
    });
}

async function ensureMessageLoaded(messageId) {
    const id = Number(messageId);
    let attempts = 0;

    while (!messages.value.some((item) => item.id === id) && hasMoreOlder.value && attempts < 50) {
        attempts += 1;
        await loadOlderMessages();
    }

    return messages.value.some((item) => item.id === id);
}

function highlightMessage(messageId) {
    highlightedMessageId.value = Number(messageId);

    if (highlightTimeout) {
        clearTimeout(highlightTimeout);
    }

    highlightTimeout = setTimeout(() => {
        highlightedMessageId.value = null;
        highlightTimeout = null;
    }, 2000);
}

async function scrollToMessage(messageId) {
    const id = Number(messageId);

    if (!id) {
        return;
    }

    const found = messages.value.some((item) => item.id === id)
        ? true
        : await ensureMessageLoaded(id);

    if (!found) {
        return;
    }

    await nextTick();

    const container = getMessagesContainer();
    const element = container?.querySelector(`[data-message-id="${id}"]`);

    if (!element) {
        return;
    }

    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlightMessage(id);
}

function hideContextMenu() {
    contextMenu.value = null;
}

function showRoomContextMenu(event, room) {
    if (room?.type !== 'direct') {
        return;
    }

    hideContextMenu();

    roomContextMenu.value = {
        x: event.clientX,
        y: event.clientY,
        roomId: room.id,
        canDelete: true,
    };
}

function hideRoomContextMenu() {
    roomContextMenu.value = null;
}

function hideAllContextMenus() {
    hideContextMenu();
    hideRoomContextMenu();
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
    hideAllContextMenus();

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
        notifyError(err.message, 'Ошибка удаления');
        deleteConfirm.value.deleting = false;
    }
}

async function copyImageToClipboard(url) {
    hideAllContextMenus();

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
        notifyError(err.message, 'Не удалось скопировать изображение');
    }
}

function deleteRoom(roomId) {
    hideAllContextMenus();

    roomDeleteConfirm.value = {
        show: true,
        roomId,
        deleting: false,
    };
}

function closeRoomDeleteConfirm() {
    if (roomDeleteConfirm.value.deleting) {
        return;
    }

    roomDeleteConfirm.value = {
        show: false,
        roomId: null,
        deleting: false,
    };
}

function removeRoomFromList(roomId) {
    rooms.value = rooms.value.filter((item) => item.id !== roomId);
}

async function confirmDeleteRoom() {
    const roomId = roomDeleteConfirm.value.roomId;

    if (!roomId || roomDeleteConfirm.value.deleting) {
        return;
    }

    roomDeleteConfirm.value.deleting = true;

    try {
        const response = await fetch(`/api/chat-rooms/${roomId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message ?? 'Не удалось удалить чат');
        }

        const data = await response.json();
        removeRoomFromList(data.id);

        if (activeRoomId.value === data.id) {
            closeActiveRoom();
            syncUrlWithRoom(null, { replace: true });
        }

        roomDeleteConfirm.value = {
            show: false,
            roomId: null,
            deleting: false,
        };
    } catch (err) {
        notifyError(err.message, 'Ошибка удаления');
        roomDeleteConfirm.value.deleting = false;
    }
}

watch(viewerOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

watch(isMobile, (mobile) => {
    if (mobile) {
        mobileChatOpen.value = Boolean(activeRoomId.value || pendingDirectUser.value);
        syncUrlWithRoom(activeRoomId.value, { replace: true });
    }
});

onMounted(async () => {
    document.addEventListener('click', hideAllContextMenus);
    document.addEventListener('keydown', handleViewerKeydown);
    document.addEventListener('paste', handlePaste);
    window.addEventListener('popstate', handlePopState);

    try {
        await loadRooms();

        const urlRoomId = getRoomIdFromUrl();

        if (urlRoomId) {
            const opened = await openRoom(urlRoomId, { updateUrl: true, replaceUrl: true });

            if (!opened) {
                syncUrlWithRoom(null, { replace: true });
                initialLoadDone.value = true;
            }
        } else {
            syncUrlWithRoom(null, { replace: true });
            initialLoadDone.value = true;
        }
    } catch (err) {
        notifyError(err.message, 'Ошибка инициализации чата');
    } finally {
        appReady.value = true;
    }
});

onUnmounted(() => {
    document.removeEventListener('click', hideAllContextMenus);
    document.removeEventListener('keydown', handleViewerKeydown);
    document.removeEventListener('paste', handlePaste);
    window.removeEventListener('popstate', handlePopState);
    document.body.style.overflow = '';

    if (highlightTimeout) {
        clearTimeout(highlightTimeout);
    }

    closeSendModal();
    leaveAllRoomChannels();
});
</script>

<template>
    <div
        v-if="!appReady"
        class="flex h-dvh items-center justify-center bg-gradient-to-br from-emerald-50 via-slate-50 to-blue-100 dark:from-gray-900 dark:via-slate-900 dark:to-gray-800"
    >
        <ChatSpinner size="lg" />
    </div>

    <div
        v-else
        class="flex h-dvh bg-gradient-to-br from-emerald-50 via-slate-50 to-blue-100 text-gray-900 dark:from-gray-900 dark:via-slate-900 dark:to-gray-800 dark:text-gray-100 md:bg-gradient-to-br"
    >
        <ChatSidebar
            v-show="!isMobile || !mobileChatOpen"
            class="min-h-0 min-w-0 flex-1 md:flex-none"
            :rooms="rooms"
            :active-room-id="activeRoomId"
            :room-online="roomOnline"
            @select-room="selectRoom"
            @start-direct="startDirect"
            @open-create-group="openCreateGroupModal"
            @show-room-context-menu="showRoomContextMenu"
        />

        <main
            v-show="!isMobile || mobileChatOpen"
            class="relative flex min-h-0 min-w-0 flex-1 flex-col md:relative"
            :class="isMobile && mobileChatOpen
                ? 'fixed inset-0 z-10 bg-gradient-to-br from-emerald-50 via-slate-50 to-blue-100 dark:from-gray-900 dark:via-slate-900 dark:to-gray-800'
                : ''"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <ChatDragOverlay :visible="isDragging" />

            <header
                v-if="isChatOpen"
                class="z-20 mx-auto flex w-full max-w-250 shrink-0 items-center border-b border-gray-100 bg-white px-4 py-4 pt-[max(1rem,env(safe-area-inset-top))] dark:border-gray-800 dark:bg-gray-900 md:rounded-b-lg md:border-l md:border-r md:px-6 md:pt-4"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <button
                        v-if="isMobile && mobileChatOpen"
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-600 transition hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        aria-label="Назад к списку чатов"
                        @click="goBackToMenu"
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
                    <h1 class="truncate text-lg font-semibold">{{ activeRoom?.title }}</h1>
                </div>
            </header>

            <div
                v-if="!isChatOpen"
                class="flex flex-1 items-center justify-center px-4 text-center text-gray-500 dark:text-gray-400"
            >
                <p class="text-sm">Выберите чат из списка{{ isMobile ? '' : ' слева' }}</p>
            </div>

            <div v-else class="relative flex min-h-0 flex-1 w-full flex-col md:items-center">
                <div
                    v-if="!initialLoadDone"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 dark:bg-gray-900/70"
                >
                    <ChatSpinner size="md" />
                </div>

                <div class="flex min-h-0 flex-1 w-full flex-col md:h-full md:max-w-200">
                    <ChatMessageList
                        ref="messageListRef"
                        :messages="messages"
                        :loading-older="loadingOlder"
                        :loading="!initialLoadDone"
                        :highlighted-message-id="highlightedMessageId"
                        @scroll="handleMessagesScroll"
                        @open-viewer="openViewer"
                        @show-context-menu="showContextMenu"
                        @scroll-to-message="scrollToMessage"
                    />
                </div>

                <div class="w-full shrink-0 md:max-w-200">
                    <ChatMessageInput
                        ref="messageInputRef"
                        v-model="newMessage"
                        :sending="sending"
                        :error="error"
                        :pinned="isMobile && mobileChatOpen"
                        :replying-to="replyingTo"
                        @send="sendMessage"
                        @file-select="handleFileSelect"
                        @cancel-reply="cancelReply"
                    />
                </div>
            </div>
        </main>

        <Teleport to="body">
            <ChatToast
                :message="toastMessage"
                :visible="toastVisible"
                @close="hideToast"
            />

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

            <ChatConfirmModal
                :show="roomDeleteConfirm.show"
                title="Удалить чат"
                message="Чат будет удалён только у вас. Собеседник по-прежнему увидит переписку. История сообщений до удаления не будет показана, если вы снова начнёте беседу."
                confirm-label="Удалить"
                :loading="roomDeleteConfirm.deleting"
                @close="closeRoomDeleteConfirm"
                @confirm="confirmDeleteRoom"
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
                @reply="replyToMessage"
            />

            <ChatRoomContextMenu
                :context-menu="roomContextMenu"
                @delete="deleteRoom"
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
