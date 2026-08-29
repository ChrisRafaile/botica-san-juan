<template>
  <div class="min-h-screen">
    <!-- Header -->
    <Header />

    <!-- Services Hero Section -->
    <ServicesHero />

    <!-- Services Grid Section -->
    <ServicesGrid />

    <!-- Agora Program Section -->
    <ServicesAgora />

    <!-- Product Categories Section -->
    <ServicesCategories />

    <!-- Footer -->
    <Footer />
  </div>
</template>

<script setup lang="ts">
import Header from '../components/Header.vue'
import Footer from '../components/Footer.vue'
import ServicesHero from '../components/ServicesHero.vue'
import ServicesGrid from '../components/ServicesGrid.vue'
import ServicesAgora from '../components/ServicesAgora.vue'
import ServicesCategories from '../components/ServicesCategories.vue'
</script>