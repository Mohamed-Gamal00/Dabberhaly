<template>
    <div class="os-page" :dir="dir">
        <v-container class="py-10">
            <div class="os-card">

                <!-- Checkmark -->
                <div class="os-icon">
                    <v-icon size="40" color="white">mdi-check</v-icon>
                </div>

                <h1 class="os-title">{{ t('order_success_title') }}</h1>
                <p class="os-sub">{{ t('order_success_sub') }}</p>

                <!-- Order details -->
                <div class="os-details">
                    <div class="os-row">
                        <span class="os-label">{{ t('order_number') }}</span>
                        <span class="os-val fw-bold">#{{ order.number }}</span>
                    </div>
                    <div class="os-row">
                        <span class="os-label">{{ t('items_count') }}</span>
                        <span class="os-val">{{ order.items_count }} {{ t('pieces') }}</span>
                    </div>
                    <div class="os-row">
                        <span class="os-label">{{ t('payment_method') }}</span>
                        <span class="os-val">{{ t(order.payment_method) }}</span>
                    </div>
                    <div class="os-row os-row--total">
                        <span class="os-label">{{ t('total') }}</span>
                        <span class="os-val os-amount">{{ ((order.total_price || 0) + (order.shipping_price || 0)).toFixed(2) }} {{ t('currency_sar') }}</span>
                    </div>
                </div>

                <p v-if="order.is_guest" class="os-note">
                    <v-icon size="16" color="#76543D" class="me-1">mdi-information-outline</v-icon>
                    {{ t('order_success_keep_number') }}
                </p>

                <!-- Actions -->
                <div class="os-actions">
                    <a v-if="!order.is_guest" href="/user-profile?tab=orders" class="os-btn os-btn--primary">
                        <v-icon size="16" class="me-1">mdi-clipboard-list-outline</v-icon>{{ t('view_orders') }}
                    </a>
                    <a href="/products" class="os-btn os-btn--outline">
                        <v-icon size="16" class="me-1">mdi-shopping-outline</v-icon>{{ t('continue_shopping') }}
                    </a>
                </div>

            </div>
        </v-container>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useLocale } from '../../composables/useLocale';
const { props } = usePage();
const order = computed(() => props.order);
const { t, dir } = useLocale();
</script>

<style scoped>
.os-page { background: #F7F0E5; min-height: 100vh; }

.os-card {
    max-width: 520px;
    margin: 0 auto;
    background: white;
    border: 1px solid #E9DFCE;
    border-radius: 20px;
    padding: 36px 28px 28px;
    text-align: center;
    box-shadow: 0 1px 2px rgba(118, 84, 61, 0.05), 0 12px 32px rgba(118, 84, 61, 0.08);
}

.os-icon {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: #76543D;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 8px 20px rgba(118, 84, 61, 0.35);
    animation: os-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes os-pop {
    from { transform: scale(0.6); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.os-title { font-size: 22px; font-weight: 800; color: #302F2B; margin: 0 0 8px; }
.os-sub { font-size: 14px; color: #77736B; line-height: 1.9; margin: 0 0 24px; }

.os-details {
    background: #FAF6EE;
    border: 1px solid #E6DCCB;
    border-radius: 14px;
    padding: 6px 18px;
    text-align: start;
    margin-bottom: 16px;
}

.os-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 11px 0;
    border-bottom: 1px solid #EFE7D8;
    font-size: 13.5px;
}
.os-row:last-child { border-bottom: none; }
.os-label { color: #77736B; }
.os-val { color: #302F2B; font-weight: 600; }
.os-row--total .os-val { color: #76543D; }
.os-amount { font-size: 16px; font-weight: 800; }

.os-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
    font-size: 12.5px;
    color: #76543D;
    background: #FFF9ED;
    border: 1px solid #E6D3BB;
    border-radius: 10px;
    padding: 10px 12px;
    margin: 0 0 20px;
}

.os-actions { display: flex; flex-direction: column; gap: 10px; }

.os-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    height: 48px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
    transition: filter 0.15s, background 0.15s;
}
.os-btn--primary { background: #76543D; color: #fff; box-shadow: 0 6px 16px rgba(118, 84, 61, 0.25); }
.os-btn--primary:hover { filter: brightness(1.08); }
.os-btn--outline { border: 1.5px solid #D7CDBD; color: #76543D; background: white; }
.os-btn--outline:hover { background: #FAF6EE; }
</style>
