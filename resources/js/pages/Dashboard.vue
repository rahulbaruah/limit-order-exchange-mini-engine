<script setup lang="ts">
import { Head, useHttp, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { OrderStatus } from '@/composables/useDashboardOrders';
import { useDashboardOrders } from '@/composables/useDashboardOrders';
import { useOrderCancellation } from '@/composables/useOrderCancellation';
import type { RealtimeConnectionState } from '@/composables/useRealtimeSync';
import { useRealtimeSync } from '@/composables/useRealtimeSync';
import { dashboard } from '@/routes';
import { index as orderBookIndex } from '@/routes/order-book';
import { show as profileShow } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

type WalletBalance = {
    symbol: string;
    name: string;
    available: string;
    locked: string;
};

type BookLevel = {
    id: number;
    price: string;
    amount: string;
};

type MarketSymbol = 'BTC' | 'ETH';

type OrderBookResponse = {
    data: {
        asks: BookLevel[];
        bids: BookLevel[];
        spread: string | null;
    };
};

type ProfileResponse = {
    data: {
        usd: {
            balance: string;
            locked_balance: string;
        };
        assets: {
            symbol: string;
            amount: string;
            locked_amount: string;
        }[];
    };
};

/** These standalone GET requests carry no request body. */
type EmptyForm = Record<string, never>;

const profileRequest = useHttp<EmptyForm, ProfileResponse>({});
const orderBookRequest = useHttp<EmptyForm, OrderBookResponse>({});
const {
    orderMarketFilter,
    orderSideFilter,
    orderStatusFilter,
    orderLoading,
    orderLoadError,
    loadOrders,
    orders,
    hasOrderFilters,
} = useDashboardOrders(formatDecimal);
const {
    cancelRequest,
    orderPendingCancellation,
    cancelDialogOpen,
    cancelError,
    requestCancellation,
    confirmCancellation,
} = useOrderCancellation(loadDashboard);

const selectedMarket = ref<MarketSymbol>('BTC');
const orderBookLoading = ref(true);
const orderBookError = ref<string | null>(null);
const orderBook = computed<OrderBookResponse['data']>(
    () =>
        orderBookRequest.response?.data ?? {
            asks: [],
            bids: [],
            spread: null,
        },
);

const isLoading = ref(true);
const loadError = ref<string | null>(null);

/** Load the authenticated user's balances and their BTC/ETH order history. */
async function loadDashboard(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        await fetchDashboard();
    } catch {
        loadError.value =
            'Unable to load your dashboard data. Please try again.';
    } finally {
        isLoading.value = false;
    }
}

/** Fetch the selected market's current open price levels. */
async function loadOrderBook(): Promise<void> {
    const symbol = selectedMarket.value;

    orderBookLoading.value = true;
    orderBookError.value = null;

    try {
        await orderBookRequest.get(orderBookIndex.url({ query: { symbol } }));
    } catch {
        orderBookError.value = `Unable to load the ${symbol} order book. Please try again.`;
    } finally {
        orderBookLoading.value = false;
    }
}

function fetchDashboard(background = false): Promise<unknown> {
    return Promise.all([
        profileRequest.get(profileShow.url()),
        loadOrders(background),
        loadOrderBook(),
    ]);
}

/** Refetch in the background so a live update doesn't flash the loading state. */
async function refreshDashboard(): Promise<void> {
    try {
        await fetchDashboard(true);
        loadError.value = null;
    } catch {
        loadError.value =
            'Unable to load your dashboard data. Please try again.';
    }
}

onMounted(loadDashboard);

watch(selectedMarket, () => {
    void loadOrderBook();
});

const page = usePage();

useEcho(`user.${page.props.auth.user.id}`, '.OrderMatched', () => {
    void refreshDashboard();
});

const { connectionState, isBrowserOnline } = useRealtimeSync({
    synchronize: refreshDashboard,
});

const connectionBadges: Record<
    RealtimeConnectionState,
    { label: string; variant: 'default' | 'secondary' | 'destructive' }
> = {
    live: { label: 'Live', variant: 'default' },
    reconnecting: { label: 'Reconnecting…', variant: 'secondary' },
    offline: { label: 'Offline', variant: 'destructive' },
};

const walletSymbols = ['USD', 'BTC', 'ETH'] as const;

type WalletSymbol = (typeof walletSymbols)[number];

const assetMetadata: Record<WalletSymbol, { name: string; scale: number }> = {
    USD: { name: 'US Dollar', scale: 2 },
    BTC: { name: 'Bitcoin', scale: 8 },
    ETH: { name: 'Ethereum', scale: 8 },
};

/**
 * Group the integer part of a plain decimal string and pad its fraction to the
 * given scale, so API decimal strings render consistently.
 */
function formatDecimal(value: string, scale: number): string {
    const [whole = '0', fraction = ''] = value.split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const padded = fraction.padEnd(scale, '0').slice(0, scale);

    return scale > 0 ? `${grouped}.${padded}` : grouped;
}

/**
 * Build the wallet rows from the profile response, defaulting any asset the
 * user has no balance for to zero so USD, BTC, and ETH always render.
 */
const walletBalances = computed<WalletBalance[]>(() => {
    const profile = profileRequest.response;
    const assetsBySymbol = new Map(
        (profile?.data.assets ?? []).map((asset) => [asset.symbol, asset]),
    );

    return walletSymbols.map((symbol) => {
        const { name, scale } = assetMetadata[symbol];
        const available =
            symbol === 'USD'
                ? (profile?.data.usd.balance ?? '0')
                : (assetsBySymbol.get(symbol)?.amount ?? '0');
        const locked =
            symbol === 'USD'
                ? (profile?.data.usd.locked_balance ?? '0')
                : (assetsBySymbol.get(symbol)?.locked_amount ?? '0');

        return {
            symbol,
            name,
            available: formatDecimal(available, scale),
            locked: formatDecimal(locked, scale),
        };
    });
});

const statusVariants: Record<OrderStatus, 'default' | 'secondary' | 'outline'> =
    {
        Open: 'secondary',
        Filled: 'default',
        Cancelled: 'outline',
    };
</script>

<template>
    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <Head title="Dashboard" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold tracking-tight">
                        Orders &amp; Wallet Overview
                    </h1>
                    <Badge
                        :variant="connectionBadges[connectionState].variant"
                        aria-live="polite"
                    >
                        {{ connectionBadges[connectionState].label }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    Review your balances, reserved funds, and recent market
                    activity.
                </p>
            </div>
            <Button
                v-if="loadError"
                variant="outline"
                size="sm"
                @click="loadDashboard"
            >
                Retry
            </Button>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="balance in walletBalances"
                :key="balance.symbol"
                class="gap-4 py-4"
                :class="{ 'animate-pulse': isLoading }"
                :aria-busy="isLoading"
            >
                <CardHeader class="gap-1">
                    <CardDescription>{{ balance.name }}</CardDescription>
                    <CardTitle class="text-xl">{{ balance.symbol }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Available</span>
                        <span class="font-medium tabular-nums">{{
                            balance.available
                        }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Locked</span>
                        <span class="font-medium tabular-nums">{{
                            balance.locked
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-1">
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-1">
                            <CardTitle>Order book</CardTitle>
                            <CardDescription>
                                Resting buy and sell interest.
                            </CardDescription>
                        </div>
                        <select
                            v-model="selectedMarket"
                            aria-label="Order book market"
                            class="h-8 rounded-md border bg-background px-2 text-xs"
                        >
                            <option value="BTC">BTC</option>
                            <option value="ETH">ETH</option>
                        </select>
                    </div>
                </CardHeader>

                <CardContent class="space-y-3">
                    <div
                        v-if="orderBookLoading"
                        class="flex items-center justify-center gap-2 rounded-lg border border-dashed p-8 text-sm text-muted-foreground"
                    >
                        <Spinner />
                        Loading order book…
                    </div>

                    <div
                        v-else-if="orderBookError"
                        class="flex flex-col items-center gap-3 rounded-lg border border-dashed border-destructive/40 p-6 text-center text-sm text-destructive"
                    >
                        {{ orderBookError }}
                        <Button
                            variant="outline"
                            size="sm"
                            @click="loadOrderBook"
                        >
                            Retry
                        </Button>
                    </div>

                    <div v-else class="space-y-3">
                        <div class="overflow-hidden rounded-lg border">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b bg-muted/50 text-xs text-muted-foreground"
                                    >
                                        <th
                                            class="px-3 py-1.5 text-left font-medium"
                                        >
                                            Price
                                        </th>
                                        <th
                                            class="px-3 py-1.5 text-right font-medium"
                                        >
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-if="orderBook.asks.length === 0"
                                        class="border-b last:border-0"
                                    >
                                        <td
                                            colspan="2"
                                            class="px-3 py-2 text-center text-muted-foreground"
                                        >
                                            No open sell orders
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="level in orderBook.asks"
                                        :key="`ask-${level.id}`"
                                        class="border-b last:border-0"
                                    >
                                        <td
                                            class="px-3 py-1.5 text-rose-600 tabular-nums dark:text-rose-400"
                                        >
                                            {{ formatDecimal(level.price, 2) }}
                                        </td>
                                        <td
                                            class="px-3 py-1.5 text-right tabular-nums"
                                        >
                                            {{ formatDecimal(level.amount, 4) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p
                            class="text-center text-xs text-muted-foreground tabular-nums"
                        >
                            {{
                                orderBook.spread === null
                                    ? 'Spread unavailable'
                                    : `Spread ${formatDecimal(orderBook.spread, 2)} USD`
                            }}
                        </p>

                        <div class="overflow-hidden rounded-lg border">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b bg-muted/50 text-xs text-muted-foreground"
                                    >
                                        <th
                                            class="px-3 py-1.5 text-left font-medium"
                                        >
                                            Price
                                        </th>
                                        <th
                                            class="px-3 py-1.5 text-right font-medium"
                                        >
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-if="orderBook.bids.length === 0"
                                        class="border-b last:border-0"
                                    >
                                        <td
                                            colspan="2"
                                            class="px-3 py-2 text-center text-muted-foreground"
                                        >
                                            No open buy orders
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="level in orderBook.bids"
                                        :key="`bid-${level.id}`"
                                        class="border-b last:border-0"
                                    >
                                        <td
                                            class="px-3 py-1.5 text-emerald-600 tabular-nums dark:text-emerald-400"
                                        >
                                            {{ formatDecimal(level.price, 2) }}
                                        </td>
                                        <td
                                            class="px-3 py-1.5 text-right tabular-nums"
                                        >
                                            {{ formatDecimal(level.amount, 4) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader class="gap-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div class="space-y-1">
                            <CardTitle>Your orders</CardTitle>
                            <CardDescription>
                                Open, filled, and cancelled limit orders.
                            </CardDescription>
                        </div>
                        <Badge variant="secondary">
                            {{ orders.length }}
                            {{ orders.length === 1 ? 'order' : 'orders' }}
                        </Badge>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="orders-market-filter">Market</Label>
                            <Select v-model="orderMarketFilter">
                                <SelectTrigger
                                    id="orders-market-filter"
                                    class="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All markets
                                    </SelectItem>
                                    <SelectItem value="BTC">BTC</SelectItem>
                                    <SelectItem value="ETH">ETH</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="orders-side-filter">Side</Label>
                            <Select v-model="orderSideFilter">
                                <SelectTrigger
                                    id="orders-side-filter"
                                    class="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All sides</SelectItem
                                    >
                                    <SelectItem value="buy">Buy</SelectItem>
                                    <SelectItem value="sell">Sell</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="orders-status-filter">Status</Label>
                            <Select v-model="orderStatusFilter">
                                <SelectTrigger
                                    id="orders-status-filter"
                                    class="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="open">Open</SelectItem>
                                    <SelectItem value="filled">
                                        Filled
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        Cancelled
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="isLoading || orderLoading"
                        class="flex items-center justify-center gap-2 rounded-lg border border-dashed p-8 text-sm text-muted-foreground"
                    >
                        <Spinner />
                        Loading your orders…
                    </div>

                    <div
                        v-else-if="loadError"
                        class="rounded-lg border border-dashed border-destructive/40 p-8 text-center text-sm text-destructive"
                    >
                        {{ loadError }}
                    </div>

                    <div
                        v-else-if="orderLoadError"
                        class="flex flex-col items-center gap-3 rounded-lg border border-dashed border-destructive/40 p-8 text-center text-sm text-destructive"
                    >
                        {{ orderLoadError }}
                        <Button
                            variant="outline"
                            size="sm"
                            @click="loadOrders()"
                        >
                            Retry
                        </Button>
                    </div>

                    <div
                        v-else-if="orders.length === 0 && hasOrderFilters"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        No orders match these filters.
                    </div>

                    <div
                        v-else-if="orders.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        You haven't placed any orders yet.
                    </div>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b bg-muted/50 text-xs text-muted-foreground"
                                >
                                    <th class="px-3 py-2 text-left font-medium">
                                        Order
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Market
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Side
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Price
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Amount
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Status
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Placed
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="order in orders"
                                    :key="order.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-3 py-2 font-medium">
                                        {{ order.id }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ order.market }}
                                    </td>
                                    <td
                                        class="px-3 py-2 font-medium"
                                        :class="
                                            order.side === 'Buy'
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-rose-600 dark:text-rose-400'
                                        "
                                    >
                                        {{ order.side }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        {{ order.price }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        {{ order.amount }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <Badge
                                            :variant="
                                                statusVariants[order.status]
                                            "
                                        >
                                            {{ order.status }}
                                        </Badge>
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-muted-foreground tabular-nums"
                                    >
                                        {{ order.placedAt }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <Button
                                            v-if="order.canCancel"
                                            variant="outline"
                                            size="sm"
                                            :disabled="!isBrowserOnline"
                                            @click="requestCancellation(order)"
                                        >
                                            Cancel
                                        </Button>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                        >
                                            &mdash;
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="cancelDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Cancel this order?</DialogTitle>
                    <DialogDescription>
                        <template v-if="orderPendingCancellation">
                            Order {{ orderPendingCancellation.id }} ({{
                                orderPendingCancellation.side
                            }}
                            {{ orderPendingCancellation.amount }}
                            {{ orderPendingCancellation.market }} at
                            {{ orderPendingCancellation.price }}) will be
                            cancelled and any funds reserved for it will be
                            released.
                        </template>
                        This cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <p v-if="cancelError" class="text-sm text-destructive">
                    {{ cancelError }}
                </p>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            variant="secondary"
                            :disabled="cancelRequest.processing"
                        >
                            Keep order
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="cancelRequest.processing || !isBrowserOnline"
                        @click="confirmCancellation"
                    >
                        <Spinner v-if="cancelRequest.processing" />
                        {{
                            cancelRequest.processing
                                ? 'Cancelling…'
                                : 'Cancel order'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
