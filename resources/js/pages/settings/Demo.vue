<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/demo';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Demo balances',
                href: edit(),
            },
        ],
    },
});

type AssetBalance = {
    symbol: string;
    amount: string;
    locked_amount: string;
};

type DemoForm = {
    balance: string;
    locked_balance: string;
    assets: AssetBalance[];
};

const props = defineProps<{
    balances: {
        usd: {
            balance: string;
            locked_balance: string;
        };
        assets: AssetBalance[];
    };
    symbols: string[];
}>();

const form = useForm<DemoForm>({
    balance: props.balances.usd.balance,
    locked_balance: props.balances.usd.locked_balance,
    assets: props.symbols.map((symbol) => {
        const asset = props.balances.assets.find(
            (entry) => entry.symbol === symbol,
        );

        return {
            symbol,
            amount: asset?.amount ?? '0',
            locked_amount: asset?.locked_amount ?? '0',
        };
    }),
});

const submitError = ref<string | null>(null);

/** Read a nested validation error without fighting Inertia's deep error keys. */
function assetError(
    index: number,
    field: 'amount' | 'locked_amount',
): string | undefined {
    const errors = form.errors as Record<string, string | undefined>;

    return errors[`assets.${index}.${field}`];
}

function submit(): void {
    submitError.value = null;

    form.put(update.url(), {
        preserveScroll: true,
        onHttpException: () => {
            submitError.value =
                'Unable to update the balances. Please try again.';
        },
        onNetworkError: () => {
            submitError.value =
                'Unable to update the balances. Please try again.';
        },
    });
}
</script>

<template>
    <Head title="Demo balances" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Demo balances"
            description="Manually overwrite your cash and asset balances."
        />

        <Alert
            class="border-amber-500/50 bg-amber-500/10"
            data-test="demo-notice"
        >
            <AlertTitle class="text-amber-700 dark:text-amber-400">
                Demo page only
            </AlertTitle>
            <AlertDescription class="text-amber-700/90 dark:text-amber-400/90">
                This page writes straight to your account balances and is not
                part of the exchange's real trading flow. Use it only to seed a
                demo account.
            </AlertDescription>
        </Alert>

        <form class="space-y-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>USD balance</CardTitle>
                    <CardDescription>
                        Available and reserved cash held by the account.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="balance">Balance</Label>
                        <Input
                            id="balance"
                            v-model="form.balance"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            data-test="balance-input"
                        />
                        <InputError :message="form.errors.balance" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="locked_balance">Locked balance</Label>
                        <Input
                            id="locked_balance"
                            v-model="form.locked_balance"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            data-test="locked-balance-input"
                        />
                        <InputError :message="form.errors.locked_balance" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Asset balances</CardTitle>
                    <CardDescription>
                        Available and reserved amounts for each asset.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div
                        v-for="(asset, index) in form.assets"
                        :key="asset.symbol"
                        class="space-y-4 border-b pb-6 last:border-b-0 last:pb-0"
                    >
                        <span class="text-sm font-medium">
                            {{ asset.symbol }}
                        </span>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label :for="`asset-${asset.symbol}-amount`">
                                    Amount
                                </Label>
                                <Input
                                    :id="`asset-${asset.symbol}-amount`"
                                    v-model="form.assets[index].amount"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    :data-test="`asset-${asset.symbol}-amount`"
                                />
                                <InputError
                                    :message="assetError(index, 'amount')"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`asset-${asset.symbol}-locked`">
                                    Locked amount
                                </Label>
                                <Input
                                    :id="`asset-${asset.symbol}-locked`"
                                    v-model="form.assets[index].locked_amount"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    :data-test="`asset-${asset.symbol}-locked`"
                                />
                                <InputError
                                    :message="
                                        assetError(index, 'locked_amount')
                                    "
                                />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <p
                v-if="submitError"
                class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                data-test="submit-error"
            >
                {{ submitError }}
            </p>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="update-demo-button"
                >
                    <Spinner v-if="form.processing" />
                    Save balances
                </Button>
            </div>
        </form>
    </div>
</template>
