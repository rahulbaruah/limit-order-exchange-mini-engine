import { useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import type { ApiOrder, OrderRow } from '@/composables/useDashboardOrders';
import { cancel } from '@/routes/orders';

type OrderResponse = {
    data: ApiOrder;
};

export function useOrderCancellation(onCancelled: () => Promise<void>) {
    const cancelRequest = useHttp<Record<string, never>, OrderResponse>({});
    const orderPendingCancellation = ref<OrderRow | null>(null);
    const cancelDialogOpen = ref(false);
    const cancelError = ref<string | null>(null);

    function requestCancellation(order: OrderRow): void {
        cancelError.value = null;
        orderPendingCancellation.value = order;
        cancelDialogOpen.value = true;
    }

    async function confirmCancellation(): Promise<void> {
        const order = orderPendingCancellation.value;

        if (order === null) {
            return;
        }

        cancelError.value = null;
        let wasCancelled = false;

        try {
            await cancelRequest.post(cancel.url(order.numericId), {
                onSuccess: () => {
                    wasCancelled = true;
                },
                onError: () => {
                    cancelError.value =
                        'This order can no longer be cancelled. Refresh to see its current status.';
                },
            });
        } catch {
            cancelError.value =
                'Unable to cancel this order. Please try again.';
        }

        if (wasCancelled) {
            cancelDialogOpen.value = false;
            await onCancelled();
        }
    }

    watch(cancelDialogOpen, (isOpen) => {
        if (!isOpen) {
            orderPendingCancellation.value = null;
            cancelError.value = null;
        }
    });

    return {
        cancelRequest,
        orderPendingCancellation,
        cancelDialogOpen,
        cancelError,
        requestCancellation,
        confirmCancellation,
    };
}
