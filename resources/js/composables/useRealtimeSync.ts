import { useConnectionStatus } from '@laravel/echo-vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type RealtimeConnectionState = 'live' | 'reconnecting' | 'offline';

/**
 * Resynchronize server state whenever the realtime connection or the browser
 * network recovers, so events missed while disconnected are not lost.
 */
export function useRealtimeSync({
    synchronize,
}: {
    synchronize: () => Promise<void>;
}): {
    connectionState: ComputedRef<RealtimeConnectionState>;
    isBrowserOnline: Ref<boolean>;
} {
    const echoStatus = useConnectionStatus();
    const isBrowserOnline = ref(navigator.onLine);
    let hasConnectedBefore = echoStatus.value === 'connected';
    let inFlightSync: Promise<void> | null = null;

    const connectionState = computed<RealtimeConnectionState>(() => {
        if (!isBrowserOnline.value) {
            return 'offline';
        }

        return echoStatus.value === 'connected' ? 'live' : 'reconnecting';
    });

    async function synchronizeOnce(): Promise<void> {
        // Browser "online" and the Echo reconnect usually fire together.
        if (inFlightSync) {
            return inFlightSync;
        }

        inFlightSync = synchronize()
            .catch((error: unknown) => {
                console.error('Failed to synchronize realtime state', error);
            })
            .finally(() => {
                inFlightSync = null;
            });

        return inFlightSync;
    }

    watch(echoStatus, (current, previous) => {
        if (current !== 'connected' || previous === 'connected') {
            return;
        }

        // The initial connect is covered by the page's own first load.
        if (hasConnectedBefore) {
            void synchronizeOnce();
        }

        hasConnectedBefore = true;
    });

    function handleOnline(): void {
        isBrowserOnline.value = true;
        void synchronizeOnce();
    }

    function handleOffline(): void {
        isBrowserOnline.value = false;
    }

    onMounted(() => {
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    });

    onUnmounted(() => {
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });

    return { connectionState, isBrowserOnline };
}
