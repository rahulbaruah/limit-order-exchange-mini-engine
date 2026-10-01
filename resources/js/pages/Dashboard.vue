<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

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

const walletBalances: WalletBalance[] = [
    {
        symbol: 'USD',
        name: 'US Dollar',
        available: '25,430.55',
        locked: '1,425.00',
    },
    {
        symbol: 'BTC',
        name: 'Bitcoin',
        available: '0.48215000',
        locked: '0.01000000',
    },
    {
        symbol: 'ETH',
        name: 'Ethereum',
        available: '3.20500000',
        locked: '0.00000000',
    },
];

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

const orders: OrderRow[] = [
    {
        id: '#1042',
        market: 'BTC/USD',
        side: 'Buy',
        price: '94,850.00',
        amount: '0.01000000',
        status: 'Open',
        placedAt: '2026-10-01 09:12',
    },
    {
        id: '#1041',
        market: 'ETH/USD',
        side: 'Sell',
        price: '3,120.50',
        amount: '0.50000000',
        status: 'Open',
        placedAt: '2026-09-30 18:04',
    },
    {
        id: '#1040',
        market: 'BTC/USD',
        side: 'Buy',
        price: '95,200.00',
        amount: '0.02500000',
        status: 'Filled',
        placedAt: '2026-09-29 14:37',
    },
    {
        id: '#1039',
        market: 'ETH/USD',
        side: 'Buy',
        price: '3,050.00',
        amount: '1.00000000',
        status: 'Filled',
        placedAt: '2026-09-28 11:20',
    },
    {
        id: '#1038',
        market: 'BTC/USD',
        side: 'Sell',
        price: '96,100.00',
        amount: '0.01500000',
        status: 'Cancelled',
        placedAt: '2026-09-27 20:55',
    },
];

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
            <Badge variant="outline">Sample data</Badge>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="balance in walletBalances"
                :key="balance.symbol"
                class="gap-4 py-4"
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
                        <Badge variant="outline">BTC/USD</Badge>
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
                        v-if="orders.length === 0"
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
