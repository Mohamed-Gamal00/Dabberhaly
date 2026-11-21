<template>
    <div class="co-page" :dir="dir">
        <SeoHead :title="t('checkout_title')" noindex />
        <v-container class="py-6 py-md-8">

            <!-- ===== Page header + steps ===== -->
            <div class="co-header mb-5">
                <div>
                    <h1 class="co-title">{{ t('checkout_title') }}</h1>
                    <p class="co-subtitle">{{ t('checkout_subtitle') }}</p>
                </div>
                <div class="co-steps" aria-hidden="true">
                    <div class="co-step"><span class="co-step-num">1</span><span class="co-step-label">{{ t('step_address') }}</span></div>
                    <span class="co-step-line" />
                    <div class="co-step"><span class="co-step-num">2</span><span class="co-step-label">{{ t('step_shipping') }}</span></div>
                    <span class="co-step-line" />
                    <div class="co-step"><span class="co-step-num">3</span><span class="co-step-label">{{ t('step_payment') }}</span></div>
                </div>
            </div>

            <!-- Validation summary from backend -->
            <v-alert
                v-if="Object.keys(errors).length"
                type="error"
                variant="tonal"
                rounded="lg"
                density="compact"
                class="mb-4"
            >
                <div class="font-weight-bold mb-1">{{ t('fix_errors') }}</div>
                <ul class="mb-0 ps-3" style="font-size:13px">
                    <li v-for="(msgs, field) in errors" :key="field">
                        {{ Array.isArray(msgs) ? msgs[0] : msgs }}
                    </li>
                </ul>
            </v-alert>

            <v-row>

                <!-- ===== Main flow (8) ===== -->
                <v-col cols="12" md="8">

                    <!-- Guest: login or guest toggle -->
                    <div v-if="!user" class="co-card mb-4">
                        <div class="co-section-head">
                            <v-icon size="20" color="#76543D">mdi-account-circle-outline</v-icon>
                            <div class="co-card-title mb-0">{{ t('login_or_guest') }}</div>
                        </div>
                        <v-divider class="my-4" />
                        <div class="co-opt-grid">
                            <label class="co-opt" :class="{ active: guestMode === 'login' }" @click="goToLogin">
                                <input type="radio" v-model="guestMode" value="login" class="co-opt-radio" />
                                <v-icon size="20" color="#76543D">mdi-login-variant</v-icon>
                                <div class="co-opt-body">
                                    <div class="co-opt-title">{{ t('login') }}</div>
                                    <div class="co-opt-desc">{{ t('login_or_guest') }}</div>
                                </div>
                                <v-icon size="18" color="#77736B">mdi-chevron-left</v-icon>
                            </label>
                            <label class="co-opt" :class="{ active: guestMode === 'guest' }">
                                <input type="radio" v-model="guestMode" value="guest" class="co-opt-radio" />
                                <v-icon size="20" color="#76543D">mdi-account-outline</v-icon>
                                <div class="co-opt-body">
                                    <div class="co-opt-title">{{ t('continue_as_guest') }}</div>
                                    <div class="co-opt-desc">{{ t('guest') }}</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Step 1: Address -->
                    <div class="co-card mb-4">
                        <div class="co-section-head">
                            <span class="co-step-badge">1</span>
                            <div class="co-card-title mb-0">{{ t('choose_shipping_address') }}</div>
                        </div>
                        <v-divider class="my-4" />

                        <!-- Logged in: saved addresses -->
                        <template v-if="user && userAddresses.length">
                            <div class="mb-2">
                                <label
                                    v-for="addr in userAddresses"
                                    :key="addr.id"
                                    class="co-addr-opt"
                                    :class="{ active: form.user_address === String(addr.id) }"
                                    @click="form.user_address = String(addr.id)"
                                >
                                    <v-icon size="18" :color="form.user_address === String(addr.id) ? 'primary' : 'grey'">mdi-map-marker-outline</v-icon>
                                    <div style="flex:1">
                                        <div style="font-size:13px; font-weight:700">{{ addr.address_title || t('address_title') }}</div>
                                        <div style="font-size:12px; color:#77736B">{{ addr.address }}</div>
                                    </div>
                                    <v-chip v-if="addr.main_address" size="x-small" color="primary" variant="flat">{{ t('main_address') }}</v-chip>
                                    <v-icon v-if="form.user_address === String(addr.id)" size="18" color="primary">mdi-check-circle</v-icon>
                                </label>
                            </div>
                        </template>

                        <!-- Add new address toggle -->
                        <template v-if="user">
                            <label
                                class="co-addr-opt"
                                :class="{ active: form.user_address === 'add_address' }"
                                @click="form.user_address = 'add_address'"
                            >
                                <v-icon size="18" color="grey">mdi-plus-circle-outline</v-icon>
                                <span style="font-size:13px; font-weight:600">{{ t('add_new_address') }}</span>
                                <v-icon v-if="form.user_address === 'add_address'" size="18" color="primary" class="ms-auto">mdi-check-circle</v-icon>
                            </label>
                        </template>

                        <!-- Address form: shown for guests in guest mode, or users adding new address -->
                        <template v-if="(!user && guestMode === 'guest') || (user && form.user_address === 'add_address')">
                            <div class="co-addr-form mt-4">
                                <div class="co-card-title mb-3" style="font-size:13px">{{ t('address_details') }}</div>
                                <div class="co-field-group">
                                    <div class="co-field">
                                        <label class="co-label">{{ t('first_name') }}</label>
                                        <v-text-field v-model="addrForm.first_name" :placeholder="t('first_name')" variant="outlined" density="compact" hide-details="auto" rounded="lg" bg-color="white" class="mt-1" :error-messages="errors['addr.shipping.first_name'] || errors['addr.billing.first_name']" />
                                    </div>
                                    <div class="co-field">
                                        <label class="co-label">{{ t('last_name') }}</label>
                                        <v-text-field v-model="addrForm.last_name" :placeholder="t('last_name')" variant="outlined" density="compact" hide-details="auto" rounded="lg" bg-color="white" class="mt-1" :error-messages="errors['addr.shipping.last_name'] || errors['addr.billing.last_name']" />
                                    </div>
                                    <div v-if="!user" class="co-field co-field--full">
                                        <label class="co-label">{{ t('email') }}</label>
                                        <v-text-field v-model="addrForm.email" :placeholder="t('email')" variant="outlined" density="compact" hide-details="auto" rounded="lg" bg-color="white" class="mt-1" dir="ltr" :error-messages="errors['guest_email']" />
                                    </div>
                                    <div class="co-field co-field--full">
                                        <label class="co-label">{{ t('phone') }}</label>
                                        <v-text-field v-model="addrForm.phone_number" :placeholder="t('phone')" variant="outlined" density="compact" hide-details="auto" rounded="lg" bg-color="white" class="mt-1" dir="ltr" :error-messages="errors['addr.shipping.phone_number'] || errors['addr.billing.phone_number']" />
                                    </div>
                                    <div class="co-field co-field--full">
                                        <label class="co-label">{{ t('country') }}</label>
                                        <v-select v-model="addrForm.country_id" :items="countries" :item-title="isEn ? 'name_en' : 'name_ar'" item-value="id" :placeholder="t('select_country')" variant="outlined" density="compact" hide-details="auto" rounded="lg" bg-color="white" class="mt-1" :error-messages="errors['addr.shipping.country_id'] || errors['addr.billing.country_id']" @update:model-value="addrForm.city_id = null" />
                                    </div>
                                    <div class="co-field co-field--full">
                                        <label class="co-label">{{ t('city') }}</label>
                                        <v-select v-model="addrForm.city_id" :items="filteredCities" :item-title="isEn ? 'name_en' : 'name_ar'" item-value="id" :placeholder="t('select_city')" variant="outlined" density="compact" hide-details="auto" rounded="lg" bg-color="white" class="mt-1" :error-messages="errors['addr.shipping.city_id'] || errors['addr.billing.city_id']" />
                                    </div>
                                    <div class="co-field co-field--full">
                                        <label class="co-label">{{ t('address') }}</label>
                                        <v-text-field v-model="addrForm.address" :placeholder="t('enter_address')" variant="outlined" density="compact" hide-details="auto" rounded="lg" bg-color="white" class="mt-1" :error-messages="errors['addr.shipping.address'] || errors['addr.billing.address']" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Step 2: Shipping method -->
                    <div class="co-card mb-4">
                        <div class="co-section-head">
                            <span class="co-step-badge">2</span>
                            <div class="co-card-title mb-0">{{ t('shipping_method') }}</div>
                        </div>
                        <v-divider class="my-4" />
                        <div class="co-opt-grid">
                            <label v-if="shipping?.add_pickup_from_store" class="co-opt" :class="{ active: shippingOption === 'noShipping' }">
                                <input type="radio" v-model="shippingOption" value="noShipping" class="co-opt-radio" />
                                <v-icon size="22" color="#76543D">mdi-store-outline</v-icon>
                                <div class="co-opt-body">
                                    <div class="co-opt-title">{{ t('pickup_from_store') }}</div>
                                    <div class="co-opt-desc">{{ t('free_shipping') }}</div>
                                </div>
                                <span class="co-opt-price">{{ t('free_shipping') }}</span>
                            </label>
                            <label v-if="shipping?.add_normal_price" class="co-opt" :class="{ active: shippingOption === 'fixed_shipping' }">
                                <input type="radio" v-model="shippingOption" value="fixed_shipping" class="co-opt-radio" />
                                <v-icon size="22" color="#76543D">mdi-truck-outline</v-icon>
                                <div class="co-opt-body">
                                    <div class="co-opt-title">{{ t('fixed_shipping') }}</div>
                                    <div class="co-opt-desc">{{ t('shipping_cost') }}</div>
                                </div>
                                <span class="co-opt-price">{{ shipping.normal_shipping_price }} {{ t('currency_sar') }}</span>
                            </label>
                            <label v-if="shipping?.add_wight_price" class="co-opt" :class="{ active: shippingOption === 'shipping_based_on_weight' }">
                                <input type="radio" v-model="shippingOption" value="shipping_based_on_weight" class="co-opt-radio" />
                                <v-icon size="22" color="#76543D">mdi-weight</v-icon>
                                <div class="co-opt-body">
                                    <div class="co-opt-title">{{ t('weight_shipping') }}</div>
                                    <div class="co-opt-desc">{{ t('weight_shipping_desc') }}</div>
                                </div>
                                <span class="co-opt-price">{{ weightShippingCost ? weightShippingCost + ' ' + t('currency_sar') : '—' }}</span>
                            </label>
                            <label v-if="shipping?.add_price_based_on_city" class="co-opt" :class="{ active: shippingOption === 'shipping_based_on_city' }">
                                <input type="radio" v-model="shippingOption" value="shipping_based_on_city" class="co-opt-radio" />
                                <v-icon size="22" color="#76543D">mdi-map-marker-outline</v-icon>
                                <div class="co-opt-body">
                                    <div class="co-opt-title">{{ t('city_shipping') }}</div>
                                    <div class="co-opt-desc">{{ t('city_shipping_desc') }}</div>
                                </div>
                                <span class="co-opt-price">{{ cityShippingPrice ? cityShippingPrice + ' ' + t('currency_sar') : '—' }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Step 3: Payment method -->
                    <div class="co-card mb-4">
                        <div class="co-section-head">
                            <span class="co-step-badge">3</span>
                            <div class="co-card-title mb-0">{{ t('payment_method') }}</div>
                        </div>
                        <v-divider class="my-4" />
                        <div class="co-opt-grid">
                            <label v-if="paymentMethods.includes('cash_on_delivery')" class="co-opt" :class="{ active: form.payment_method === 'cash_on_delivery' }">
                                <input type="radio" v-model="form.payment_method" value="cash_on_delivery" class="co-opt-radio" />
                                <v-icon size="22" color="#76543D">mdi-cash</v-icon>
                                <div class="co-opt-body">
                                    <div class="co-opt-title">{{ t('cash_on_delivery') }}</div>
                                    <div class="co-opt-desc">{{ t('cod_desc') }}</div>
                                </div>
                            </label>
                            <label v-if="paymentMethods.includes('card_payment')" class="co-opt" :class="{ active: form.payment_method === 'card_payment' }">
                                <input type="radio" v-model="form.payment_method" value="card_payment" class="co-opt-radio" />
                                <v-icon size="22" color="#76543D">mdi-credit-card-outline</v-icon>
                                <div class="co-opt-body">
                                    <div class="co-opt-title">{{ t('card_payment') }}</div>
                                    <div class="co-opt-desc">{{ t('card_desc') }}</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Notes + newsletter -->
                    <div class="co-card mb-4">
                        <div class="co-card-title">{{ t('note_optional') }}</div>
                        <v-textarea v-model="form.note" variant="outlined" density="compact" hide-details rows="3" rounded="lg" bg-color="grey-lighten-5" class="mt-1" />
                        <label class="co-check mt-3"><input type="checkbox" v-model="joinNews" /><span>{{ t('join_newsletter') }}</span></label>
                    </div>

                </v-col>

                <!-- ===== Order summary (4, sticky) ===== -->
                <v-col cols="12" md="4">
                    <div class="co-summary">
                        <div class="co-card">
                            <div class="co-summary-head">
                                <div class="co-card-title mb-0">{{ t('order_summary') }}</div>
                                <a href="/products" class="co-edit-link">{{ t('edit_cart') }}</a>
                            </div>
                            <v-divider class="my-4" />

                            <!-- Items -->
                            <div class="co-items">
                                <div v-for="item in cartItems" :key="item.id" class="co-item">
                                    <img :src="item.image" class="co-item-img" alt="product" />
                                    <div class="co-item-info">
                                        <div class="co-item-name">{{ pick(item, 'name') }}</div>
                                        <div class="co-item-meta">{{ item.quantity }} × {{ Math.ceil(Number(item.discount_price) || Number(item.price)) }} {{ t('currency_sar') }}</div>
                                    </div>
                                    <div class="co-item-total">
                                        {{ Math.ceil((Number(item.discount_price) || Number(item.price)) * Number(item.quantity)) }} {{ t('currency_sar') }}
                                    </div>
                                </div>
                            </div>

                            <v-divider class="my-4" />

                            <!-- Discount code -->
                            <div class="co-discount-block">
                                <div class="co-card-title mb-3" style="font-size:13px">{{ t('discount_code') }}</div>
                                <div v-if="appliedDiscount" class="co-discount-applied">
                                    <v-icon size="18" color="success" class="me-1">mdi-check-circle</v-icon>
                                    <span>{{ t('discount_applied') }} <strong>{{ appliedDiscount.code }}</strong> — {{ appliedDiscount.type === 'percentage' ? appliedDiscount.value + '%' : appliedDiscount.value + ' ' + t('currency_sar') }}</span>
                                    <button class="co-discount-remove" @click="removeDiscount">
                                        <v-icon size="14">mdi-close</v-icon>
                                    </button>
                                </div>
                                <div v-else class="co-discount-input-row">
                                    <v-text-field v-model="discountInput" :placeholder="t('discount_placeholder')" variant="outlined" density="compact" hide-details rounded="lg" bg-color="grey-lighten-5" style="flex:1" dir="ltr" :error="!!discountError" @keyup.enter="applyDiscount" />
                                    <v-btn color="primary" rounded="lg" height="40" style="text-transform:none; min-width:90px" :loading="discountLoading" @click="applyDiscount">{{ t('discount_apply') }}</v-btn>
                                </div>
                                <div v-if="discountError" class="mt-2">
                                    <div v-if="discountError === '__login__'" class="co-discount-login-msg">
                                        <v-icon size="16" color="warning" class="me-1">mdi-account-lock-outline</v-icon>
                                        {{ t('discount_login_required') }}
                                        <a href="/login" class="text-primary fw-bold mx-1">{{ t('discount_login_link') }}</a>
                                        {{ t('discount_or') }}
                                        <a href="/register" class="text-primary fw-bold mx-1">{{ t('discount_register_link') }}</a>
                                        {{ t('discount_login_msg') }}
                                    </div>
                                    <div v-else class="text-red text-caption">{{ discountError }}</div>
                                </div>
                            </div>

                            <v-divider class="my-4" />

                            <!-- Totals -->
                            <div class="co-sum-row"><span>{{ t('cart_items_count') }}</span><span>{{ totalItemsCount }} {{ t('pieces') }}</span></div>
                            <div class="co-sum-row"><span>{{ t('order_subtotal') }}</span><span>{{ Math.ceil(cartTotal + discountSaving) }} {{ t('currency_sar') }}</span></div>
                            <div v-if="appliedDiscount" class="co-sum-row co-sum-discount">
                                <span>
                                    <v-icon size="14" color="success" class="me-1">mdi-tag-outline</v-icon>
                                    {{ t('discount_applied') }} ({{ appliedDiscount.code }})
                                </span>
                                <span>- {{ discountSaving }} {{ t('currency_sar') }}</span>
                            </div>
                            <div class="co-sum-row"><span>{{ t('shipping_cost') }}</span><span>{{ shippingCost ? Math.ceil(shippingCost) + ' ' + t('currency_sar') : t('free_shipping') }}</span></div>
                            <div v-if="vatRate > 0" class="co-sum-row"><span>{{ t('tax') }}</span><span>{{ Math.ceil(taxAmount) }} {{ t('currency_sar') }}</span></div>
                            <div class="co-sum-row co-sum-total"><span>{{ t('total') }}</span><span>{{ grandTotal }} {{ t('currency_sar') }}</span></div>

                            <div v-if="errors.general" class="text-red text-caption mt-3 text-center">{{ errors.general }}</div>

                            <v-btn block color="primary" height="52" rounded="lg" class="co-cta" :loading="submitting" @click="submit">{{ t('place_order') }}</v-btn>

                            <!-- Implied consent replaces the mandatory checkbox -->
                            <p class="co-consent">
                                {{ t('consent_prefix') }}
                                <a href="/terms-conditions" target="_blank" rel="noopener">{{ t('terms_conditions') }}</a>
                                {{ t('consent_and') }}
                                <a href="/privacy-policy" target="_blank" rel="noopener">{{ t('privacy_policy') }}</a>
                            </p>
                        </div>
                    </div>
                </v-col>

            </v-row>
        </v-container>

        <!-- Mobile sticky CTA -->
        <div class="co-mobile-bar d-md-none">
            <div class="co-mobile-total">
                <span>{{ t('total') }}</span>
                <strong>{{ grandTotal }} {{ t('currency_sar') }}</strong>
            </div>
            <v-btn color="primary" height="46" rounded="lg" class="co-mobile-btn" :loading="submitting" @click="submit">{{ t('place_order') }}</v-btn>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import SeoHead from '../../components/Shared/SeoHead.vue';
import axios from 'axios';
import { useLocale } from '../../composables/useLocale';
const { t, pick, isEn, dir } = useLocale();

const { props } = usePage();
const user          = computed(() => props.auth?.user);
const cartItems     = ref(props.cartItems || []);
const totalBefore   = computed(() =>
    cartItems.value.reduce((sum, item) => {
        return sum + Number(item.price) * item.quantity;
    }, 0)
);

const totalItemsCount = computed(() => cartItems.value.length);

const cartTotal = computed(() =>
    cartItems.value.reduce((sum, item) => {
        const price = item.discount_price && Number(item.discount_price) < Number(item.price)
            ? Number(item.discount_price)
            : Number(item.price);
        const qty = Number(item.quantity);
        return sum + price * qty;
    }, 0)
);
const countries     = ref(props.countries || []);
const cities        = ref(props.cities || []);
const shipping      = ref(props.shipping);
const vatRate       = ref(Number(props.vatRate) || 0);
const freeShippingThreshold = ref(props.freeShippingThreshold ? Number(props.freeShippingThreshold) : null);
const paymentMethods = ref(props.paymentMethods || ['cash_on_delivery', 'card_payment']);
const discountPrice = ref(props.discountPrice || 0);
const userAddresses = ref(props.userAddresses || []);

const submitting     = ref(false);
const errors         = ref({});
const discountInput  = ref('');
const discountError  = ref('');
const discountLoading = ref(false);
const appliedDiscount = ref(null); // { code, value, type }
const joinNews       = ref(false);
const guestMode      = ref('guest');

function goToLogin() {
    window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
}
const shippingOption = ref(
    props.shipping?.add_pickup_from_store ? 'noShipping' :
    props.shipping?.add_normal_price      ? 'fixed_shipping' :
    props.shipping?.add_wight_price       ? 'shipping_based_on_weight' :
    props.shipping?.add_price_based_on_city ? 'shipping_based_on_city' : 'fixed_shipping'
);

const form = ref({
    payment_method: paymentMethods.value[0] || 'cash_on_delivery',
    user_address: userAddresses.value.find(a => a.main_address)
        ? String(userAddresses.value.find(a => a.main_address).id)
        : (userAddresses.value.length ? String(userAddresses.value[0].id) : 'add_address'),
    note: '',
});

const addrForm = ref({
    first_name:   user.value?.first_name || '',
    last_name:    '',
    email:        user.value?.email || '',
    phone_number: '',
    country_id:   null,
    city_id:      null,
    address:      '',
});

const filteredCities = computed(() =>
    addrForm.value.country_id
        // eslint-disable-next-line eqeqeq
        ? cities.value.filter(c => c.country_id == addrForm.value.country_id)
        : cities.value
);

const cityShippingPrice = computed(() => {
    // For saved address: look up city_id from the selected user address
    let cityId = addrForm.value.city_id;

    if (user.value && form.value.user_address && form.value.user_address !== 'add_address') {
        const savedAddr = userAddresses.value.find(a => String(a.id) === String(form.value.user_address));
        if (savedAddr?.city_id) cityId = savedAddr.city_id;
    }

    if (!cityId) return 0;
    // eslint-disable-next-line eqeqeq
    const city = cities.value.find(c => c.id == cityId);
    return city?.shipping_price || 0;
});

const shippingCost = computed(() => {
    if (shippingOption.value === 'noShipping') return 0;
    // Free shipping once the items subtotal (after product offers,
    // before any coupon) reaches the configured threshold.
    if (freeShippingThreshold.value && cartTotal.value >= freeShippingThreshold.value) return 0;
    if (shippingOption.value === 'fixed_shipping') return Number(shipping.value?.normal_shipping_price) || 0;
    if (shippingOption.value === 'shipping_based_on_city') return Number(cityShippingPrice.value) || 0;
    if (shippingOption.value === 'shipping_based_on_weight') return weightShippingCost.value;
    return 0;
});

const totalWeight = computed(() =>
    cartItems.value.reduce((sum, item) => sum + Number(item.weight || 0) * Number(item.quantity), 0)
);

// Weight shipping is charged on the TOTAL weight, not the per-kg price alone.
const weightShippingCost = computed(() =>
    Math.ceil(totalWeight.value * (Number(shipping.value?.weight_price) || 0))
);

// VAT base = discounted items subtotal (after the coupon, if any).
const taxAmount = computed(() =>
    vatRate.value > 0 ? Math.max(0, cartTotal.value - discountSaving.value) * vatRate.value / 100 : 0
);

const grandTotal = computed(() =>
    Math.ceil(Number(cartTotal.value) + Number(shippingCost.value) - Number(discountSaving.value) + Number(taxAmount.value))
);

async function applyDiscount() {
    if (!discountInput.value.trim()) return;
    discountError.value   = '';
    discountLoading.value = true;
    try {
        const { data } = await axios.post('/checkout/apply-discount', {
            discount_code: discountInput.value.trim(),
        });
        appliedDiscount.value = {
            code:  data.discount_code,
            value: data.discount_value,
            type:  data.discount_type,
        };
        discountPrice.value = data.discount_type === 'percentage'
            ? Math.ceil(cartTotal.value * data.discount_value / 100)
            : data.discount_value;
        discountInput.value = '';
    } catch (e) {
        const res = e.response?.data;
        if (res?.requires_login) {
            discountError.value = '__login__';
        } else {
            discountError.value = res?.message || t('error_occurred');
        }
    } finally {
        discountLoading.value = false;
    }
}

async function removeDiscount() {
    // Real round-trip: the server forgets the session code and resets
    // discounted_price on the cart rows, so a later checkout is
    // charged the full price and the code is not consumed.
    try {
        await axios.post('/checkout/remove-discount');
    } catch (e) {
        console.error('Remove discount failed:', e.response?.data);
    }
    appliedDiscount.value = null;
    discountPrice.value   = 0;
    discountInput.value   = '';
    discountError.value   = '';
}

const discountSaving = computed(() => {
    if (!appliedDiscount.value) return 0;
    if (appliedDiscount.value.type === 'percentage') {
        return Math.ceil(cartTotal.value * appliedDiscount.value.value / 100);
    }
    return appliedDiscount.value.value;
});

async function submit() {
    // Validate guest / new address fields
    const isGuest = !user.value;
    const needsAddressForm = isGuest ? guestMode.value === 'guest' : form.value.user_address === 'add_address';

    if (needsAddressForm) {
        const fieldErrors = {};
        if (!addrForm.value.first_name?.trim())   fieldErrors['addr.billing.first_name']   = [t('first_name_required')];
        if (!addrForm.value.last_name?.trim())    fieldErrors['addr.billing.last_name']    = [t('last_name_required')];
        if (!addrForm.value.phone_number?.trim()) fieldErrors['addr.billing.phone_number'] = [t('phone_required')];
        if (!addrForm.value.address?.trim())      fieldErrors['addr.billing.address']      = [t('address_required')];
        if (!addrForm.value.country_id)           fieldErrors['addr.billing.country_id']   = [t('country_required')];
        if (!addrForm.value.city_id)              fieldErrors['addr.billing.city_id']      = [t('city_required')];
        if (isGuest && !addrForm.value.email?.trim()) fieldErrors['guest_email'] = [t('email_required')];

        if (Object.keys(fieldErrors).length) {
            errors.value = fieldErrors;
            // Scroll to the form
            document.querySelector('.co-field-group')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
    }

    submitting.value = true;
    errors.value = {};

    const addrType = isGuest ? 'billing' : 'shipping';

    const payload = {
        payment_method:  form.value.payment_method,
        note:            form.value.note,
        shipping_price:  shippingCost.value,
        join_news:       joinNews.value ? 1 : 0,
    };

    if (shippingOption.value === 'noShipping') {
        payload.pickup_from_store = true;
    } else if (shippingOption.value === 'fixed_shipping') {
        payload.fixed_shipping = true;
    } else if (shippingOption.value === 'shipping_based_on_weight') {
        payload.shipping_based_on_weight = true;
    } else if (shippingOption.value === 'shipping_based_on_city') {
        payload.shipping_based_on_city = true;
    }

    if (user.value && form.value.user_address !== 'add_address') {
        payload.user_address = form.value.user_address;
    } else {
        payload.addr = {
            [addrType]: {
                first_name:   addrForm.value.first_name,
                last_name:    addrForm.value.last_name,
                phone_number: addrForm.value.phone_number,
                country_id:   addrForm.value.country_id,
                city_id:      addrForm.value.city_id,
                address:      addrForm.value.address,
            }
        };
        if (isGuest) payload.guest_email = addrForm.value.email;
    }

    try {
        const { data } = await axios.post('/checkout', payload);
        if (data.redirect) window.location.href = data.redirect;
        else router.visit('/');
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {};
        } else {
            errors.value = { general: e.response?.data?.message || e.message || t('error_occurred') };
            console.error('Checkout error:', e.response?.data);
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<style scoped>
.co-page { background: #F7F0E5; min-height: 100vh; }

/* ===== Header + steps ===== */
.co-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}

.co-title { font-size: 22px; font-weight: 800; color: #302F2B; line-height: 1.3; margin: 0; }
.co-subtitle { font-size: 13.5px; color: #77736B; margin: 4px 0 0; }

.co-steps { display: flex; align-items: center; gap: 10px; }
.co-step { display: flex; align-items: center; gap: 8px; }
.co-step-num {
    width: 30px; height: 30px; border-radius: 50%;
    background: #76543D; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.co-step-label { font-size: 13px; font-weight: 700; color: #302F2B; white-space: nowrap; }
.co-step-line { width: 28px; height: 2px; background: #D7CDBD; border-radius: 2px; }

/* ===== Cards ===== */
.co-card {
    background: white;
    border: 1px solid #E9DFCE;
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 1px 2px rgba(118, 84, 61, 0.05), 0 10px 30px rgba(118, 84, 61, 0.06);
}

.co-section-head { display: flex; align-items: center; gap: 12px; }
.co-step-badge {
    width: 30px; height: 30px; border-radius: 50%;
    background: #76543D; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; flex-shrink: 0;
}
.co-card-title {
    font-size: 15px;
    font-weight: 800;
    color: #302F2B;
}

/* ===== Option cards (shipping / payment / guest) ===== */
.co-opt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.co-opt {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border: 1.5px solid #E6DCCB;
    border-radius: 12px;
    cursor: pointer;
    background: white;
    transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.co-opt:hover { border-color: #C69763; }
.co-opt.active {
    border-color: #76543D;
    background: rgba(118, 84, 61, 0.06);
    box-shadow: 0 2px 10px rgba(118, 84, 61, 0.10);
}
.co-opt-radio { accent-color: #76543D; width: 16px; height: 16px; flex-shrink: 0; }
.co-opt-body { flex: 1; min-width: 0; }
.co-opt-title { font-size: 13.5px; font-weight: 700; color: #302F2B; }
.co-opt-desc { font-size: 12px; color: #77736B; margin-top: 2px; }
.co-opt-price { font-size: 13px; font-weight: 800; color: #76543D; white-space: nowrap; }

/* ===== Address options ===== */
.co-addr-opt {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1.5px solid #E6DCCB;
    border-radius: 12px;
    padding: 12px 14px;
    cursor: pointer;
    margin-bottom: 8px;
    transition: border-color 0.15s, background 0.15s;
    font-size: 13px;
    background: white;
}
.co-addr-opt:hover { border-color: #C69763; }
.co-addr-opt.active { border-color: #76543D; background: rgba(118, 84, 61, 0.06); }

.co-addr-form {
    background: #FAF6EE;
    border: 1px solid #E6DCCB;
    border-radius: 12px;
    padding: 16px;
}

/* ===== Discount ===== */
.co-discount-login-msg {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 2px;
    background: #FFF9ED;
    border: 1px solid #E6D3BB;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: #76543D;
}

.co-discount-input-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.co-discount-applied {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #EFF8F3;
    border: 1px solid #C6E7D5;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 13px;
    color: #15803d;
}

.co-discount-remove {
    margin-right: auto;
    background: none;
    border: none;
    cursor: pointer;
    color: #77736B;
    display: flex;
    align-items: center;
    padding: 0;
}
.co-discount-remove:hover { color: #ef4444; }

/* ===== Summary ===== */
.co-summary { position: sticky; top: 84px; }
.co-summary-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.co-edit-link { font-size: 12.5px; font-weight: 700; color: #76543D; text-decoration: none; }
.co-edit-link:hover { text-decoration: underline; }

.co-items { max-height: 280px; overflow-y: auto; padding-inline-end: 4px; }

.co-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #F0E8D8;
}
.co-item:last-child { border-bottom: none; }

.co-item-img {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #E6DCCB;
    flex-shrink: 0;
}

.co-item-info { flex: 1; min-width: 0; }
.co-item-name { font-size: 13.5px; font-weight: 700; color: #302F2B; }
.co-item-meta { font-size: 12px; color: #77736B; margin-top: 2px; }
.co-item-total { font-size: 13px; font-weight: 800; color: #76543D; white-space: nowrap; }

.co-sum-row { display: flex; justify-content: space-between; gap: 8px; font-size: 13.5px; color: #77736B; padding: 5px 0; }
.co-sum-discount { color: #15803d; }
.co-sum-total {
    font-size: 16px; font-weight: 800; color: #302F2B;
    padding-top: 12px; border-top: 1px solid #E6DCCB; margin-top: 6px;
}
.co-sum-total span:last-child { color: #76543D; font-size: 18px; }

.co-cta {
    text-transform: none;
    font-size: 16px;
    font-weight: 700;
    margin-top: 16px;
    box-shadow: 0 6px 16px rgba(118, 84, 61, 0.25);
}

.co-consent {
    font-size: 12px;
    color: #77736B;
    text-align: center;
    line-height: 1.8;
    margin: 14px 0 0;
}
.co-consent a { color: #76543D; font-weight: 700; text-decoration: underline; }

/* ===== Contact form grid ===== */
.co-field-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.co-field { display: flex; flex-direction: column; }
.co-field--full { grid-column: 1 / -1; }
.co-label { font-size: 12px; font-weight: 600; color: #302F2B; }

/* Checkbox */
.co-check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #302F2B; cursor: pointer; }
.co-check input { accent-color: #76543D; width: 15px; height: 15px; }

/* ===== Mobile sticky CTA ===== */
.co-mobile-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: white;
    border-top: 1px solid #E6DCCB;
    box-shadow: 0 -4px 16px rgba(118, 84, 61, 0.12);
    padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
}
.co-mobile-total { display: flex; flex-direction: column; line-height: 1.3; }
.co-mobile-total span { font-size: 12px; color: #77736B; }
.co-mobile-total strong { font-size: 16px; font-weight: 800; color: #76543D; }
.co-mobile-btn { text-transform: none; font-size: 15px; font-weight: 700; min-width: 150px; }

@media (max-width: 959px) {
    .co-page { padding-bottom: 78px; }
    .co-summary { position: static; }
    .co-steps { display: none; }
}

@media (max-width: 600px) {
    .co-opt-grid { grid-template-columns: 1fr; }
    .co-field-group { grid-template-columns: 1fr; }
    .co-card { padding: 16px; }
}
</style>
