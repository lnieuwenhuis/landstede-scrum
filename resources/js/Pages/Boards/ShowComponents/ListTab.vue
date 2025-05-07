<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import ConfirmModal from './ConfirmModal.vue';

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

// Flatten all cards from all columns into a single array for table display
const allCards = computed(() => {
    const cards = [];
    sortedColumns.value.forEach(column => {
        column.cards.forEach(card => {
            cards.push({
                ...card,
                columnTitle: column.title
            });
        });
    });
    return cards;
});

// Separate active cards (not in Done column)
const activeCards = computed(() => {
    const cards = [];
    sortedColumns.value.forEach(column => {
        if (column.title !== 'Done') {
            column.cards.forEach(card => {
                cards.push({
                    ...card,
                    columnTitle: column.title
                });
            });
        }
    });
    return cards;
});

// Get only cards from Done column
const doneCards = computed(() => {
    const cards = [];
    const doneColumn = sortedColumns.value.find(column => column.title === 'Done');
    
    if (doneColumn) {
        doneColumn.cards.forEach(card => {
            cards.push({
                ...card,
                columnTitle: 'Done'
            });
        });
    }
    return cards;
});

// Add state for showing/hiding done cards
const showDoneCards = ref(false);

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

// Add state for delete confirmation modal
const pendingDeleteCardId = ref(null);
const showDeleteCardModal = ref(false);

// Update handleDeleteCard to show the confirmation modal
const handleDeleteCard = (cardId) => {
    pendingDeleteCardId.value = cardId;
    showDeleteCardModal.value = true;
};

// Add function to confirm and execute card deletion
const confirmDeleteCard = async () => {
    const cardId = pendingDeleteCardId.value;
    if (!cardId) return;
    
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
    
    // Close the modal
    closeDeleteModal();
};

// Add function to close delete modal
const closeDeleteModal = () => {
    showDeleteCardModal.value = false;
    pendingDeleteCardId.value = null;
};

// Get user initials for avatar display
const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ')
        .map(part => part[0])
        .join('')
        .toUpperCase()
        .substring(0, 2);
};
</script>

<template>
    <div class="flex-1">
        <div class="bg-white p-4 shadow-md rounded-lg overflow-hidden">
            <h2 class="text-2xl font-semibold text-gray-800 m-2">Task List</h2>

            <!-- Table View of Tasks -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Assignee
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Title
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Points
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Column
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <!-- Active Cards -->
                        <tr v-for="card in activeCards" :key="card.id" class="hover:bg-gray-50">
                            <!-- Assignee Column -->
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ card.user_name || 'Unassigned' }}
                                </div>
                            </td>
                            
                            <!-- Title Column with Description Tooltip -->
                            <td class="px-4 py-2">
                                <div class="text-sm font-medium text-gray-900 group relative">
                                    {{ card.title }}
                                    <div v-if="card.description" class="hidden group-hover:block absolute z-10 bg-black text-white p-2 rounded text-xs max-w-xs whitespace-normal">
                                        {{ card.description }}
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Points Column -->
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ card.points || 0 }}
                                </span>
                            </td>
                            
                            <!-- Column Title -->
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                {{ card.columnTitle }}
                            </td>
                            
                            <!-- Actions Column -->
                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <button 
                                        @click="openEditModal(card)"
                                        class="text-blue-600 hover:text-blue-900"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                    </button>
                                    <button 
                                        @click="handleDeleteCard(card.id)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Done Cards Dropdown -->
                        <tr v-if="doneCards.length > 0" class="bg-gray-50">
                            <td colspan="5" class="px-4 py-2">
                                <button 
                                    @click="showDoneCards = !showDoneCards" 
                                    class="flex items-center justify-between w-full text-left font-medium text-gray-700"
                                >
                                    <span>Completed Tasks ({{ doneCards.length }})</span>
                                    <svg 
                                        class="h-5 w-5 text-gray-500 transform transition-transform duration-200" 
                                        :class="{ 'rotate-180': showDoneCards }"
                                        xmlns="http://www.w3.org/2000/svg" 
                                        viewBox="0 0 20 20" 
                                        fill="currentColor"
                                    >
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Done Cards (Expandable) -->
                        <template v-if="showDoneCards">
                            <tr v-for="card in doneCards" :key="'done-' + card.id" class="hover:bg-gray-50 bg-gray-100">
                                <!-- Assignee Column -->
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ card.user_name || 'Unassigned' }}
                                    </div>
                                </td>
                                
                                <!-- Title Column with Description Tooltip -->
                                <td class="px-4 py-2">
                                    <div class="text-sm font-medium text-gray-900 group relative">
                                        {{ card.title }}
                                        <div v-if="card.description" class="hidden group-hover:block absolute z-10 bg-black text-white p-2 rounded text-xs max-w-xs whitespace-normal">
                                            {{ card.description }}
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Points Column -->
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ card.points || 0 }}
                                    </span>
                                </td>
                                
                                <!-- Column Title -->
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    {{ card.columnTitle }}
                                </td>
                                
                                <!-- Actions Column -->
                                <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <button 
                                            @click="openEditModal(card)"
                                            class="text-blue-600 hover:text-blue-900"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="handleDeleteCard(card.id)"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        
                        <!-- Empty State -->
                        <tr v-if="activeCards.length === 0 && doneCards.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                No cards found in any column
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Edit Card Modal and Delete Confirmation Modal remain unchanged -->
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
        
        <!-- Add Delete Confirmation Modal -->
        <ConfirmModal
            :show="showDeleteCardModal"
            title="Delete Card"
            message="Are you sure you want to delete this card? This action cannot be undone."
            confirm-text="Delete"
            cancel-text="Cancel"
            confirm-button-class="bg-red-600 hover:bg-red-700"
            @confirm="confirmDeleteCard"
            @cancel="closeDeleteModal"
        />
    </div>
</template>