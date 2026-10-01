<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index as ordersIndex } from '@/routes/orders';
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
    price: string;
    amount: string;
    total: string;
};

type OrderSide = 'Buy' | 'Sell';

type OrderStatus = 'Open' | 'Filled' | 'Cancelled';

type OrderRow = {
    id: string;
    market: string;
    side: OrderSide;
    price: string;
    amount: string;
    status: OrderStatus;
    placedAt: string;
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

type ApiOrder = {
    id: number;
    symbol: string;
    side: 'buy' | 'sell';
    price: string;
    amount: string;
    status: 'open' | 'filled' | 'cancelled';
    created_at: string;
};

type OrdersResponse = {
    data: ApiOrder[];
};

/** These standalone GET requests carry no request body. */
type EmptyForm = Record<string, never>;

const profileRequest = useHttp<EmptyForm, ProfileResponse>({});
const btcOrdersRequest = useHttp<EmptyForm, OrdersResponse>({});
const ethOrdersRequest = useHttp<EmptyForm, OrdersResponse>({});

const isLoading = ref(true);
const loadError = ref<string | null>(null);

/** Load the authenticated user's balances and their BTC/ETH order history. */
async function loadDashboard(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        await Promise.all([
            profileRequest.get(profileShow.url()),
            btcOrdersRequest.get(ordersIndex.url({ query: { symbol: 'BTC' } })),
            ethOrdersRequest.get(ordersIndex.url({ query: { symbol: 'ETH' } })),
        ]);
    } catch {
        loadError.value =
            'Unable to load your balances and orders. Please try again.';
    } finally {
        isLoading.value = false;
    }
}

onMounted(loadDashboard);

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

const sideLabels: Record<ApiOrder['side'], OrderSide> = {
    buy: 'Buy',
    sell: 'Sell',
};

const statusLabels: Record<ApiOrder['status'], OrderStatus> = {
    open: 'Open',
    filled: 'Filled',
    cancelled: 'Cancelled',
};

/** Render an ISO-8601 timestamp as a compact local date and time. */
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

const asks: BookLevel[] = [
    { price: '95,012.00', amount: '0.0412', total: '3,914.49' },
    { price: '95,005.50', amount: '0.1200', total: '11,400.66' },
    { price: '94,998.00', amount: '0.0750', total: '7,124.85' },
    { price: '94,990.25', amount: '0.2300', total: '21,847.76' },
];

const bids: BookLevel[] = [
    { price: '94,980.00', amount: '0.0630', total: '5,983.74' },
    { price: '94,972.50', amount: '0.1450', total: '13,771.01' },
    { price: '94,965.00', amount: '0.0980', total: '9,306.57' },
    { price: '94,958.75', amount: '0.2100', total: '19,941.34' },
];

/**
 * Combine both markets' rows into one list, newest first to match the API
 * ordering. The order id breaks ties for orders created together.
 */
const orders = computed<OrderRow[]>(() =>
    [
        ...(btcOrdersRequest.response?.data ?? []),
        ...(ethOrdersRequest.response?.data ?? []),
    ]
        .sort((a, b) => {
            const difference =
                Date.parse(b.created_at) - Date.parse(a.created_at);

            return difference !== 0 ? difference : b.id - a.id;
        })
        .map((order) => ({
            id: `#${order.id}`,
            market: `${order.symbol}`,
            side: sideLabels[order.side],
            price: formatDecimal(order.price, 2),
            amount: formatDecimal(order.amount, 8),
            status: statusLabels[order.status],
            placedAt: formatPlacedAt(order.created_at),
        })),
);

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
                <h1 class="text-lg font-semibold tracking-tight">
                    Orders &amp; Wallet Overview
                </h1>
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
                        <div class="flex items-center gap-2">
                            <Badge variant="outline">Sample data</Badge>
                            <Badge variant="outline">BTC/USD</Badge>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-3">
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
                                    <th
                                        class="px-3 py-1.5 text-right font-medium"
                                    >
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="level in asks"
                                    :key="`ask-${level.price}`"
                                    class="border-b last:border-0"
                                >
                                    <td
                                        class="px-3 py-1.5 text-rose-600 tabular-nums dark:text-rose-400"
                                    >
                                        {{ level.price }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right tabular-nums"
                                    >
                                        {{ level.amount }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right text-muted-foreground tabular-nums"
                                    >
                                        {{ level.total }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p
                        class="text-center text-xs text-muted-foreground tabular-nums"
                    >
                        Spread 32.00 USD
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
                                    <th
                                        class="px-3 py-1.5 text-right font-medium"
                                    >
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="level in bids"
                                    :key="`bid-${level.price}`"
                                    class="border-b last:border-0"
                                >
                                    <td
                                        class="px-3 py-1.5 text-emerald-600 tabular-nums dark:text-emerald-400"
                                    >
                                        {{ level.price }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right tabular-nums"
                                    >
                                        {{ level.amount }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right text-muted-foreground tabular-nums"
                                    >
                                        {{ level.total }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-1">
                            <CardTitle>Your orders</CardTitle>
                            <CardDescription>
                                Open, filled, and cancelled limit orders.
                            </CardDescription>
                        </div>
                        <Badge variant="secondary">
                            {{ orders.length }} orders
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="isLoading"
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
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
