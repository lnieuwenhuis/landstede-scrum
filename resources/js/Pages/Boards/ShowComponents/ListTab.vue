<script setup>
import { ref } from 'vue';

defineProps({
    columns: Array
});

const emit = defineEmits(['delete-card']);

const expandedColumns = ref({});
</script>

<template>
    <div class="flex-1">
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="divide-y divide-gray-200">
                <div v-for="column in columns" :key="column.id" class="p-6">
                    <div 
                        @click="expandedColumns[column.id] = !expandedColumns[column.id]"
                        class="flex justify-between items-center cursor-pointer"
                    >
                        <h2 class="text-xl font-semibold text-gray-800">{{ column.title }}</h2>
                        <svg 
                            xmlns="http://www.w3.org/2000/svg" 
                            class="h-5 w-5 text-gray-500 transition-transform duration-200"
                            :class="expandedColumns[column.id] ? 'transform rotate-180' : ''"
                            viewBox="0 0 20 20" 
                            fill="currentColor"
                        >
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div v-show="expandedColumns[column.id]" class="space-y-4 mt-4">
                        <div 
                            v-for="card in column.cards" 
                            :key="card.id"
                            class="bg-black/5 p-4 rounded-lg shadow-sm transition-all duration-200 hover:bg-black/10"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-gray-700 font-medium">{{ card.title }}</p>
                                    <p class="text-gray-500 mt-1">{{ card.description }}</p>
                                    <div class="mt-2 flex items-center">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">
                                            {{ card.points || 0 }} points
                                        </span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <button 
                                        @click="emit('delete-card', card.id)"
                                        class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <!-- Edit button for cards -->
                                    <button 
                                        class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="column.cards.length === 0" class="py-4 text-center text-gray-500 italic bg-black/5 rounded-lg">
                            No cards in this column
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>