<script setup>
import CardForm from './CardForm.vue';
import { ref, computed } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    columns: Array,
    board: Object,
    users: Array,
    showDescription: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits([
    'columns-updated',
    'burndown-update',
    'toggle-description'
]);

// Toggle description visibility
const toggleDescription = () => {
    emit('toggle-description');
};

// Add computed properties to separate regular columns from the Done column
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
    currentDragCardId.value = cardId;
    currentDragSourceColumnId.value = columnId;
    event.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (event, columnId) => {
    currentDragColumnId.value = columnId;
};

const handleDragLeave = () => {
    currentDragColumnId.value = null;
};

const handleDrop = async (event, targetColumnId) => {
    if (currentDragCardId.value && currentDragSourceColumnId.value) {
        await handleMoveCard({
            cardId: currentDragCardId.value,
            sourceColumnId: currentDragSourceColumnId.value,
            targetColumnId
        });
    }
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
const handleUpdateCard = async ({ cardId, columnId, title, description, points }) => {
    // Update card optimistically
    props.columns.forEach(column => {
        const cardIndex = column.cards.findIndex(c => c.id === cardId);
        if (cardIndex !== -1) {
            column.cards[cardIndex] = {
                ...column.cards[cardIndex],
                title,
                description,
                points
            };
        }
    });

    // Send to backend
    try {
        const response = await axios.post(`/api/updateCard/${cardId}`, {
            title,
            description,
            points
        });

        if (!response.data.message) {
            throw new Error('Failed to update card');
        }
        
        toast.success('Card updated successfully');
        emit('columns-updated');
    } catch (error) {
        // Revert changes if failed
        toast.error('Failed to update card');
        emit('columns-updated');
    }
};

const handleDeleteCard = async (cardId) => {
    // Store card data for potential rollback
    let deletedCard;
    let deletedFromColumn;

    // Remove card optimistically
    props.columns.forEach(column => {
        const cardIndex = column.cards.findIndex(c => c.id === cardId);
        if (cardIndex !== -1) {
            deletedCard = column.cards[cardIndex];
            deletedFromColumn = column;
            column.cards = column.cards.filter(card => card.id !== cardId);
        }
    });

    try {
        // Send to backend
        const response = await axios.post(`/api/deleteCard/${cardId}`);
        if (!response.data.message) {
            throw new Error('Failed to delete card');
        }
        
        toast.success(response.data.message);
        emit('columns-updated');
    } catch (error) {
        // Revert changes if failed
        if (deletedCard && deletedFromColumn) {
            deletedFromColumn.cards.push(deletedCard);
        }
        toast.error('Failed to delete card');
        emit('columns-updated');
    }
};

const generateTempId = () => `temp-${Date.now()}`;

const handleAddCard = async ({ columnId, title, description, points }) => {
    // Add card optimistically
    const tempId = generateTempId();
    const column = props.columns.find(col => col.id === columnId);
    if (column) {
        const tempCard = {
            id: tempId,
            title,
            description,
            points
        };
        column.cards.push(tempCard);
    }

    try {
        // Send to backend
        const response = await axios.post(`/api/addCardToColumn/${columnId}`, {
            title,
            description,
            points
        });

        if (!response.data.card) {
            throw new Error('Failed to add card');
        }

        // Replace temp card with real one
        const column = props.columns.find(col => col.id === columnId);
        if (column) {
            const index = column.cards.findIndex(c => c.id === tempId);
            if (index !== -1) {
                column.cards[index] = response.data.card;
            }
        }
        
        toast.success('Card added successfully');
        // Reset form fields but keep the form open
        resetForm();
        emit('columns-updated');
        emit('burndown-update'); // Add this to trigger burndown chart update
    } catch (error) {
        toast.error('Failed to add card');
        emit('columns-updated');
    }
};

const handleAddColumn = async ({ title, done, board_id, status }) => {
    if (!status) status = "active";
    const tempId = generateTempId();
    
    // Add column optimistically
    const tempColumn = {
        id: tempId,
        title,
        done,
        board_id,
        status,
        cards: []
    };
    props.columns.push(tempColumn);

    try {
        // Send to backend
        const response = await axios.post('/api/addColumn', {
            title,
            done,
            board_id,
            status
        });

        if (!response.data || !response.data.column) {
            throw new Error('No column data received');
        }
        
        // Replace temp column with real one
        const index = props.columns.findIndex(col => col.id === tempId);
        if (index !== -1) {
            props.columns[index] = response.data.column;
        }
        
        toast.success('Column added successfully!');
        emit('columns-updated');
    } catch (error) {
        // Remove temp column if request failed
        const filteredColumns = props.columns.filter(col => col.id !== tempId);
        props.columns.length = 0;
        filteredColumns.forEach(col => props.columns.push(col));
        
        toast.error('Failed to add column');
    }
};

const handleDeleteColumn = async (columnId) => {
    // Store column data for potential rollback
    const deletedColumn = props.columns.find(col => col.id === columnId);
    const columnIndex = props.columns.findIndex(col => col.id === columnId);
    
    // Remove column optimistically
    const filteredColumns = props.columns.filter(column => column.id !== columnId);
    props.columns.length = 0;
    filteredColumns.forEach(col => props.columns.push(col));

    try {
        // Send to backend
        const response = await axios.post(`/api/deleteColumn`, {
            column_id: columnId
        });

        if (!response.data.message) {
            throw new Error('Failed to delete column');
        }
        
        toast.success(response.data.message);
        emit('columns-updated');
    } catch (error) {
        // Revert changes if failed
        if (deletedColumn && columnIndex !== -1) {
            props.columns.splice(columnIndex, 0, deletedColumn);
        }
        toast.error('Failed to delete column');
        emit('columns-updated');
    }
};

// Card movement handler
const handleMoveCard = async ({ cardId, sourceColumnId, targetColumnId }) => {
    const sourceColumn = props.columns.find(col => col.id == sourceColumnId);
    const targetColumn = props.columns.find(col => col.id == targetColumnId);
    const cardIndex = sourceColumn.cards.findIndex(c => c.id == cardId);
    if (cardIndex === -1) return;

    const [movedCard] = sourceColumn.cards.splice(cardIndex, 1);
    targetColumn.cards.push(movedCard);

    try {
        await axios.post(`/api/cards/${cardId}/move`, {
            column_id: targetColumnId
        });
        toast.success('Card moved successfully');
        emit('columns-updated');
        emit('burndown-update'); // Add this line
    } catch (error) {
        // Revert changes if API call fails
        sourceColumn.cards.splice(cardIndex, 0, movedCard);
        targetColumn.cards.pop();
        toast.error('Failed to move card');
        emit('columns-updated');
    }
};

const handleUpdateColumn = async ({ id, title }) => {
    const column = props.columns.find(col => col.id === id);
    if (!column) return;
    
    const originalTitle = column.title;
    
    column.title = title;
    
    try {
        const response = await axios.post(`/api/updateColumn`, {
            column_id: id,
            title
        });
        
        if (!response.data.message) {
            throw new Error('Failed to update column');
        }
        
        toast.success('Column updated successfully');
        emit('columns-updated');
    } catch (error) {
        if (column) {
            column.title = originalTitle;
        }
        toast.error('Failed to update column');
        emit('columns-updated');
    }
};
</script>

<template>
    <div class="flex flex-col bg-white p-4 rounded-lg shadow">
        <!-- Board header with title and foldable description -->
        <div class="mb-4">
            <div class="flex items-center">
                <h1 class="text-2xl font-bold text-gray-800">{{ board.title }}</h1>
                <button 
                    @click="toggleDescription" 
                    class="ml-2 text-gray-500 hover:text-gray-700 focus:outline-none"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path v-if="!showDescription" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        <path v-else fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <p v-if="showDescription" class="text-gray-600 mt-1 transition-all duration-300">{{ board.description }}</p>
        </div>

        <!-- Board columns container -->
        <div class="flex space-x-4 overflow-x-auto pb-4">
            <!-- Regular columns -->
            <div 
                v-for="column in regularColumns" 
                :key="column.id"
                class="flex-shrink-0 w-80 bg-gray-100 rounded-lg shadow"
                :class="{ 'border-2 border-blue-400': currentDragColumnId === column.id }"
                @dragover.prevent="handleDragOver($event, column.id)"
                @dragleave="handleDragLeave"
                @drop.prevent="handleDrop($event, column.id)"
            >
                <!-- Column header -->
                <div class="p-3 bg-gray-200 rounded-t-lg flex justify-between items-center">
                    <div v-if="columnEditing === column.id" class="flex-1">
                        <input 
                            v-model="newColumnTitle" 
                            @keyup.enter="handleUpdateColumn({ id: column.id, title: newColumnTitle }); resetNewColumnForm()"
                            @blur="resetNewColumnForm()"
                            class="w-full px-2 py-1 border border-gray-300 rounded"
                            placeholder="Column title"
                        />
                    </div>
                    <h3 v-else class="font-medium text-gray-700">{{ column.title }}</h3>
                    <div class="flex space-x-1" v-if="column.user_created !== 0">
                        <button 
                            @click="toggleEditColumn(column)"
                            class="p-1 text-gray-500 hover:text-gray-700 focus:outline-none"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                            </svg>
                        </button>
                        <button 
                            @click="handleDeleteColumn(column.id)"
                            class="p-1 text-gray-500 hover:text-red-600 focus:outline-none"
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
                        class="bg-white p-3 rounded shadow-sm mb-2 cursor-pointer"
                        draggable="true"
                        @dragstart="handleDragStart($event, card.id, column.id)"
                    >
                        <!-- Show card editing form when this card is being edited -->
                        <div v-if="cardEditing === card.id">
                            <CardForm 
                                :column-id="column.id"
                                :initial-title="cardTitle"
                                :initial-description="cardDesc"
                                :initial-points="cardPoints"
                                :is-editing="true"
                                @save="(data) => { 
                                    handleUpdateCard({
                                        cardId: card.id,
                                        columnId: column.id,
                                        title: data.title,
                                        description: data.description,
                                        points: data.points
                                    });
                                    resetForm();
                                }"
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
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add card form -->
                    <div v-if="cardOpen[column.id]">
                        <CardForm 
                            :column-id="column.id"
                            :key="`form-${column.id}-${Date.now()}`"
                            @save="handleAddCard"
                            @cancel="toggleAddCard(column.id)"
                        />
                    </div>
                    
                    <!-- Add card button -->
                    <button 
                        v-else
                        @click="toggleAddCard(column.id)"
                        class="w-full py-2 px-3 text-sm text-gray-600 hover:bg-gray-200 rounded flex items-center justify-center mt-2"
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
                class="flex-shrink-0 w-80 bg-green-50 rounded-lg shadow"
                :class="{ 'border-2 border-blue-400': currentDragColumnId === doneColumn.id }"
                @dragover.prevent="handleDragOver($event, doneColumn.id)"
                @dragleave="handleDragLeave"
                @drop.prevent="handleDrop($event, doneColumn.id)"
            >
                <!-- Column header -->
                <div class="p-3 bg-green-100 rounded-t-lg flex justify-between items-center">
                    <h3 class="font-medium text-green-800">{{ doneColumn.title }}</h3>
                    <div class="flex space-x-1" v-if="doneColumn.user_created !== 0">
                        <button 
                            @click="toggleEditColumn(doneColumn)"
                            class="p-1 text-gray-500 hover:text-gray-700 focus:outline-none"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Cards container -->
                <div class="p-2 max-h-[calc(100vh-12rem)] overflow-y-auto">
                    <!-- Cards -->
                    <div 
                        v-for="card in doneColumn.cards" 
                        :key="card.id"
                        class="bg-white p-3 rounded shadow-sm mb-2 cursor-pointer"
                        draggable="true"
                        @dragstart="handleDragStart($event, card.id, doneColumn.id)"
                    >
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
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add column form -->
            <div v-if="showNewColumn" class="flex-shrink-0 w-80 bg-white p-3 rounded-lg shadow">
                <h3 class="font-medium text-gray-700 mb-2">Add New Column</h3>
                <input 
                    v-model="newColumnTitle" 
                    class="w-full px-3 py-2 border border-gray-300 rounded mb-2"
                    placeholder="Column title"
                />
                <div class="flex items-center mb-3">
                    <input 
                        id="done-column" 
                        type="checkbox" 
                        v-model="newColumnDone" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label for="done-column" class="ml-2 block text-sm text-gray-900">
                        This is a "Done" column
                    </label>
                </div>
                <div class="flex justify-end space-x-2">
                    <button 
                        @click="toggleNewColumn" 
                        class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="handleAddColumn({
                            title: newColumnTitle,
                            done: newColumnDone,
                            board_id: board.id
                        }); toggleNewColumn()"
                        class="px-3 py-1 text-sm bg-blue-600 text-white hover:bg-blue-700 rounded"
                    >
                        Add Column
                    </button>
                </div>
            </div>
            
            <!-- Add column button -->
            <button 
                v-else
                @click="toggleNewColumn"
                class="flex-shrink-0 w-80 bg-gray-100 rounded-lg shadow-sm border-2 border-dashed border-gray-300 flex items-center justify-center p-6 hover:bg-gray-200"
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
</template>