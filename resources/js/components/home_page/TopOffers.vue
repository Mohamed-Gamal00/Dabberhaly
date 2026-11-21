<template>
  <div class="top-offers py-9" :style="{ backgroundColor: bgColor }" v-if="offers.length">
    <v-container fluid>
      <div class="offers-grid">
        <div
          v-for="(offer, index) in offers"
          :key="offer.id"
          class="offer-card"
          :class="index < 3 ? 'offer-card--top' : 'offer-card--bottom'"
        >
          <div class="offer-image-wrapper">
            <img
              :src="offer.image_url"
              :alt="offer.description || offer.title"
              :title="offer.description"
              class="offer-image"
              loading="lazy"
              decoding="async"
              @error="onImgError"
            />
          </div>
        </div>
      </div>
    </v-container>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
  bgColor: {
    type: String,
    default: '#E6D3BB' // Latte Beige fallback
  }
});

const offers = computed(() => usePage().props.topOffers || []);

// Local placeholder (Vanilla Latte) shown when an offer image fails to load.
const IMG_FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#F7F0E5"/><g fill="none" stroke="#76543D" stroke-width="6"><path d="M55 75 L100 55 L145 75 L145 130 L100 150 L55 130 Z"/><path d="M55 75 L100 95 L145 75 M100 95 L100 150"/></g></svg>`
);

function onImgError(e) {
    if (e.target.src !== IMG_FALLBACK) e.target.src = IMG_FALLBACK;
}
</script>

<style scoped>
.offers-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 20px;
}

.offer-card {
  position: relative;
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
}

.offer-card--top {
  grid-column: span 2;
}

.offer-card--bottom {
  grid-column: span 3;
}

/* Aspect ratio container to prevent overlapping */
.offer-image-wrapper {
  position: relative;
  width: 100%;
  padding-bottom: 60%; /* 5:3 aspect ratio */
  overflow: hidden;
  border-radius: 12px;
  background: var(--brand-border);
}

.offer-image {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  transition: transform 0.5s ease-in-out;
}

.offer-card:hover .offer-image {
  transform: scale(1.05);
}

@media (max-width: 959px) {
  .offers-grid {
    gap: 16px;
  }

  .offer-card--top {
    grid-column: span 3;
  }

  .offer-card--top:nth-child(3) {
    grid-column: span 6;
  }

  .offer-card--bottom {
    grid-column: span 6;
  }
}

@media (max-width: 599px) {
  .offers-grid {
    gap: 12px;
  }

  .offer-card--top,
  .offer-card--bottom {
    grid-column: span 6;
  }

  .offer-image-wrapper {
    padding-bottom: 65%; /* Slightly taller on mobile */
  }
}
</style>
