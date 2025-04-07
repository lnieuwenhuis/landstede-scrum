<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    columns: Array,
    board: Object,
    showDescription: {
        type: Boolean,
        default: false
    }
});

// Add computed properties to separate regular columns from the Done column
const sortedColumns = computed(() => {
    const regularColumns = props.columns.filter(column => column.title !== 'Done');
    const doneColumn = props.columns.find(column => column.title === 'Done');
    
    return [...regularColumns, ...(doneColumn ? [doneColumn] : [])];
});

const emit = defineEmits(['toggle-description']);

const expandedColumns = ref({});

// Add state for edit modal
const editModalOpen = ref(false);
const editingCard = ref(null);
const editForm = ref({
    title: '',
    description: '',
    points: 0
});

// Function to open edit modal
const openEditModal = (card) => {
    editingCard.value = card;
    editForm.value = {
        title: card.title,
        description: card.description,
        points: card.points
    };
    editModalOpen.value = true;
};

// Function to save edited card
const saveEditedCard = async () => {
    if (!editingCard.value) return;
    
    try {
        const response = await axios.post(`/api/updateCard/${editingCard.value.id}`, editForm.value);
        
        if (response.data.message) {
            // Update the card in the UI
            props.columns.forEach(column => {
                const cardIndex = column.cards.findIndex(c => c.id === editingCard.value.id);
                if (cardIndex !== -1) {
                    column.cards[cardIndex].title = editForm.value.title;
                    column.cards[cardIndex].description = editForm.value.description;
                    column.cards[cardIndex].points = editForm.value.points;
                }
            });
            
            toast.success('Card updated successfully');
            closeEditModal();
        } else {
            throw new Error(response.data.error || 'Failed to update card');
        }
    } catch (error) {
        toast.error(error.error || 'Failed to update card');
    }
};

// Function to close edit modal
const closeEditModal = () => {
    editModalOpen.value = false;
    editingCard.value = null;
};

const handleDeleteCard = async (cardId) => {
    if (!confirm('Are you sure you want to delete this card?')) {
        return;
    }
    
    // Find the card and its column
    let deletedCard;
    let deletedFromColumn;
    
    props.columns.forEach(column => {
        const cardIndex = column.cards.findIndex(c => c.id === cardId);
        if (cardIndex !== -1) {
            deletedCard = column.cards[cardIndex];
            deletedFromColumn = column;
            column.cards = column.cards.filter(card => card.id !== cardId);
        }
    });

    try {
        const response = await axios.post(`/api/deleteCard/${cardId}`);
        if (!response.data.message) {
            throw new Error('Failed to delete card');
        }
        
        toast.success(response.data.message);
    } catch (error) {
        // Revert changes if failed
        if (deletedCard && deletedFromColumn) {
            deletedFromColumn.cards.push(deletedCard);
        }
        toast.error('Failed to delete card');
    }
};
</script>

<template>
    <div class="flex-1">
        <div class="bg-white p-4 shadow-md rounded-lg overflow-hidden">
            <h2 class="text-2xl font-semibold text-gray-800 m-2">Task List</h2>

            <div class="divide-y divide-gray-200">
                <div v-for="column in sortedColumns" :key="column.id" class="p-4">
                    <!-- Column Header -->
                    <div 
                        @click="expandedColumns[column.id] = !expandedColumns[column.id]"
                        class="flex justify-between items-center cursor-pointer"
                    >
                        <h3 class="text-lg font-medium text-gray-900">{{ column.title }}</h3>
                        <div class="flex items-center">
                            <span class="text-gray-500 mr-2">{{ column.cards.length }} cards</span>
                            <svg 
                                xmlns="http://www.w3.org/2000/svg" 
                                class="h-5 w-5 text-gray-500 transition-transform duration-200"
                                :class="{ 'transform rotate-180': expandedColumns[column.id] }"
                                viewBox="0 0 20 20" 
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Cards List (Expandable) -->
                    <div v-if="expandedColumns[column.id]" class="mt-4 space-y-3">
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
                                        <span class="text-sm text-gray-500">Points: {{ card.points }}</span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <!-- Delete button for cards -->
                                    <button 
                                        @click="handleDeleteCard(card.id)"
                                        class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <!-- Edit button for cards -->
                                    <button 
                                        @click="openEditModal(card)"
                                        class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="column.cards.length === 0" class="text-gray-500 text-center py-4">
                            No cards in this column
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Card Modal -->
        <div v-if="editModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Card</h3>
                
                <div class="space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input 
                            type="text" 
                            id="title" 
                            v-model="editForm.title" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea 
                            id="description" 
                            v-model="editForm.description" 
                            rows="3" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                    </div>
                    
                    <div>
                        <label for="points" class="block text-sm font-medium text-gray-700">Points</label>
                        <input 
                            type="number" 
                            id="points" 
                            v-model="editForm.points" 
                            min="0"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button 
                        @click="closeEditModal" 
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="saveEditedCard" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>