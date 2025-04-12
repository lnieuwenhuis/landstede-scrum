<script setup>
import CardForm from './CardForm.vue';
import { ref, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    columns: Array,
    board: Object,
    users: Array,
    showDescription: Boolean,
    currentSprint: Object,
    showDescription: {
        type: Boolean,
        default: false
    },
    currentSprint: Object,
});

const userDropdownOpen = ref(null);
const userDropdownPosition = ref({ top: '0px', left: '0px' });

function getInitials(name) {
    if (!name || typeof name !== 'string') {
        return '?'; // Return a placeholder if name is invalid
    }

    let nameToProcess = name.trim();

  // Check if the name starts with "Student " and remove it if it does
    if (nameToProcess.startsWith('Student ')) {
        nameToProcess = nameToProcess.substring('Student '.length).trim();
    }

    // Remove potential parentheses around names, e.g., "(firstname)" -> "firstname"
    // This regex replaces leading '(' and trailing ')' from words
    nameToProcess = nameToProcess.replace(/\b\(/g, '').replace(/\)\b/g, '');

    // Split the remaining name into parts based on spaces
    const nameParts = nameToProcess.split(' ').filter((part) => part.length > 0); // Filter out empty strings from multiple spaces

    if (nameParts.length === 0) {
        // If nothing is left, maybe fallback to the first char of the original name?
        return name.trim()[0]?.toUpperCase() || '?';
    } else if (nameParts.length === 1) {
        // If only one part (e.g., "Admin", or just "Firstname"), take its first letter
        return nameParts[0][0].toUpperCase();
    } else {
        // If two or more parts, take the first letter of the first part
        // and the first letter of the *last* part (to handle middle names)
        const firstInitial = nameParts[0][0];
        const lastInitial = nameParts[nameParts.length - 1][0];
        return (firstInitial + lastInitial).toUpperCase();
    }
}

const toggleUserDropdown = (cardId, event) => {
    if (userDropdownOpen.value === cardId) {
        userDropdownOpen.value = null;
        return;
    }
    
    userDropdownOpen.value = cardId;
    
    // We need to wait for the next tick to get the element position
    // after the modal is rendered
    nextTick(() => {
        const avatarElement = event.target.closest('.relative');
        if (avatarElement) {
            const rect = avatarElement.getBoundingClientRect();
            const dropdownHeight = 240;
            const viewportHeight = window.innerHeight;
            
            const spaceBelow = viewportHeight - rect.bottom;
            
            if (spaceBelow < dropdownHeight) {
                // Not enough space below, position above but not too far
                userDropdownPosition.value = {
                    top: `${rect.top + window.scrollY - 160}px`,
                    left: `${rect.left + window.scrollX - 100}px` // Offset to center better
                };
            } else {
                // Enough space below, position below as before
                userDropdownPosition.value = {
                    top: `${rect.bottom + window.scrollY + 5}px`,
                    left: `${rect.left + window.scrollX - 100}px` // Offset to center better
                };
            }
        }
    });
};

// assign user to card
const assignUserToCard = async (cardId, userId) => {
    loading.value = true;
    try {
        const response = await axios.post(`/api/cards/${cardId}/assign`, {
            user_id: userId
        });
        
        if (response.data.success) {
            // Update the card in the local state
            const updatedColumns = props.columns.map(column => {
                const updatedCards = column.cards.map(card => {
                    if (card.id === cardId) {
                        return { ...card, user_id: userId };
                    }
                    return card;
                });
                return { ...column, cards: updatedCards };
            });
            
            emit('columns-updated', updatedColumns);
            toast.success(userId ? 'User assigned successfully' : 'User unassigned successfully');
        } else {
            throw new Error('Failed to assign user');
        }
    } catch (error) {
        toast.error('Failed to assign user to card');
    } finally {
        loading.value = false;
        userDropdownOpen.value = null; // Close dropdown after action
    }
};

// close dropdown when clicking outside
onMounted(() => {
    document.addEventListener('click', (e) => {
        if (userDropdownOpen.value && !e.target.closest('.relative')) {
            userDropdownOpen.value = null;
        }
    });
});

const isColumnLocked = computed(() => {
    return (columnTitle) => {
        if (!props.currentSprint || !props.currentSprint.status) {
            return false;
        }
        
        const status = props.currentSprint.status;
        
        if (status === 'planning') {
            // When sprint is planning, all columns except Project Backlog and Sprint Backlog are locked
            return columnTitle !== 'Project Backlog' && columnTitle !== 'Sprint Backlog';
        } else if (status === 'locked' || status === 'checked') {
            // When sprint is locked or checked, all columns are locked
            return true;
        }
        
        return false;
    };
});

const emit = defineEmits([
    'columns-updated',
    'toggle-description'
]);

// Toggle description visibility
const toggleDescription = () => {
    emit('toggle-description');
};

const regularColumns = computed(() => {
    return props.columns.filter(column => column.title !== 'Done');
});

const doneColumn = computed(() => {
    return props.columns.find(column => column.title === 'Done');
});

// Local state management
const cardOpen = ref({});
const cardTitle = ref('');
const cardDesc = ref('');
const cardPoints = ref(0);
const cardEditing = ref(null);
const columnEditing = ref(null);
const newColumnTitle = ref('');
const newColumnDone = ref(false);
const showNewColumn = ref(false);
const currentDragCardId = ref(null);
const currentDragColumnId = ref(null);
const currentDragSourceColumnId = ref(null);

// Card drag and drop handlers
const handleDragStart = (event, cardId, columnId) => {
    const sourceColumn = props.columns.find(col => col.id === columnId);
    // Prevent dragging from locked columns
    if (!sourceColumn || isColumnLocked.value(sourceColumn.title)) {
        event.preventDefault();
        return;
    }
    
    currentDragCardId.value = cardId;
    currentDragSourceColumnId.value = columnId;
    event.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (event, columnId) => {
    event.preventDefault();
    currentDragColumnId.value = columnId;
};

const handleDragLeave = () => {
    currentDragColumnId.value = null;
};

const handleDrop = async (event, targetColumnId) => {
    event.preventDefault();
    
    const targetColumn = props.columns.find(col => col.id === targetColumnId);
    if (!targetColumn || isColumnLocked.value(targetColumn.title)) {
        toast.error('Cannot move cards to locked columns');
        currentDragColumnId.value = null;
        return;
    }
    
    if (currentDragCardId.value && currentDragSourceColumnId.value && targetColumnId) {
        await handleMoveCard({
            cardId: currentDragCardId.value,
            sourceColumnId: currentDragSourceColumnId.value,
            targetColumnId
        });
    }
    
    // Reset drag state
    currentDragCardId.value = null;
    currentDragSourceColumnId.value = null;
    currentDragColumnId.value = null;
};

// Card form handlers
const toggleAddCard = (columnId) => {
    cardOpen.value = { ...cardOpen.value, [columnId]: !cardOpen.value[columnId] };
    resetForm();
};

const toggleEditCard = (card) => {
    cardEditing.value = card.id;
    cardTitle.value = card.title;
    cardDesc.value = card.description;
    cardPoints.value = card.points;
};

const resetForm = () => {
    cardTitle.value = '';
    cardDesc.value = '';
    cardPoints.value = 0;
    cardEditing.value = null;
};

// Column form handlers
const toggleNewColumn = () => {
    showNewColumn.value = !showNewColumn.value;
    resetNewColumnForm();
};

const toggleEditColumn = (column) => {
    columnEditing.value = column.id;
    newColumnTitle.value = column.title;
};

const resetNewColumnForm = () => {
    newColumnTitle.value = '';
    newColumnDone.value = false;
    columnEditing.value = null;
};

// API handlers
const handleUpdateCard = async ({ cardId, title, description, points }) => {
    loading.value = true;
    try {
        const response = await axios.post(`/api/updateCard/${cardId}`, {
            title,
            description,
            points
        });

        if (!response.data.card) {
            throw new Error('Failed to update card');
        }
        
        // Update from server response
        const updatedColumns = [...props.columns];
        updatedColumns.forEach(column => {
            const cardIndex = column.cards.findIndex(c => c.id === cardId);
            if (cardIndex !== -1) {
                column.cards[cardIndex] = response.data.card;
            }
        });
        emit('columns-updated', updatedColumns);

        toast.success('Card updated successfully');
    } catch (error) {
        toast.error('Failed to update card');
    } finally {
        loading.value = false;
        cardEditing.value = null;
        resetForm();
    }
};

const loading = ref(false);

const handleAddCard = async ({ columnId, title, description, points }) => {
    loading.value = true;
    try {
        const response = await axios.post(`/api/addCardToColumn/${columnId}`, {
            title, description, points
        });

        if (response.data.card) {
            const columnIndex = props.columns.findIndex(col => col.id === columnId);
            if (columnIndex > -1) {
                const updatedColumns = [...props.columns];
                updatedColumns[columnIndex].cards.push(response.data.card);
                emit('columns-updated', updatedColumns);
            }
            cardOpen.value[columnId] = false;
            toast.success('Card added successfully');
        }
    } catch (error) {
        toast.error('Failed to add card');
    } finally {
        loading.value = false;
        resetForm();
    }
};

const handleDeleteCard = async (cardId) => {
    loading.value = true;
    try {
        const response = await axios.post(`/api/deleteCard/${cardId}`);
        if (response.data.message) {
            // Create a deep copy of columns with the card removed
            const updatedColumns = props.columns.map(column => ({
                ...column,
                cards: column.cards.filter(card => card.id !== cardId)
            }));
            
            // Emit the updated columns array
            emit('columns-updated', updatedColumns);
            toast.success(response.data.message);
        }
    } catch (error) {
        toast.error('Failed to delete card');
    } finally {
        loading.value = false;
    }
};

const handleAddColumn = async ({ title, done, board_id, status }) => {
    loading.value = true;
    try {
        const response = await axios.post('/api/addColumn', {
            title, done, board_id, status
        });

        if (response.data.column) {
            const updatedColumns = [...props.columns, response.data.column];
            emit('columns-updated', updatedColumns);
            toast.success('Column added successfully!');
            resetNewColumnForm();
        }
    } catch (error) {
        toast.error('Failed to add column');
    } finally {
        loading.value = false;
        showNewColumn.value = false; 
    }
};

const handleUpdateColumn = async ({ id, title }) => {
    loading.value = true;
    try {
        const response = await axios.post(`/api/updateColumn`, {
            column_id: id,
            title
        });
        
        if (response.data.column) {
            const updatedColumns = props.columns.map(col => 
                col.id === id ? { ...col, title: response.data.column.title } : col
            );
            emit('columns-updated', updatedColumns);
            toast.success('Column updated successfully');
            resetNewColumnForm();
        }
    } catch (error) {
        toast.error('Failed to update column');
    } finally {
        loading.value = false;
    }
};

const handleDeleteColumn = async (columnId) => {
    loading.value = true;
    try {
        const response = await axios.post(`/api/deleteColumn`, {
            column_id: columnId
        });

        if (!response.data.message) {
            throw new Error('Failed to delete column');
        }
        
        // Remove column after successful API response
        const filteredColumns = props.columns.filter(column => column.id !== columnId);
        emit('columns-updated', filteredColumns);
        
        toast.success(response.data.message);
    } catch (error) {
        toast.error('Failed to delete column');
    } finally {
        loading.value = false;
    }
};

// Card movement handler
const handleMoveCard = async ({ cardId, sourceColumnId, targetColumnId }) => {
    // Check if columns array exists
    if (!props.columns || !Array.isArray(props.columns)) {
        toast.error('Columns data is not available');
        return;
    }
    
    // Find source and target columns with null checks
    const sourceColumn = props.columns.find(col => col && col.id == sourceColumnId);
    const targetColumn = props.columns.find(col => col && col.id == targetColumnId);
    
    // Validate columns exist
    if (!sourceColumn || !targetColumn) {
        toast.error('Source or target column not found');
        return;
    }
    
    // Ensure cards arrays exist
    if (!Array.isArray(sourceColumn.cards) || !Array.isArray(targetColumn.cards)) {
        toast.error('Cards data is not available');
        return;
    }
    
    // Find card in source column
    const cardIndex = sourceColumn.cards.findIndex(c => c && c.id == cardId);
    if (cardIndex === -1) {
        toast.error('Card not found in source column');
        return;
    }

    // Create a copy of the card to move
    const movedCard = { ...sourceColumn.cards[cardIndex] };
    
    try {
        // Remove from source
        sourceColumn.cards.splice(cardIndex, 1);
        
        // Add to target
        targetColumn.cards.push(movedCard);
        
        // Make API call after optimistic update
        await axios.post(`/api/cards/${cardId}/move`, {
            column_id: targetColumnId
        });
        
        toast.success('Card moved successfully');
        emit('columns-updated', props.columns);
        emit('burndown-update');
    } catch (error) {
        // Revert changes if API call fails
        if (Array.isArray(sourceColumn.cards) && Array.isArray(targetColumn.cards)) {
            sourceColumn.cards.splice(cardIndex, 0, movedCard);
            const targetCardIndex = targetColumn.cards.findIndex(c => c && c.id == cardId);
            if (targetCardIndex !== -1) {
                targetColumn.cards.splice(targetCardIndex, 1);
            }
        }
        
        toast.error('Failed to move card');
        emit('columns-updated', props.columns);
    }
};

const isUserAssigned = (cardId, userId) => {
    for (const column of props.columns) {
        const card = column.cards.find(c => c.id === cardId);
        if (card) {
            return card.user_id === userId;
        }
    }
    return false;
};

</script>

<template>
    <div class="flex justify-center">
        <div class="bg-white p-6 rounded-lg shadow w-full">
            <!-- Board header -->
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-semibold text-gray-800">{{ board.title }}</h1>
                    <span v-if="currentSprint" class="text-gray-600">
                        <span>({{ currentSprint.title }})</span>
                    </span>
                    <span v-else class="text-sm text-gray-600">
                        <span class="font-medium">No active sprint</span>
                    </span>
                </div>
                <button 
                    @click="toggleDescription" 
                    class="text-gray-500 hover:text-gray-700 focus:outline-none"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path v-if="!showDescription" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        <path v-else fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div v-if="showDescription" class="py-2 rounded-lg">
                <p class="text-gray-500">{{ board.description }}</p>
            </div>

            <!-- Columns container -->
            <div class="flex justify-center">
                <div 
                    class="flex space-x-4 overflow-x-auto pb-4 w-full"
                    :class="{ '': showDescription, 'mt-6': !showDescription }"
                >
                    <!-- Regular columns -->
                    <div 
                        v-for="column in regularColumns" 
                        :key="column.id"
                        class="flex-shrink-0 w-72 rounded-lg shadow"
                        :class="{ 
                            'bg-gray-100': !isColumnLocked(column.title),
                            'bg-gray-200': isColumnLocked(column.title),
                            'border-2 border-blue-400': currentDragColumnId === column.id 
                        }"
                        @dragover.prevent="handleDragOver($event, column.id)"
                        @dragleave="handleDragLeave"
                        @drop.prevent="handleDrop($event, column.id)"
                    >
                        <!-- Regular column header -->
                        <div class="p-3 bg-gray-200 rounded-t-lg flex justify-between items-center">
                            <div v-if="columnEditing === column.id" class="flex-1 relative">
                                <input 
                                    v-model="newColumnTitle" 
                                    @keyup.enter="handleUpdateColumn({ id: column.id, title: newColumnTitle })"
                                    :disabled="loading"
                                    class="w-full px-2 py-1 border border-gray-300 rounded pr-8"
                                    placeholder="Column title"
                                />
                                <span v-if="loading" class="absolute right-3 top-1/2 -translate-y-1/2">⏳</span>
                            </div>
                            <div v-else class="flex items-center justify-between w-full">
                                <h3 class="font-medium text-gray-700">{{ column.title }}</h3>
                                <span v-if="isColumnLocked(column.title)" class="flex items-center text-sm text-red-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Locked</span>
                                </span>
                            </div>
                            <div v-if="column.user_created !== 0 && !isColumnLocked(column.title)" class="flex space-x-1">
                                <button 
                                    @click="toggleEditColumn(column)"
                                    class="p-1 text-gray-500 hover:text-gray-700 focus:outline-none"
                                    :disabled="loading"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                    </svg>
                                </button>
                                <button
                                    @click="handleDeleteColumn(column.id)"
                                    class="p-1 text-gray-500 hover:text-red-600 focus:outline-none"
                                    :disabled="loading"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Cards container -->
                        <div class="p-2 max-h-[calc(100vh-12rem)] overflow-y-auto">
                            <!-- Cards -->
                            <div 
                                v-for="card in column.cards" 
                                :key="card.id"
                                class="bg-white p-3 rounded shadow-sm mb-2"
                                :class="{ 'cursor-pointer': !isColumnLocked(column.title) }"
                                :draggable="!isColumnLocked(column.title)"
                                @dragstart="handleDragStart($event, card.id, column.id)"
                            >
                                <!-- Show card editing form when this card is being edited -->
                                <div v-if="cardEditing === card.id">
                                    <!-- In both CardForm instances (add card and edit card) -->
                                    <CardForm 
                                        :loading="loading"
                                        :column-id="column.id"
                                        :initial-title="cardTitle"
                                        :initial-description="cardDesc"
                                        :initial-points="cardPoints"
                                        :is-editing="true"
                                        @save="(data) => handleUpdateCard({  // Remove resetForm from here
                                            cardId: card.id,
                                            columnId: column.id,
                                            title: data.title,
                                            description: data.description,
                                            points: data.points
                                        })"
                                        @cancel="resetForm"
                                    />
                                </div>
                                <!-- Show normal card when not editing -->
                                <div v-else>
                                    <div class="flex justify-between items-start">
                                        <h4 class="font-medium text-gray-800">{{ card.title }}</h4>
                                        <div class="flex space-x-1">
                                            <button 
                                                @click="toggleEditCard(card)"
                                                class="p-1 text-gray-500 hover:text-gray-700 focus:outline-none"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                </svg>
                                            </button>
                                            <button 
                                                @click="handleDeleteCard(card.id)"
                                                class="p-1 text-gray-500 hover:text-red-600 focus:outline-none"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 text-sm mt-1">{{ card.description }}</p>
                                    <div class="flex justify-between items-center mt-2">
                                        <span class="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-800 rounded">{{ card.points }} points</span>
                                        <!-- User avatar with dropdown -->
                                        <div class="relative">
                                            <div 
                                                @click="toggleUserDropdown(card.id, $event)"
                                                class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium cursor-pointer border-gray-400 border-2"
                                            >
                                                {{ getInitials(props.users.find(u => u.id === card.user_id).name) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add card form -->
                            <div v-if="cardOpen[column.id]">
                                <CardForm 
                                    :loading="loading"
                                    :column-id="column.id"
                                    :key="`form-${column.id}-${Date.now()}`"
                                    @save="handleAddCard"
                                    @cancel="toggleAddCard(column.id)"
                                />
                            </div>
                            
                            <!-- Add card button -->
                            <button 
                                @click="toggleAddCard(column.id)"
                                class="w-full py-2 px-3 text-sm text-gray-600 hover:bg-gray-200 rounded flex items-center justify-center mt-2"
                                :disabled="isColumnLocked(column.title)"
                                :class="{ 'opacity-50 cursor-not-allowed': isColumnLocked(column.title) }"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                Add Card
                            </button>
                        </div>
                    </div>
                    
                    <!-- Done column -->
                    <div 
                        v-if="doneColumn"
                        :key="doneColumn.id"
                        class="flex-shrink-0 w-72 rounded-lg shadow"
                        :class="{ 
                            'bg-green-50': !isColumnLocked(doneColumn.title), 
                            'bg-green-100': isColumnLocked(doneColumn.title),
                            'border-2 border-blue-400': currentDragColumnId === doneColumn.id 
                        }"
                        @dragover.prevent="handleDragOver($event, doneColumn.id)"
                        @dragleave="handleDragLeave"
                        @drop.prevent="handleDrop($event, doneColumn.id)"
                    >
                        <!-- Done column header -->
                        <div class="p-3 bg-green-100 rounded-t-lg flex justify-between items-center">
                            <div class="flex items-center justify-between w-full">
                                <h3 class="font-medium text-green-800">{{ doneColumn.title }}</h3>
                                <span v-if="isColumnLocked(doneColumn.title)" class="flex items-center text-sm text-red-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Locked</span>
                                </span>
                            </div>
                            <div v-if="doneColumn.user_created !== 0 && !isColumnLocked(doneColumn.title)" class="flex space-x-1">
                                <button 
                                    @click="toggleEditColumn(doneColumn)"
                                    class="p-1 text-gray-500 hover:text-gray-700 focus:outline-none"
                                />
                                <button 
                                    @click="handleDeleteColumn(doneColumn.id)"
                                    class="p-1 text-gray-500 hover:text-red-600 focus:outline-none"
                                    :disabled="loading"
                                />
                            </div>
                        </div>
                        
                        <!-- Cards container -->
                        <div class="p-2 max-h-[calc(100vh-12rem)] overflow-y-auto">
                            <!-- Cards -->
                            <div 
                                v-for="card in doneColumn.cards" 
                                :key="card.id"
                                class="bg-white p-3 rounded shadow-sm mb-2"
                                :class="{ 'cursor-pointer': !isColumnLocked(doneColumn.title) }"
                                :draggable="!isColumnLocked(doneColumn.title)"
                                @dragstart="handleDragStart($event, card.id, doneColumn.id)"
                            >
                                <div class="flex justify-between items-start">
                                    <h4 class="font-medium text-gray-800">{{ card.title }}</h4>
                                    <div v-if="!isColumnLocked(doneColumn.title)" class="flex space-x-1">
                                        <button 
                                            @click="toggleEditCard(card)"
                                            class="p-1 text-gray-500 hover:text-gray-700 focus:outline-none"
                                        />
                                        <button 
                                            @click="handleDeleteCard(card.id)"
                                            class="p-1 text-gray-500 hover:text-red-600 focus:outline-none"
                                        />
                                    </div>
                                </div>
                                <p class="text-gray-600 text-sm mt-1">{{ card.description }}</p>                                
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-800 rounded">{{ card.points }} points</span>
                                    <!-- User avatar with dropdown -->
                                    <div class="relative">
                                        <div 
                                            @click="toggleUserDropdown(card.id, $event)"
                                            class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium cursor-pointer border-gray-400 border-2"
                                        >
                                            {{ getInitials(props.users.find(u => u.id === card.user_id).name) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add card button for Done column -->
                            <button 
                                @click="toggleAddCard(doneColumn.id)"
                                class="w-full py-2 px-3 text-sm text-gray-600 hover:bg-gray-200 rounded flex items-center justify-center mt-2"
                                :disabled="isColumnLocked(doneColumn.title)"
                                :class="{ 'opacity-50 cursor-not-allowed': isColumnLocked(doneColumn.title) }"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                Add Card
                            </button>
                        </div>
                    </div>
                    
                    <!-- Add column form -->
                    <div v-if="showNewColumn" class="flex-shrink-0 w-72 bg-white p-3 rounded-lg shadow">
                        <h3 class="font-medium text-gray-700 mb-2">Add New Column</h3>
                        <div class="relative">
                            <input 
                                v-model="newColumnTitle" 
                                class="w-full px-3 py-2 border border-gray-300 rounded mb-2 pr-8"
                                placeholder="Column title"
                                :disabled="loading"
                            />
                            <span v-if="loading" class="absolute right-3 top-3">⏳</span>
                        </div>
                        <div class="flex items-center mb-3">
                            <input 
                                id="done-column" 
                                type="checkbox" 
                                v-model="newColumnDone" 
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                :disabled="loading"
                            />
                            <label for="done-column" class="ml-2 block text-sm text-gray-900">
                                This is a "Done" column
                            </label>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button 
                                @click="toggleNewColumn" 
                                class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded"
                                :disabled="loading"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="handleAddColumn({
                                    title: newColumnTitle,
                                    done: newColumnDone,
                                    board_id: board.id,
                                    status: board.status
                                })"
                                class="px-3 py-1 text-sm bg-blue-600 text-white hover:bg-blue-700 rounded relative"
                                :disabled="loading"
                            >
                                <span v-if="loading">⏳</span>
                                {{ loading ? 'Adding...' : 'Add Column' }}
                            </button>
                        </div>
                    </div>
                    
                    <!-- Add column button -->
                    <button 
                        v-else
                        @click="toggleNewColumn"
                        class="flex-shrink-0 w-72 bg-gray-100 rounded-lg shadow-sm border-2 border-dashed border-gray-300 flex items-center justify-center p-6 hover:bg-gray-200"
                    >
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <span class="mt-2 block text-sm font-medium text-gray-600">Add Column</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User assignment modal -->
    <div v-if="userDropdownOpen" class="absolute inset-0 z-50" @click.self="userDropdownOpen = null">
        <div 
            class="bg-white rounded-md shadow-lg w-64 absolute"
            :style="userDropdownPosition"
        >
            <div class="py-1">
                <div v-for="user in props.users" :key="user.id" 
                    @click="assignUserToCard(userDropdownOpen, user.id)"
                    class="px-4 py-2 text-sm text-gray-700 hover:bg-blue-100 cursor-pointer flex items-center"
                    :class="{ 'bg-green-100': isUserAssigned(userDropdownOpen, user.id) }"
                >
                    <div class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium mr-2">
                        {{ getInitials(user.name) }}
                    </div>
                    {{ user.name }}
                </div>
            </div>
        </div>
    </div>
</template>
