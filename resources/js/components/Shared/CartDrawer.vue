<template>
    <div>
        <v-navigation-drawer v-model="drawer" temporary location="left" width="400" class="cart-drawer">
            <div class="cart-drawer-inner">

                <!-- Header -->
                <div class="cd-header">
                    <div class="cd-header-title">
                        <v-icon size="20" color="#76543D">mdi-cart-outline</v-icon>
                        <span>{{ t('cart_title') }}</span>
                        <span v-if="cartItems.length" class="cd-count">{{ cartItems.length }}</span>
                    </div>
                    <button class="cd-close" @click="drawer = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </button>
                </div>

                <!-- Empty state -->
                <div v-if="!cartItems.length" class="cd-empty">
                    <div class="cd-empty-icon">
                        <v-icon size="44" color="#76543D">mdi-cart-outline</v-icon>
                    </div>
                    <div class="cd-empty-title">{{ t('cart_empty_title') }}</div>
                    <p class="cd-empty-sub">{{ t('cart_empty_sub') }}</p>
                    <v-btn color="primary" height="46" rounded="lg" class="cd-empty-btn" href="/products">
                        <v-icon size="18" class="me-1">mdi-shopping-outline</v-icon>
                        {{ t('cart_browse_products') }}
                    </v-btn>
                </div>

                <!-- Filled state -->
                <template v-else>
                    <!-- Free shipping progress -->
                    <div class="cd-shipping">
                        <div class="cd-shipping-head">
                            <v-icon size="18" color="#76543D">mdi-truck-outline</v-icon>
                            <span v-if="total < freeShippingThreshold" class="cd-shipping-text">
                                {{ t('cart_free_shipping_remaining') }} — <strong>{{ freeShippingThreshold - total }} ر.س</strong>
                            </span>
                            <span v-else class="cd-shipping-text cd-shipping-text--done">
                                <v-icon size="16" color="#15803d">mdi-check-circle</v-icon>
                                {{ t('cart_free_shipping_congrats') }}
                            </span>
                        </div>
                        <div class="cd-progress">
                            <div class="cd-progress-fill" :style="{ width: Math.min(shippingProgress, 100) + '%' }" />
                            <v-icon
                                size="20"
                                color="#76543D"
                                class="cd-progress-truck"
                                :style="{ insetInlineStart: 'calc(' + Math.min(Math.max(shippingProgress, 2), 96) + '% - 10px)' }"
                            >mdi-truck</v-icon>
                        </div>
                    </div>

                    <!-- Items list -->
                    <div class="cd-items">
                        <div v-for="item in cartItems" :key="item.id" class="cd-item">
                            <img :src="item.image" class="cd-item-img" alt="product" />
                            <div class="cd-item-details">
                                <div class="cd-item-name">{{ item.name }}</div>
                                <div class="cd-item-price-row">
                                    <span class="cd-item-price">{{ Math.ceil(item.discount_price || item.price) }} ر.س</span>
                                    <span v-if="item.discount_price && Number(item.discount_price) < Number(item.price)" class="cd-item-old-price">{{ Math.ceil(item.price) }} ر.س</span>
                                </div>
                                <div class="cd-item-footer">
                                    <div class="cd-qty">
                                        <v-icon size="16" class="cd-qty-icon" @click="changeQty(item, -1)">mdi-minus</v-icon>
                                        <span class="cd-qty-value">{{ item.quantity }}</span>
                                        <v-icon size="16" class="cd-qty-icon" @click="changeQty(item, 1)">mdi-plus</v-icon>
                                    </div>
                                    <span class="cd-item-total">{{ Math.ceil((Number(item.discount_price) || Number(item.price)) * Number(item.quantity)) }} ر.س</span>
                                </div>
                            </div>
                            <button class="cd-remove" @click="removeItem(item.id)">
                                <v-icon size="17">mdi-trash-can-outline</v-icon>
                            </button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="cd-footer">
                        <div v-if="totalSavings > 0" class="cd-sum-row cd-sum-saving">
                            <span><v-icon size="14" color="#15803d" class="me-1">mdi-tag-outline</v-icon>{{ t('cart_you_save') }}</span>
                            <span>- {{ totalSavings }} ر.س</span>
                        </div>
                        <div class="cd-sum-row cd-sum-total">
                            <span>{{ t('cart_total') }}</span>
                            <span>{{ total }} ر.س</span>
                        </div>
                        <p class="cd-shipping-note">{{ t('cart_shipping_note') }}</p>
                        <v-btn block color="primary" height="48" rounded="lg" class="cd-cta" href="/checkout">
                            {{ t('cart_checkout') }}
                            <v-icon size="18" class="ms-1">mdi-arrow-left</v-icon>
                        </v-btn>
                        <v-btn block variant="outlined" color="primary" height="44" rounded="lg" class="mt-2 cd-continue" @click="drawer = false">
                            {{ t('cart_continue') }}
                        </v-btn>
                    </div>
                </template>

            </div>
        </v-navigation-drawer>
    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import axios from 'axios';
import { useLocale } from '../../composables/useLocale';
const { t } = useLocale();

const Emitter = inject('Emitter');
const drawer = ref(false);
const cartItems = ref([]);
const freeShippingThreshold = 500;

const total = computed(() =>
    cartItems.value.reduce((sum, item) => {
        const price = item.discount_price || item.price;
        return sum + Math.ceil(price) * item.quantity;
    }, 0)
);

const totalSavings = computed(() =>
    cartItems.value.reduce((sum, item) => {
        if (item.discount_price && Number(item.discount_price) < Number(item.price)) {
            return sum + Math.ceil(Number(item.price) - Number(item.discount_price)) * item.quantity;
        }
        return sum;
    }, 0)
);

const shippingProgress = computed(() =>
    parseInt((total.value / freeShippingThreshold) * 100)
);

async function fetchCart() {
    try {
        const { data } = await axios.get('/cart');
        cartItems.value = data.items;
        Emitter.emit('cart-updated', data.count);
    } catch (e) {
        console.error('Cart fetch error', e);
    }
}

async function changeQty(item, delta) {
    const newQty = item.quantity + delta;
    if (newQty < 1) return;
    try {
        const { data } = await axios.patch(`/cart/${item.id}`, { quantity: newQty });
        cartItems.value = data.items;
        Emitter.emit('cart-updated', data.count);
    } catch (e) {
        console.error(e);
    }
}

async function removeItem(id) {
    try {
        const { data } = await axios.delete(`/cart/${id}`);
        cartItems.value = data.items;
        Emitter.emit('cart-updated', data.count);
    } catch (e) {
        console.error(e);
    }
}

onMounted(() => {
    fetchCart();
    Emitter.on('openCart', () => {
        drawer.value = true;
    });
    Emitter.on('cart-item-added', (items) => {
        cartItems.value = items;
        Emitter.emit('cart-updated', items.length);
        // Don't auto-open drawer — let the fly animation handle the feedback
    });
});
</script>

<style lang="scss" scoped>
.cart-drawer {
    background: #F7F0E5;
    overflow-y: auto;
    &::-webkit-scrollbar { width: 4px; }
    &::-webkit-scrollbar-thumb { background: #D7CDBD; border-radius: 4px; }
}

.cart-drawer-inner {
    display: flex;
    flex-direction: column;
    min-height: 100%;
    padding: 18px 16px;
}

/* ===== Header ===== */
.cd-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 14px;
    border-bottom: 1px solid #E6DCCB;
    margin-bottom: 16px;
}

.cd-header-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 800;
    color: #302F2B;
}

.cd-count {
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 11px;
    background: #76543D;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.cd-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: white;
    border: 1px solid #E6DCCB;
    color: #77736B;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
    &:hover { color: #76543D; border-color: #76543D; }
}

/* ===== Empty state ===== */
.cd-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 56px 16px 32px;
}

.cd-empty-icon {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: #EFE7D8;
    border: 1px solid #E6D3BB;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cd-empty-title {
    font-size: 17px;
    font-weight: 800;
    color: #302F2B;
    margin-top: 20px;
}

.cd-empty-sub {
    font-size: 13.5px;
    color: #77736B;
    line-height: 1.9;
    max-width: 260px;
    margin: 6px 0 0;
}

.cd-empty-btn {
    margin-top: 22px;
    min-width: 200px;
    text-transform: none;
    font-size: 14px;
    font-weight: 700;
}

/* ===== Free shipping ===== */
.cd-shipping {
    background: white;
    border: 1px solid #E9DFCE;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 14px;
}

.cd-shipping-head {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cd-shipping-text {
    font-size: 12.5px;
    color: #77736B;
    strong { color: #76543D; }
}

.cd-shipping-text--done {
    color: #15803d;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
}

.cd-progress {
    position: relative;
    height: 8px;
    background: #EFE7D8;
    border-radius: 4px;
    margin-top: 14px;
}

.cd-progress-fill {
    position: absolute;
    inset-inline-start: 0;
    top: 0;
    bottom: 0;
    background: linear-gradient(90deg, #C69763, #76543D);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.cd-progress-truck {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    transition: inset-inline-start 0.3s ease;
}

/* ===== Items ===== */
.cd-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 340px;
    overflow-y: auto;
    padding-inline-end: 2px;
    margin-bottom: 14px;
    &::-webkit-scrollbar { width: 4px; }
    &::-webkit-scrollbar-thumb { background: #D7CDBD; border-radius: 4px; }
}

.cd-item {
    position: relative;
    display: flex;
    gap: 12px;
    background: white;
    border: 1px solid #E9DFCE;
    border-radius: 14px;
    padding: 12px;
}

.cd-item-img {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #E6DCCB;
    flex-shrink: 0;
}

.cd-item-details {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-inline-end: 22px;
}

.cd-item-name {
    font-size: 13.5px;
    font-weight: 700;
    line-height: 1.4;
    color: #302F2B;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.cd-item-price-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cd-item-price {
    font-size: 14px;
    font-weight: 800;
    color: #76543D;
}

.cd-item-old-price {
    font-size: 12px;
    color: #B0A696;
    text-decoration: line-through;
}

.cd-item-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 6px;
}

.cd-qty {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 3px 8px;
    border: 1.5px solid #E6DCCB;
    border-radius: 20px;
    background: #FAF6EE;
}

.cd-qty-icon {
    color: #77736B;
    cursor: pointer;
    transition: color 0.2s;
    &:hover { color: #76543D; }
}

.cd-qty-value {
    font-size: 13px;
    min-width: 18px;
    text-align: center;
    color: #302F2B;
    font-weight: 700;
}

.cd-item-total {
    font-size: 14px;
    font-weight: 800;
    color: #76543D;
}

.cd-remove {
    position: absolute;
    top: 10px;
    inset-inline-end: 10px;
    background: none;
    border: none;
    padding: 0;
    color: #B0A696;
    cursor: pointer;
    transition: color 0.15s;
    &:hover { color: #dc2626; }
}

/* ===== Footer ===== */
.cd-footer {
    background: white;
    border: 1px solid #E9DFCE;
    border-radius: 14px;
    padding: 16px;
    margin-top: auto;
}

.cd-sum-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13.5px;
    color: #77736B;
    padding: 3px 0;
}

.cd-sum-saving { color: #15803d; font-weight: 600; }

.cd-sum-total {
    font-size: 16px;
    font-weight: 800;
    color: #302F2B;
    padding-top: 10px;
    border-top: 1px solid #EFE7D8;
    margin-top: 6px;
    span:last-child { color: #76543D; font-size: 18px; }
}

.cd-shipping-note {
    font-size: 12px;
    color: #77736B;
    text-align: center;
    margin: 8px 0 14px;
}

.cd-cta {
    text-transform: none;
    font-size: 15px;
    font-weight: 700;
    box-shadow: 0 6px 16px rgba(118, 84, 61, 0.25);
}

.cd-continue {
    text-transform: none;
    font-size: 14px;
    font-weight: 700;
}
</style>
