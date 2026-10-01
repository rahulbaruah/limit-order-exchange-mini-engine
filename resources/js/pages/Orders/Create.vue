<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { create, store } from '@/routes/orders';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'New Order',
                href: create(),
            },
        ],
    },
});

type Order = {
    id: number;
    symbol: string;
    side: string;
    price: string;
    amount: string;
    status: string;
};

type OrderResponse = {
    data: Order;
};

const symbolOptions = [
    { value: 'BTC', label: 'BTC' },
    { value: 'ETH', label: 'ETH' },
];

const sideOptions = [
    { value: 'buy', label: 'Buy' },
    { value: 'sell', label: 'Sell' },
];

const emptyOrder = { symbol: '', side: '', price: '', amount: '' };

const form = useHttp<typeof emptyOrder, OrderResponse>({ ...emptyOrder });

const createdOrder = ref<Order | null>(null);
const orderError = ref<string | null>(null);
const submitError = ref<string | null>(null);

function createIdempotencyKey(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

const idempotencyKey = ref(createIdempotencyKey());

async function submit(): Promise<void> {
    createdOrder.value = null;
    orderError.value = null;
    submitError.value = null;

    try {
        await form.post(store.url(), {
            headers: { 'Idempotency-Key': idempotencyKey.value },
            onSuccess: (response) => {
                createdOrder.value = response.data;
                idempotencyKey.value = createIdempotencyKey();
                form.defaults({ ...emptyOrder });
                form.reset();
            },
            onError: (errors) => {
                orderError.value =
                    errors.balance ?? errors.asset_balance ?? null;
            },
        });
    } catch {
        submitError.value = 'Unable to place the order. Please try again.';
    }
}
</script>

<template>
    <Head title="New Order" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <Card class="w-full max-w-2xl">
            <CardHeader>
                <CardTitle>Place a limit order</CardTitle>
                <CardDescription>
                    Funds for the order notional and upfront fee are reserved
                    while it rests on the book.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="symbol">Market</Label>
                        <Select v-model="form.symbol">
                            <SelectTrigger id="symbol" class="w-full">
                                <SelectValue placeholder="Select a market" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in symbolOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.symbol" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="side">Side</Label>
                        <Select v-model="form.side">
                            <SelectTrigger id="side" class="w-full">
                                <SelectValue placeholder="Select a side" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in sideOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.side" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="price">Limit price (USD)</Label>
                        <Input
                            id="price"
                            v-model="form.price"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            placeholder="95000.00"
                        />
                        <InputError :message="form.errors.price" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="amount">Amount</Label>
                        <Input
                            id="amount"
                            v-model="form.amount"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            placeholder="0.01000000"
                        />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <p
                        v-if="orderError"
                        class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                        data-test="order-error"
                    >
                        {{ orderError }}
                    </p>

                    <p
                        v-if="submitError"
                        class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                        data-test="submit-error"
                    >
                        {{ submitError }}
                    </p>

                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="create-order-button"
                    >
                        <Spinner v-if="form.processing" />
                        Place order
                    </Button>
                </form>
            </CardContent>
        </Card>

        <Card
            v-if="createdOrder"
            class="w-full max-w-2xl border-emerald-500/40 bg-emerald-500/5"
            data-test="order-created"
        >
            <CardHeader>
                <CardTitle>Order placed</CardTitle>
                <CardDescription>
                    Order #{{ createdOrder.id }} is now
                    {{ createdOrder.status }}.
                </CardDescription>
            </CardHeader>
            <CardContent
                class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3"
            >
                <div>
                    <p class="text-muted-foreground">Market</p>
                    <p class="font-medium">{{ createdOrder.symbol }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground">Side</p>
                    <p class="font-medium capitalize">
                        {{ createdOrder.side }}
                    </p>
                </div>
                <div>
                    <p class="text-muted-foreground">Status</p>
                    <p class="font-medium capitalize">
                        {{ createdOrder.status }}
                    </p>
                </div>
                <div>
                    <p class="text-muted-foreground">Price</p>
                    <p class="font-medium">{{ createdOrder.price }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground">Amount</p>
                    <p class="font-medium">{{ createdOrder.amount }}</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
