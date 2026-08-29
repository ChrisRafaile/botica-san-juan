<template>
  <div class="min-h-screen">
    <!-- Header -->
    <Header />

    <!-- About Hero Section -->
    <AboutHero />

    <!-- About Content Section -->
    <AboutContent />

    <!-- Testimonials Section -->
    <AboutTestimonials />

    <!-- Delivery Information -->
    <AboutDelivery />

    <!-- Contact Information -->
    <AboutContact />

    <!-- Footer -->
    <Footer />
  </div>
</template>

<script setup lang="ts">
import Header from '../components/Header.vue'
import Footer from '../components/Footer.vue'
import AboutHero from '../components/AboutHero.vue'
import AboutContent from '../components/AboutContent.vue'
import AboutTestimonials from '../components/AboutTestimonials.vue'
import AboutDelivery from '../components/AboutDelivery.vue'
import AboutContact from '../components/AboutContact.vue'
</script>
