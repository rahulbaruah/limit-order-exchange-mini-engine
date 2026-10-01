import { useHttp } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { index as ordersIndex } from '@/routes/orders';

type MarketSymbol = 'BTC' | 'ETH';
type OrderSide = 'Buy' | 'Sell';
export type OrderStatus = 'Open' | 'Filled' | 'Cancelled';

export type OrderRow = {
    id: string;
    numericId: number;
    market: string;
    side: OrderSide;
    price: string;
    amount: string;
    status: OrderStatus;
    canCancel: boolean;
    placedAt: string;
};

export type ApiOrder = {
    id: number;
    symbol: string;
    side: 'buy' | 'sell';
    price: string;
    amount: string;
    status: 'open' | 'filled' | 'cancelled';
    created_at: string;
};

type OrderMarketFilter = 'all' | MarketSymbol;
type OrderSideFilter = 'all' | ApiOrder['side'];
type OrderStatusFilter = 'all' | ApiOrder['status'];

type OrdersResponse = {
    data: ApiOrder[];
};

const sideLabels: Record<ApiOrder['side'], OrderSide> = {
    buy: 'Buy',
    sell: 'Sell',
};

const statusLabels: Record<ApiOrder['status'], OrderStatus> = {
    open: 'Open',
    filled: 'Filled',
    cancelled: 'Cancelled',
};

function formatPlacedAt(value: string): string {
    const placedAt = new Date(value);

    if (Number.isNaN(placedAt.getTime())) {
        return '—';
    }

    const pad = (part: number): string => part.toString().padStart(2, '0');

    return `${placedAt.getFullYear()}-${pad(placedAt.getMonth() + 1)}-${pad(
        placedAt.getDate(),
    )} ${pad(placedAt.getHours())}:${pad(placedAt.getMinutes())}`;
}

export function useDashboardOrders(
    formatDecimal: (value: string, scale: number) => string,
) {
    const btcOrdersRequest = useHttp<Record<string, never>, OrdersResponse>({});
    const ethOrdersRequest = useHttp<Record<string, never>, OrdersResponse>({});
    const btcOrders = ref<ApiOrder[]>([]);
    const ethOrders = ref<ApiOrder[]>([]);

    const orderMarketFilter = ref<OrderMarketFilter>('all');
    const orderSideFilter = ref<OrderSideFilter>('all');
    const orderStatusFilter = ref<OrderStatusFilter>('all');
    const orderLoading = ref(true);
    const orderLoadError = ref<string | null>(null);
    let orderRequestVersion = 0;

    async function loadOrders(background = false): Promise<void> {
        const requestVersion = ++orderRequestVersion;
        const symbols: MarketSymbol[] =
            orderMarketFilter.value === 'all'
                ? ['BTC', 'ETH']
                : [orderMarketFilter.value];
        const filters = {
            ...(orderSideFilter.value === 'all'
                ? {}
                : { side: orderSideFilter.value }),
            ...(orderStatusFilter.value === 'all'
                ? {}
                : { status: orderStatusFilter.value }),
        };

        btcOrdersRequest.cancel();
        ethOrdersRequest.cancel();
        orderLoading.value = !background;
        orderLoadError.value = null;

        try {
            const results = await Promise.all(
                symbols.map(async (symbol) => {
                    const request =
                        symbol === 'BTC' ? btcOrdersRequest : ethOrdersRequest;
                    const response = await request.get(
                        ordersIndex.url({ query: { symbol, ...filters } }),
                    );

                    return { symbol, orders: response.data };
                }),
            );

            if (requestVersion !== orderRequestVersion) {
                return;
            }

            btcOrders.value =
                results.find((result) => result.symbol === 'BTC')?.orders ?? [];
            ethOrders.value =
                results.find((result) => result.symbol === 'ETH')?.orders ?? [];
        } catch {
            if (requestVersion === orderRequestVersion) {
                orderLoadError.value =
                    'Unable to load your orders. Please try again.';
            }
        } finally {
            if (requestVersion === orderRequestVersion) {
                orderLoading.value = false;
            }
        }
    }

    const orders = computed<OrderRow[]>(() =>
        [...btcOrders.value, ...ethOrders.value]
            .sort((a, b) => {
                const difference =
                    Date.parse(b.created_at) - Date.parse(a.created_at);

                return difference !== 0 ? difference : b.id - a.id;
            })
            .map((order) => ({
                id: `#${order.id}`,
                numericId: order.id,
                market: `${order.symbol}`,
                side: sideLabels[order.side],
                price: formatDecimal(order.price, 2),
                amount: formatDecimal(order.amount, 8),
                status: statusLabels[order.status],
                canCancel: order.status === 'open',
                placedAt: formatPlacedAt(order.created_at),
            })),
    );

    const hasOrderFilters = computed(
        () =>
            orderMarketFilter.value !== 'all' ||
            orderSideFilter.value !== 'all' ||
            orderStatusFilter.value !== 'all',
    );

    watch([orderMarketFilter, orderSideFilter, orderStatusFilter], () => {
        void loadOrders();
    });

    return {
        orderMarketFilter,
        orderSideFilter,
        orderStatusFilter,
        orderLoading,
        orderLoadError,
        loadOrders,
        orders,
        hasOrderFilters,
    };
}
