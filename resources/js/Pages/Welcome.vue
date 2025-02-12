<template>
    <div class="min-h-screen flex flex-col bg-white text-gray-900">
      <!-- Header -->
      <header class="flex justify-between items-center p-6 bg-pink-100 shadow-md">
        <h1 class="text-2xl font-bold text-blue-600">Landstede Scrum</h1>
        <div>
          <button class="px-4 py-2 bg-blue-500 text-white rounded-md mr-2 hover:bg-blue-600">
            Student Login
          </button>
          <button class="px-4 py-2 bg-pink-500 text-white rounded-md hover:bg-pink-600">
            Teacher Login
          </button>
        </div>
      </header>
  
      <!-- Hero Section -->
      <main 
        class="flex-1 flex flex-col items-center justify-center text-center px-6 bg-cover bg-center" 
        style="background-image: url('/heroImage.jpg');"
      >
        <h2 class="text-4xl font-semibold text-blue-700 mb-4 bg-white bg-opacity-75 p-4 rounded-lg">
          Welcome to Landstede Scrum
        </h2>
        <p class="text-lg text-gray-600 max-w-2xl mb-6 bg-white bg-opacity-75 p-4 rounded-lg">
          A streamlined platform for students and teachers to collaborate on projects with an intuitive Scrum-based workflow.
        </p>
        <a href="/login" class="px-6 py-3 bg-blue-500 text-white rounded-md text-lg hover:bg-blue-600">
          Get Started
        </a>
      </main>
  
      <!-- Footer -->
      <footer class="bg-blue-100 text-center p-4 text-gray-700">
        &copy; {{ new Date().getFullYear() }} Landstede. All rights reserved.
      </footer>
    </div>
  </template>
  
  <style scoped>
  </style>
  