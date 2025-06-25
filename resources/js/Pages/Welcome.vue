<script setup>
import {Head} from "@inertiajs/vue3"
import { useTranslations } from '../translations';

const { __ } = useTranslations();
</script>

<template>
    <Head title="Home" />

    <div class="min-h-screen flex flex-col bg-white text-gray-900">
      <!-- Header -->
      <header class="flex justify-between items-center p-6 bg-pink-100 shadow-md">
        <h1 class="text-2xl font-bold text-blue-600">Landstede Scrum</h1>
        <div>
          <a href="/login/azure" class="px-4 py-3 bg-blue-500 text-white rounded-md hover:bg-blue-600">
            {{__('Login') }}
          </a>
        </div>
      </header>
  
      <!-- Hero Section -->
      <main 
        class="flex-1 flex flex-col items-center justify-center text-center px-6 bg-cover bg-center" 
        style="background-image: url('/heroImage.jpg');"
      >
        <h2 class="text-4xl font-semibold text-blue-700 mb-4 bg-white bg-opacity-75 p-4 rounded-lg">
          Welkom bij Landstede Scrum
        </h2>
        <p class="text-lg text-gray-600 max-w-2xl mb-6 bg-white bg-opacity-75 p-4 rounded-lg">
          Een gezamenlijk takenbord waar groepen hun werk kunnen organiseren.
        </p>
        <a href="/login" class="px-6 py-3 bg-blue-500 text-white rounded-md text-lg hover:bg-blue-600">
          Aan de slag
        </a>
      </main>
  
      <!-- Footer -->
      <footer class="bg-blue-100 text-center p-4 text-gray-700">
        &copy; {{ new Date().getFullYear() }} Landstede. Alle rechten voorbehouden.
      </footer>
    </div>
  </template>