<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

/**
 * Sanitize SDP string to be compatible with both Chrome and Firefox SDP parsers.
 *
 * Firefox and Chrome have different SDP generation formats. When Firefox generates
 * an SDP offer and Chrome needs to parse it via setRemoteDescription(), Chrome may
 * reject certain Firefox-isms. This function normalizes those differences.
 *
 * Known Firefox-isms that Chrome rejects:
 *
 * 1. a=ssrc:... cname:{uuid} — Firefox wraps UUIDs in curly braces.
 *    Chrome's SDP parser rejects curly braces as invalid syntax.
 *    Fix: strip braces → cname:uuid
 *
 * 2. a=ssrc:... msid:<stream-id> — Firefox generates msid attributes on a=ssrc: lines.
 *    Chrome may reject these when the SSRC doesn't have a corresponding cname: line,
 *    or when the msid value format doesn't match Chrome's expectations.
 *    The media-level a=msid: line (not a=ssrc:) already carries the stream/track
 *    association, so removing a=ssrc:... msid: lines is safe.
 *    Fix: remove entire a=ssrc:... msid:... lines
 *
 * 3. Firefox uses \r\n (CRLF) line endings. Chrome accepts both \r\n and \n,
 *    but the regex engine needs to handle both correctly.
 *    Fix: normalize \r\n to \n first, then work with \n consistently
 *
 * 4. Firefox may concatenate multiple a=ssrc: attributes on the same line
 *    without newline separators (e.g. "a=ssrc:... cname:... a=ssrc:... msid:...").
 *    Chrome requires each a=ssrc: attribute on its own line.
 *    Fix: insert newline between concatenated a=ssrc: entries
 */
function sanitizeSdp(sdp) {
    if (typeof sdp !== 'string') return sdp;
    const original = sdp;
    let result = sdp;

    // Normalize line endings: convert \r\n to \n for consistent processing
    result = result.replace(/\r\n/g, '\n');

    // Strip curly braces from cname:{uuid} values
    // e.g. "a=ssrc:123 cname:{uuid}" → "a=ssrc:123 cname:uuid"
    result = result.replace(/^(a=ssrc:\d+ cname:)\{([^}]+)\}$/gm, '$1$2');

    // Split concatenated a=ssrc: lines that are on the same line.
    // Firefox may produce: "a=ssrc:... cname:... a=ssrc:... msid:..."
    // Insert newline before the second a=ssrc: entry.
    result = result.replace(/(cname:\S+) a=ssrc:/g, '$1\na=ssrc:');

    // Remove ALL a=ssrc:... lines entirely.
    // Chrome's SDP parser rejects a=ssrc: lines when the SDP is received
    // via signaling (not from local RTCPeerConnection), regardless of
    // whether they contain cname: or msid: attributes.
    // The media-level a=msid: line already carries the stream/track
    // association, so a=ssrc: lines are not essential.
    result = result.replace(/^a=ssrc:\d+.*\n?/gm, '');

    // Ensure the SDP ends with a newline. Chrome's SDP parser may reject
    // the last line if it doesn't have a trailing newline.
    result = result.replace(/\n*$/, '\n');

    if (result !== original) {
        console.log('[ChatCall] sanitizeSdp: SDP изменён');
        console.log('[ChatCall] sanitizeSdp: оригинал (первые 300 символов):', original.substring(0, 300));
        console.log('[ChatCall] sanitizeSdp: результат (первые 300 символов):', result.substring(0, 300));
    }
    return result;
}

const props = defineProps({
    roomId: {
        type: Number,
        default: null,
    },
    peerUser: {
        type: Object,
        default: null,
    },
    currentUserId: {
        type: Number,
        required: true,
    },
    incomingOffer: {
        type: Object,
        default: null,
    },
    signal: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['end-call']);

const ICE_SERVERS = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
    ],
};

const callPhase = ref('idle'); // idle | pre-call | calling | ringing | connected | ended
const localStream = ref(null);
const remoteStream = ref(null);
const peerConnection = ref(null);
const isMuted = ref(false);
const isSpeakerOn = ref(true);
const callDuration = ref(0);
const endReason = ref(null); // 'rejected' | 'busy' | 'ended' | null
let durationInterval = null;
let localAudio = null;
let remoteAudio = null;
/** @type {Array<{candidate: RTCIceCandidateInit}>} */
let pendingIceCandidates = [];
let remoteDescriptionSet = false;
let hangupSent = false;
let receivedHangup = false;
/** @type {{ stop: () => void }|null} */
let ringbackTone = null;

const peerName = computed(() => props.peerUser?.display_name ?? 'Собеседник');
const peerInitial = computed(() => props.peerUser?.initial ?? '?');

const isPreCall = computed(() => callPhase.value === 'pre-call');
const isIncoming = computed(() => callPhase.value === 'ringing');
const isCalling = computed(() => callPhase.value === 'calling');
const isConnected = computed(() => callPhase.value === 'connected');
const isEnded = computed(() => callPhase.value === 'ended');
const isRinging = computed(() => callPhase.value === 'ringing');

const statusText = computed(() => {
    switch (callPhase.value) {
        case 'pre-call': return 'Готов к звонку';
        case 'calling': return 'Звоним...';
        case 'ringing': return 'Входящий звонок...';
        case 'connected': return formatDuration(callDuration.value);
        case 'ended': {
            switch (endReason.value) {
                case 'rejected': return `${peerName.value} отклонил(а) вызов`;
                case 'busy': return `${peerName.value} сейчас разговаривает`;
                case 'ended': return 'Звонок завершён';
                default: return 'Звонок завершён';
            }
        }
        default: return '';
    }
});

function formatDuration(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function startDurationTimer() {
    callDuration.value = 0;
    durationInterval = setInterval(() => {
        callDuration.value += 1;
    }, 1000);
}

function stopDurationTimer() {
    if (durationInterval) {
        clearInterval(durationInterval);
        durationInterval = null;
    }
}

/**
 * Start a ringback tone (audible ringing) for the caller while waiting
 * for the callee to answer. Uses the Web Audio API to generate a
 * European-style double-ring pattern (0.4s tone, 0.2s pause, 0.4s tone, 2s pause).
 * The tone is played through the local audio output (not sent to the peer).
 */
function startRingbackTone() {
    stopRingbackTone();

    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        let timeoutId = null;
        let stopped = false;

        function playRing() {
            if (stopped) return;

            // Create oscillator for the ring tone (440Hz sine wave)
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 440;
            gain.gain.value = 0.3;
            osc.connect(gain);
            gain.connect(audioCtx.destination);

            const now = audioCtx.currentTime;
            // First pulse: 0.4s
            osc.start(now);
            gain.gain.setValueAtTime(0.3, now);
            gain.gain.setValueAtTime(0, now + 0.4);
            // Second pulse: 0.2s pause, then 0.4s
            gain.gain.setValueAtTime(0.3, now + 0.6);
            gain.gain.setValueAtTime(0, now + 1.0);
            osc.stop(now + 1.0);

            osc.onended = () => {
                osc.disconnect();
                gain.disconnect();
            };

            // Schedule next ring cycle after 2s pause (total cycle: ~3s)
            if (!stopped) {
                timeoutId = setTimeout(playRing, 3000);
            }
        }

        // Resume AudioContext if suspended (autoplay policy)
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().then(playRing).catch(() => {});
        } else {
            playRing();
        }

        ringbackTone = {
            stop: () => {
                stopped = true;
                if (timeoutId) {
                    clearTimeout(timeoutId);
                    timeoutId = null;
                }
                audioCtx.close().catch(() => {});
            },
        };
    } catch (err) {
        console.warn('[ChatCall] Failed to start ringback tone:', err);
    }
}

/**
 * Start an incoming ringtone for the callee to indicate an incoming call.
 * Plays a melodic "Динь-дилинь-дилинь... Динь-дилинь-дилинь" pattern:
 * - "Динь": 880Hz (A5), 0.15s
 * - "дилинь": 660Hz (E5), 0.15s
 * - "дилинь": 660Hz (E5), 0.15s
 * - Pause ~1.5s, then repeat
 * Uses the Web Audio API — no external files needed.
 */
function startIncomingRingtone() {
    stopRingbackTone();

    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        let timeoutId = null;
        let stopped = false;

        function playRingCycle() {
            if (stopped) return;

            const now = audioCtx.currentTime;
            const vol = 0.2;

            // Helper to play a single tone at a given time offset
            function playTone(freq, startOffset, duration) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(vol, now + startOffset);
                gain.gain.exponentialRampToValueAtTime(0.001, now + startOffset + duration);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(now + startOffset);
                osc.stop(now + startOffset + duration);
                osc.onended = () => {
                    osc.disconnect();
                    gain.disconnect();
                };
            }

            // "Динь" — 880Hz, 0.15s
            playTone(880, 0, 0.15);
            // "дилинь" — 660Hz, 0.15s (starts 0.2s after first)
            playTone(660, 0.2, 0.15);
            // "дилинь" — 660Hz, 0.15s (starts 0.4s after first)
            playTone(660, 0.4, 0.15);

            // Schedule next cycle after ~1.6s pause (total cycle: ~2s)
            if (!stopped) {
                timeoutId = setTimeout(playRingCycle, 2000);
            }
        }

        // Resume AudioContext if suspended (autoplay policy)
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().then(playRingCycle).catch(() => {});
        } else {
            playRingCycle();
        }

        ringbackTone = {
            stop: () => {
                stopped = true;
                if (timeoutId) {
                    clearTimeout(timeoutId);
                    timeoutId = null;
                }
                audioCtx.close().catch(() => {});
            },
        };
    } catch (err) {
        console.warn('[ChatCall] Failed to start incoming ringtone:', err);
    }
}

function stopRingbackTone() {
    if (ringbackTone) {
        ringbackTone.stop();
        ringbackTone = null;
    }
}

/**
 * Play a short ascending two-tone chime ("ding-dong") to signal
 * that the call has been connected (callee answered).
 * Uses the Web Audio API — no external files needed.
 */
function playConnectedChime() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

        // First tone: 523Hz (C5) — "ding" (0.15s)
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        osc1.type = 'sine';
        osc1.frequency.value = 523;
        gain1.gain.setValueAtTime(0.25, audioCtx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.start(audioCtx.currentTime);
        osc1.stop(audioCtx.currentTime + 0.15);

        // Second tone: 659Hz (E5) — "dong" (0.25s), starts 0.15s after first
        const osc2 = audioCtx.createOscillator();
        const gain2 = audioCtx.createGain();
        osc2.type = 'sine';
        osc2.frequency.value = 659;
        gain2.gain.setValueAtTime(0.25, audioCtx.currentTime + 0.15);
        gain2.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.start(audioCtx.currentTime + 0.15);
        osc2.stop(audioCtx.currentTime + 0.4);

        // Cleanup: close context after both tones finish
        setTimeout(() => {
            audioCtx.close().catch(() => {});
        }, 500);
    } catch (err) {
        // AudioContext may not be available — non-critical
        console.warn('[ChatCall] Failed to play connected chime:', err);
    }
}

/**
 * Play a short descending two-tone chime ("dong-ding") to signal
 * that the call has ended. This is the reverse of the connected chime.
 * Uses the Web Audio API — no external files needed.
 */
function playDisconnectedChime() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

        // First tone: 659Hz (E5) — "dong" (0.25s)
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        osc1.type = 'sine';
        osc1.frequency.value = 659;
        gain1.gain.setValueAtTime(0.25, audioCtx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.start(audioCtx.currentTime);
        osc1.stop(audioCtx.currentTime + 0.25);

        // Second tone: 523Hz (C5) — "ding" (0.15s), starts 0.25s after first
        const osc2 = audioCtx.createOscillator();
        const gain2 = audioCtx.createGain();
        osc2.type = 'sine';
        osc2.frequency.value = 523;
        gain2.gain.setValueAtTime(0.25, audioCtx.currentTime + 0.25);
        gain2.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.start(audioCtx.currentTime + 0.25);
        osc2.stop(audioCtx.currentTime + 0.4);

        // Cleanup: close context after both tones finish
        setTimeout(() => {
            audioCtx.close().catch(() => {});
        }, 500);
    } catch (err) {
        // AudioContext may not be available — non-critical
        console.warn('[ChatCall] Failed to play disconnected chime:', err);
    }
}

async function getLocalStream() {
    try {
        console.log('[ChatCall] getLocalStream: запрос getUserMedia...');
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        console.log('[ChatCall] getLocalStream: поток получен, треков:', stream.getAudioTracks().length);
        localStream.value = stream;

        // Apply the pre-set mute state to the stream tracks.
        // The user may have toggled the mute button before accepting the call,
        // so we need to respect that choice when the stream is created.
        if (isMuted.value) {
            stream.getAudioTracks().forEach((track) => {
                track.enabled = false;
            });
        }

        localAudio = new Audio();
        localAudio.srcObject = stream;
        localAudio.muted = true;
        // Local audio play may fail in some browsers, but it's muted so it's non-critical
        try {
            await localAudio.play();
        } catch {
            // Ignore autoplay errors for muted local audio
        }

        return stream;
    } catch (err) {
        console.error('Failed to get local stream:', err);
        callPhase.value = 'ended';
        return null;
    }
}

function createPeerConnection() {
    console.log('[ChatCall] createPeerConnection: создание RTCPeerConnection');
    const pc = new RTCPeerConnection(ICE_SERVERS);

    pc.onicecandidate = (event) => {
        if (event.candidate && event.candidate.candidate) {
            console.log('[ChatCall] onicecandidate: отправка ICE кандидата, тип:', event.candidate.type, 'протокол:', event.candidate.protocol, 'ip:', event.candidate.address, 'порт:', event.candidate.port);
            sendSignal('ice-candidate', { candidate: event.candidate });
        } else {
            console.log('[ChatCall] onicecandidate: конец ICE кандидатов (null)');
        }
    };

    pc.ontrack = (event) => {
        console.log('[ChatCall] ontrack: получен удалённый трек, streams:', event.streams.length, 'track.kind:', event.track?.kind);
        remoteStream.value = event.streams[0];

        // Reuse the pre-created remoteAudio element (created within user gesture context
        // in startCall/acceptCall). The element was already "unlocked" for autoplay by
        // calling .play() on it during the user gesture, so setting srcObject here
        // will play automatically without being blocked by the browser autoplay policy.
        // Only create a new one if it doesn't exist yet (fallback for edge cases).
        if (!remoteAudio) {
            remoteAudio = new Audio();
        }
        remoteAudio.srcObject = event.streams[0];
        // Apply the current speaker state whenever the remote audio element is set up.
        // This ensures that if the user toggled speaker off before the remote stream
        // arrived, the mute state is applied immediately.
        remoteAudio.muted = !isSpeakerOn.value;
        // The audio element was already "unlocked" by play() in startCall/acceptCall,
        // but we call play() again as a safety measure in case the element is new.
        remoteAudio.play().catch((err) => {
            console.warn('[ChatCall] Remote audio autoplay blocked, will retry on user interaction:', err.message);
        });
    };

    pc.oniceconnectionstatechange = () => {
        console.log('[ChatCall] oniceconnectionstatechange:', pc.iceConnectionState);
        if (pc.iceConnectionState === 'connected') {
            callPhase.value = 'connected';
            stopRingbackTone();
            playConnectedChime();
            startDurationTimer();
        } else if (pc.iceConnectionState === 'disconnected' || pc.iceConnectionState === 'failed') {
            endCall();
        }
    };

    pc.onsignalingstatechange = () => {
        console.log('[ChatCall] onsignalingstatechange:', pc.signalingState);
    };

    pc.onconnectionstatechange = () => {
        console.log('[ChatCall] onconnectionstatechange:', pc.connectionState);
    };

    peerConnection.value = pc;
    return pc;
}

/**
 * Drain any ICE candidates that were queued before the remote description was set.
 * Must be called after setRemoteDescription() resolves successfully.
 */
function drainPendingIceCandidates() {
    if (!peerConnection.value) {
        console.log('[ChatCall] drainPendingIceCandidates: нет peerConnection, пропускаем');
        return;
    }

    const candidates = pendingIceCandidates;
    pendingIceCandidates = [];
    console.log('[ChatCall] drainPendingIceCandidates: добавление', candidates.length, 'отложенных ICE кандидатов');

    for (const { candidate } of candidates) {
        // Skip invalid candidates (empty candidate string)
        if (!candidate || !candidate.candidate) continue;
        peerConnection.value.addIceCandidate(new RTCIceCandidate(candidate))
            .catch((err) => {
                // Candidate may be stale by now — non-critical
                console.warn('[ChatCall] Failed to add queued ICE candidate:', err.message);
            });
    }
}

async function startCall() {
    console.log('[ChatCall] startCall: начало исходящего звонка, peer:', props.peerUser?.id, props.peerUser?.display_name);
    callPhase.value = 'calling';
    hangupSent = false;
    receivedHangup = false;

    // Pre-create remote audio element within user gesture context.
    // This is critical — browsers require a user gesture (click) to allow
    // audio playback. We create and "unlock" the element here by calling
    // .play() immediately, even though there's no srcObject yet.
    // Later, when ontrack sets srcObject, the audio will play automatically
    // because the element was already activated by the user gesture.
    if (!remoteAudio) {
        remoteAudio = new Audio();
    }
    // Apply the current speaker state to the audio element.
    // If the user toggled speaker off before remoteAudio was created,
    // this ensures the mute state is applied immediately.
    remoteAudio.muted = !isSpeakerOn.value;
    // "Unlock" the audio element for autoplay while still in user gesture context.
    // Calling play() on an Audio element without a srcObject is a no-op,
    // but it satisfies the browser's autoplay policy requirement.
    remoteAudio.play().catch(() => {
        // Ignore — no source yet, this is expected to fail silently
    });

    const stream = await getLocalStream();
    if (!stream) return;

    const pc = createPeerConnection();

    for (const track of stream.getTracks()) {
        pc.addTrack(track, stream);
    }

    try {
        console.log('[ChatCall] startCall: создание offer...');
        const offer = await pc.createOffer();
        console.log('[ChatCall] startCall: offer создан, type:', offer.type, 'sdp length:', offer.sdp?.length);
        await pc.setLocalDescription(offer);
        console.log('[ChatCall] startCall: localDescription установлен, signalingState:', pc.signalingState);
        const sanitizedSdp = sanitizeSdp(offer.sdp);
        console.log('[ChatCall] startCall: отправка offer сигнала (sdp длина:', sanitizedSdp.length, ')');
        sendSignal('offer', { sdp: sanitizedSdp });
        // Start the ringback tone so the caller hears ringing while waiting
        startRingbackTone();
    } catch (err) {
        console.error('[ChatCall] Failed to create offer:', err);
        endCall();
    }
}

async function acceptCall() {
    console.log('[ChatCall] acceptCall: принятие входящего звонка, peer:', props.peerUser?.id, props.peerUser?.display_name);
    callPhase.value = 'calling';
    hangupSent = false;
    receivedHangup = false;

    // Pre-create remote audio element within user gesture context (button click).
    // This is critical — browsers require a user gesture (click) to allow
    // audio playback. We create and "unlock" the element here by calling
    // .play() immediately, even though there's no srcObject yet.
    // Later, when ontrack sets srcObject, the audio will play automatically
    // because the element was already activated by the user gesture.
    if (!remoteAudio) {
        remoteAudio = new Audio();
    }
    // Apply the current speaker state to the audio element.
    // If the user toggled speaker off before remoteAudio was created,
    // this ensures the mute state is applied immediately.
    remoteAudio.muted = !isSpeakerOn.value;
    // "Unlock" the audio element for autoplay while still in user gesture context.
    // Calling play() on an Audio element without a srcObject is a no-op,
    // but it satisfies the browser's autoplay policy requirement.
    remoteAudio.play().catch(() => {
        // Ignore — no source yet, this is expected to fail silently
    });

    // Resume any AudioContext that might be needed by the browser
    // Some browsers use AudioContext for autoplay policy enforcement
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        await audioCtx.resume();
        audioCtx.close();
    } catch {
        // AudioContext may not be available or may fail — non-critical
    }

    const stream = await getLocalStream();
    if (!stream) return;

    const pc = createPeerConnection();

    for (const track of stream.getTracks()) {
        pc.addTrack(track, stream);
    }

    try {
        console.log('[ChatCall] acceptCall: pendingOffer type:', pendingOffer.value.type, 'sdp length:', pendingOffer.value.sdp?.length);
        
        // Sanitize the SDP from Firefox before passing to Chrome's setRemoteDescription.
        // Firefox may include a=ssrc:... msid:... lines that Chrome cannot parse.
        const cleanedSdp = sanitizeSdp(pendingOffer.value.sdp);
        
        const sanitizedOffer = new RTCSessionDescription({
            type: pendingOffer.value.type,
            sdp: cleanedSdp,
        });
        
        console.log('[ChatCall] acceptCall: установка remoteDescription (offer)...');
        console.log(sanitizedOffer.type);
        console.log(sanitizedOffer.sdp);
        await pc.setRemoteDescription(sanitizedOffer);
        console.log('[ChatCall] acceptCall: remoteDescription установлен, signalingState:', pc.signalingState);
        remoteDescriptionSet = true;
        // Drain any ICE candidates that arrived before the remote description was set
        drainPendingIceCandidates();
        console.log('[ChatCall] acceptCall: создание answer...');
        const answer = await pc.createAnswer();
        console.log('[ChatCall] acceptCall: answer создан, type:', answer.type, 'sdp length:', answer.sdp?.length);
        await pc.setLocalDescription(answer);
        console.log('[ChatCall] acceptCall: localDescription установлен, отправка answer сигнала');
        sendSignal('answer', { sdp: answer.sdp });
    } catch (err) {
        console.error('[ChatCall] Failed to accept call:', err);
        endCall();
    }
}

function rejectCall() {
    endCall();
}

function endCall() {
    console.log('[ChatCall] endCall: завершение звонка, phase:', callPhase.value, 'endReason:', endReason.value, 'hangupSent:', hangupSent, 'receivedHangup:', receivedHangup);

    // If we're in pre-call phase, just close without any signaling or history
    if (callPhase.value === 'pre-call') {
        cleanupMedia();
        emit('end-call');
        return;
    }

    // Determine call result before cleaning up state
    const wasAnswered = callPhase.value === 'connected' || callPhase.value === 'calling';
    const wasRinging = callPhase.value === 'ringing';
    const duration = callDuration.value;
    const initiatedByUs = !hangupSent && !receivedHangup;

    // Set the end reason for the status text.
    // If we already have a reason from the other peer (set in handleCallSignal),
    // keep it. Otherwise, set it based on our own state.
    if (endReason.value === null) {
        if (wasRinging) {
            endReason.value = 'rejected';
        } else {
            endReason.value = 'ended';
        }
    }

    // Notify the other peer that the call has ended, but only once
    // to avoid sending duplicate hangup signals (e.g. when both users
    // hang up simultaneously, or ICE disconnects while user also clicks end).
    if (!hangupSent) {
        hangupSent = true;
        sendSignal('hangup', { reason: wasRinging ? 'rejected' : 'ended' });
    }

    cleanupMedia();

    // Play the end-of-call chime only if the call was actually connected.
    // Skip for rejected/busy calls that never connected.
    if (wasAnswered) {
        playDisconnectedChime();
    }

    // Save call history message in the chat room.
    // Only save when WE initiated the end (clicked end/reject button, or ICE failed on our side).
    // When we receive a 'hangup' from the other peer, they already saved the history,
    // and the MessageSent broadcast will deliver it to us.
    if (initiatedByUs) {
        saveCallHistory(wasAnswered, duration);
    }

    // Decide whether to show the "ended" screen or close immediately:
    // - Show "ended" screen only when the call was rejected or the user was busy
    //   (so the user sees the reason: "отклонил(а) вызов" or "сейчас разговаривает")
    // - Close immediately in all other cases:
    //   * Caller hung up before answer (no need to show anything)
    //   * Call was connected and ended normally
    if (endReason.value === 'rejected' || endReason.value === 'busy') {
        callPhase.value = 'ended';
    } else {
        emit('end-call');
    }
}

/**
 * Clean up media resources (peer connection, streams, audio elements, timers).
 * Shared between endCall() and pre-call cancellation.
 */
function cleanupMedia() {
    if (peerConnection.value) {
        peerConnection.value.close();
        peerConnection.value = null;
    }

    if (localStream.value) {
        for (const track of localStream.value.getTracks()) {
            track.stop();
        }
        localStream.value = null;
    }

    if (localAudio) {
        localAudio.pause();
        localAudio = null;
    }

    if (remoteAudio) {
        remoteAudio.pause();
        remoteAudio = null;
    }

    remoteStream.value = null;
    pendingIceCandidates = [];
    remoteDescriptionSet = false;
    receivedHangup = false;
    stopDurationTimer();
    stopRingbackTone();
}

function saveCallHistory(wasAnswered, duration) {
    const roomId = props.roomId;
    if (!roomId) return;

    // The caller is the user who initiated the call (startCall).
    // If this is an incoming call (incomingOffer was set), the caller is the peer.
    // If this is an outgoing call, the caller is the current user.
    const callerUserId = props.incomingOffer
        ? Number(props.incomingOffer.caller_user_id)
        : Number(props.currentUserId);

    fetch('/api/call/history', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            room_id: roomId,
            caller_user_id: callerUserId,
            duration: duration,
            was_answered: wasAnswered,
        }),
    }).catch((err) => {
        console.error('[ChatCall] Failed to save call history:', err);
    });
}

function toggleMute() {
    const newMuted = !isMuted.value;
    isMuted.value = newMuted;

    if (localStream.value) {
        localStream.value.getAudioTracks().forEach((track) => {
            track.enabled = !newMuted;
        });
    }
}

function toggleSpeaker() {
    isSpeakerOn.value = !isSpeakerOn.value;
    if (remoteAudio) {
        remoteAudio.muted = !isSpeakerOn.value;
    }
}

function goBack() {
    // endCall() already emits 'end-call' for pre-call phase,
    // so we only emit here for other phases where endCall() may
    // show the "ended" screen instead of emitting.
    const wasPreCall = callPhase.value === 'pre-call';
    endCall();
    if (!wasPreCall) {
        emit('end-call');
    }
}

// Signaling
function sendSignal(type, payload) {
    if (!props.peerUser?.id) {
        console.warn('[ChatCall] sendSignal: нет peerUser, пропускаем отправку', type);
        return;
    }

    console.log('[ChatCall] sendSignal: отправка сигнала type:', type, 'target:', props.peerUser.id, 'payload keys:', Object.keys(payload));

    fetch('/api/call/signal', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            target_user_id: props.peerUser.id,
            type,
            payload,
        }),
    }).then((res) => {
        console.log('[ChatCall] sendSignal: ответ сервера', type, 'status:', res.status);
    }).catch((err) => {
        console.error('[ChatCall] Failed to send signal:', type, err);
    });
}

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const pendingOffer = ref(null);

async function handleCallSignal(data) {
    const callerId = Number(data.caller_user_id);
    console.log('[ChatCall] handleCallSignal: получен сигнал type:', data.type, 'caller:', callerId, 'current peer:', props.peerUser?.id, 'callPhase:', callPhase.value);

    if (callerId !== Number(props.peerUser?.id)) {
        console.log('[ChatCall] handleCallSignal: callerId не совпадает с peerUser, игнорируем');
        return;
    }

    switch (data.type) {
        case 'offer':
            console.log('[ChatCall] handleCallSignal: получен offer, sdp length:', data.sdp?.length);
            // If already connected, notify the caller that we're busy
            if (callPhase.value === 'connected') {
                console.log('[ChatCall] handleCallSignal: уже на связи, отправляем busy');
                sendSignal('hangup', { reason: 'busy' });
                return;
            }

            // If we're already processing this call (calling state), ignore duplicate offers.
            // This prevents a duplicate 'offer' signal from triggering a 'busy' hangup
            // when the callee has already accepted the call.
            if (callPhase.value === 'calling') {
                console.log('[ChatCall] handleCallSignal: уже в процессе звонка, игнорируем дубликат offer');
                return;
            }

            // If we already have a pending offer (ringing state), ignore duplicates.
            // This prevents overwriting the pending offer with a stale duplicate.
            if (callPhase.value === 'ringing' && pendingOffer.value) {
                console.log('[ChatCall] handleCallSignal: уже звенит, игнорируем дубликат offer');
                return;
            }

            pendingOffer.value = new RTCSessionDescription({ type: 'offer', sdp: sanitizeSdp(data.sdp) });
            callPhase.value = 'ringing';
            startIncomingRingtone();
            console.log('[ChatCall] handleCallSignal: offer принят, state -> ringing');
            break;

        case 'answer':
            console.log('[ChatCall] handleCallSignal: получен answer, sdp length:', data.sdp?.length, 'peerConnection:', !!peerConnection.value);
            if (peerConnection.value && data.sdp) {
                try {
                    console.log('[ChatCall] handleCallSignal: установка remoteDescription (answer)...');
                    await peerConnection.value.setRemoteDescription(
                        new RTCSessionDescription({ type: 'answer', sdp: sanitizeSdp(data.sdp) })
                    );
                    console.log('[ChatCall] handleCallSignal: remoteDescription (answer) установлен, signalingState:', peerConnection.value.signalingState);
                    remoteDescriptionSet = true;
                    // Drain any ICE candidates that arrived before the remote description was set
                    drainPendingIceCandidates();
                } catch (err) {
                    console.error('[ChatCall] Failed to set remote description:', err);
                }
            } else {
                console.log('[ChatCall] handleCallSignal: answer пропущен — нет peerConnection или sdp');
            }
            break;

        case 'ice-candidate':
            console.log('[ChatCall] handleCallSignal: получен ICE candidate, candidate:', data.candidate?.candidate?.substring(0, 80), 'remoteDescriptionSet:', remoteDescriptionSet);
            // Validate that the candidate has a non-empty candidate string.
            // Some browsers send end-of-candidates signals with an empty candidate
            // string, which would cause "Invalid candidate, no ':'" errors.
            if (data.candidate && data.candidate.candidate) {
                if (remoteDescriptionSet && peerConnection.value) {
                    // Remote description is already set — add candidate immediately
                    console.log('[ChatCall] handleCallSignal: добавление ICE кандидата сразу');
                    peerConnection.value.addIceCandidate(new RTCIceCandidate(data.candidate))
                        .then(() => console.log('[ChatCall] ICE кандидат добавлен успешно'))
                        .catch((err) => console.warn('[ChatCall] Failed to add ICE candidate:', err.message));
                } else {
                    // Remote description not yet set — queue the candidate for later
                    console.log('[ChatCall] handleCallSignal: ICE кандидат отложен (remoteDescription не установлен)');
                    pendingIceCandidates.push({ candidate: data.candidate });
                }
            } else {
                console.log('[ChatCall] handleCallSignal: ICE candidate пустой (end-of-candidates)');
            }
            break;

        case 'hangup':
            console.log('[ChatCall] handleCallSignal: получен hangup, reason:', data.reason);
            // Store the reason from the other peer before ending
            endReason.value = data.reason || 'ended';
            // Mark that we received a hangup (not initiated by us) so endCall()
            // skips saving call history — the other peer already saved it.
            receivedHangup = true;
            endCall();
            break;

        default:
            console.log('[ChatCall] handleCallSignal: неизвестный тип сигнала:', data.type);
            break;
    }
}

// Watch for incoming signals forwarded from ChatApp (avoids duplicate Echo subscription)
watch(() => props.signal, (newSignal) => {
    if (newSignal) {
        handleCallSignal(newSignal);
    }
}, { immediate: false });

onMounted(() => {
    console.log('[ChatCall] onMounted: компонент смонтирован, incomingOffer:', !!props.incomingOffer, 'peerUser:', props.peerUser?.id, 'roomId:', props.roomId);
    if (props.incomingOffer) {
        console.log('[ChatCall] onMounted: восстановление входящего звонка из incomingOffer, sdp length:', props.incomingOffer.sdp?.length);
        pendingOffer.value = new RTCSessionDescription({ type: 'offer', sdp: sanitizeSdp(props.incomingOffer.sdp) });
        callPhase.value = 'ringing';
        startIncomingRingtone();
    } else {
        // Outgoing call — show pre-call screen with "Начать звонок" button
        console.log('[ChatCall] onMounted: исходящий звонок, показываем экран pre-call');
        callPhase.value = 'pre-call';
    }
});

onUnmounted(() => {
    console.log('[ChatCall] onUnmounted: компонент размонтирован');
    endCall();
});
</script>

<template>
    <div class="flex h-full flex-col items-center justify-center bg-gradient-to-br from-emerald-50 via-slate-50 to-blue-100 dark:from-gray-900 dark:via-slate-900 dark:to-gray-800">
        <!-- Peer avatar -->
        <div class="mb-6">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-emerald-500 text-4xl font-bold text-white shadow-lg ring-4 ring-emerald-200 dark:ring-emerald-800">
                {{ peerInitial }}
            </div>
        </div>

        <h2 class="mb-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
            {{ peerName }}
        </h2>

        <p class="mb-8 text-sm text-gray-500 dark:text-gray-400">
            {{ statusText }}
        </p>

        <!-- Pre-call screen (outgoing, waiting for user to start) -->
        <div v-if="isPreCall" class="flex flex-col items-center gap-6">
            <button
                type="button"
                class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg transition hover:bg-emerald-600 hover:scale-105 active:scale-95"
                aria-label="Начать звонок"
                @click="startCall"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-9 w-9"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
            </button>

            <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                Начать звонок
            </span>

            <button
                type="button"
                class="mt-2 rounded-lg bg-white px-6 py-2 text-sm font-medium text-gray-700 shadow transition hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                @click="goBack"
            >
                Отмена
            </button>
        </div>

        <!-- Call controls (calling / ringing / connected) -->
        <div v-if="!isPreCall && !isEnded" class="flex items-center gap-6">
            <!-- Mute button -->
            <button
                type="button"
                class="flex h-14 w-14 items-center justify-center rounded-full transition"
                :class="isMuted
                    ? 'bg-red-500 text-white shadow-lg hover:bg-red-600'
                    : 'bg-white text-gray-600 shadow hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'"
                aria-label="Отключить микрофон"
                @click="toggleMute"
            >
                <svg
                    v-if="!isMuted"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                    <line x1="12" x2="12" y1="19" y2="22" />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <line x1="2" x2="22" y1="2" y2="22" />
                    <path d="M8.89 13.63A7 7 0 0 0 19 12v-2" />
                    <path d="M5 10v2a7 7 0 0 0 2.35 5.16" />
                    <path d="M12 19v3" />
                    <path d="M8 22h8" />
                    <path d="M12 2a3 3 0 0 0-3 3v5.5" />
                </svg>
            </button>

            <!-- End / Reject call button -->
            <button
                type="button"
                class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 text-white shadow-lg transition hover:bg-red-600"
                :class="isIncoming ? 'animate-pulse' : ''"
                aria-label="Завершить звонок"
                @click="endCall"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-7 w-7"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
            </button>

            <!-- Accept call button (incoming) -->
            <button
                v-if="isIncoming"
                type="button"
                class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg transition hover:bg-emerald-600 animate-pulse"
                aria-label="Принять звонок"
                @click="acceptCall"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-7 w-7"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
            </button>

            <!-- Speaker button -->
            <button
                type="button"
                class="flex h-14 w-14 items-center justify-center rounded-full transition"
                :class="!isSpeakerOn
                    ? 'bg-red-500 text-white shadow-lg hover:bg-red-600'
                    : 'bg-white text-gray-600 shadow hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'"
                aria-label="Динамик"
                @click="toggleSpeaker"
            >
                <svg
                    v-if="isSpeakerOn"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14" />
                    <path d="M15.54 8.46a5 5 0 0 1 0 7.07" />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                    <line x1="23" x2="17" y1="9" y2="15" />
                    <line x1="17" x2="23" y1="9" y2="15" />
                </svg>
            </button>
        </div>

        <!-- Back button (after call ended) -->
        <button
            v-if="isEnded"
            type="button"
            class="mt-8 rounded-lg bg-white px-6 py-2 text-sm font-medium text-gray-700 shadow transition hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
            @click="goBack"
        >
            Вернуться в чат
        </button>
    </div>
</template>