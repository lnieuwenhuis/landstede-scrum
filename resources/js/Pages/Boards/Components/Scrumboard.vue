<script setup>
import CardForm from './CardForm.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    columns: Array,
    board: Object
});

// Add computed properties to separate regular columns from the Done column
const regularColumns = computed(() => {
    return props.columns.filter(column => column.title !== 'Done');
});

const doneColumn = computed(() => {
    return props.columns.find(column => column.title === 'Done');
});

const emit = defineEmits([
    'updateCard',
    'deleteCard',
    'addCard',
    'deleteColumn',
    'addColumn',
    'moveCard'
]);

// Local state management
const cardOpen = ref({});
const cardEditing = ref(null);
const cardTitle = ref('');
const cardDesc = ref('');
const cardPoints = ref(0);
const showNewColumn = ref(false);
const newColumnTitle = ref('');
const newColumnDone = ref(false);
const currentDragColumnId = ref(null);

// Card handlers
const toggleAddCard = (columnId) => {
    cardOpen.value[columnId] = !cardOpen.value[columnId];
    cardEditing.value = null;
};

const toggleEditCard = (card) => {
    cardEditing.value = card.id;
    cardTitle.value = card.title;
    cardDesc.value = card.description;
    cardPoints.value = card.points;
};

const resetForm = () => {
    cardEditing.value = null;
    cardTitle.value = '';
    cardDesc.value = '';
    cardPoints.value = 0;
};

// Column handlers
const toggleNewColumn = () => {
    showNewColumn.value = !showNewColumn.value;
};

const resetNewColumnForm = () => {
    newColumnTitle.value = '';
    newColumnDone.value = false;
};

// Drag and drop handlers
const handleDragStart = (event, cardId, columnId) => {
    event.dataTransfer.setData('text/plain', JSON.stringify({ cardId, columnId }));
    event.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (event, columnId) => {
    event.preventDefault();
    currentDragColumnId.value = columnId;
    event.dataTransfer.dropEffect = 'move';
};

const handleDragLeave = () => {
    currentDragColumnId.value = null;
};

const handleDrop = (event, targetColumnId) => {
    event.preventDefault();
    currentDragColumnId.value = null;
    
    const { cardId, columnId: sourceColumnId } = JSON.parse(event.dataTransfer.getData('text/plain'));
    if (sourceColumnId === targetColumnId) return;
    
    emit('moveCard', { cardId, sourceColumnId, targetColumnId });
};
</script>

<template>
    <div class="overflow-x-auto pb-4" style="min-height: calc(100vh - 250px)">
        <div class="flex gap-6 flex-nowrap min-w-full">
            <!-- Regular Columns (excluding Done) -->
            <div 
                v-for="column in regularColumns" 
                :key="column.id"
                @dragover.prevent="handleDragOver($event, column.id)"
                @dragleave="handleDragLeave"
                @drop.prevent="handleDrop($event, column.id)"
                :class="{ 'bg-blue-100': currentDragColumnId === column.id }"
                class="w-[300px] flex-none bg-black/5 shadow-md rounded-lg p-4 transition-colors duration-200"
            >
                <!-- Column content remains the same -->
                <h2 class="text-xl font-semibold text-gray-800 mb-4">{{ column.title }}</h2>

                <!-- Cards -->
                <div class="space-y-4">
                    <div 
                        v-for="card in column.cards" 
                        :key="card.id"
                        draggable="true"
                        @dragstart="handleDragStart($event, card.id, column.id)"
                        class="bg-white p-3 rounded-lg shadow-sm cursor-move transition-transform duration-200 hover:scale-[1.02]"
                    >
                        <!-- Card View -->
                        <div v-if="cardEditing !== card.id">
                            <p class="text-gray-700">{{ card.title }}</p>
                            <p class="text-gray-500">{{ card.description }}</p>
                            <span class="text-gray-500">Points: {{ card.points }}</span>
                            <div class="flex gap-2 mt-2">
                                <button @click="toggleEditCard(card)" class="bg-blue-500 text-white py-1 px-2 rounded-lg hover:bg-blue-600">
                                    Edit
                                </button>
                                <button @click="emit('deleteCard', card.id)" class="bg-red-500 text-white py-1 px-2 rounded-lg hover:bg-red-600">
                                    Delete
                                </button>
                            </div>
                        </div>

                        <!-- Card Edit Form -->
                        <CardForm
                            v-else-if="cardEditing === card.id"
                            :initial-values="{
                                title: card.title,
                                description: card.description,
                                points: card.points
                            }"
                            :is-editing="true"
                            :column-id="column.id"
                            :card-id="card.id"
                            @save="emit('updateCard', $event); resetForm()"
                            @cancel="resetForm"
                        />
                    </div>
                </div>

                <CardForm
                    v-if="cardOpen[column.id]"
                    :initial-values="{
                        title: cardTitle,
                        description: cardDesc,
                        points: cardPoints
                    }"
                    :is-editing="false"
                    :column-id="column.id"
                    @save="emit('addCard', $event); resetForm(); toggleAddCard(column.id)"
                    @cancel="toggleAddCard(column.id)"
                />

                <!-- Column Actions -->
                <div class="flex justify-between mt-2">
                    <button 
                        v-if="!cardOpen[column.id]" 
                        @click="toggleAddCard(column.id)"
                        class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        + Add Card
                    </button>
                    <div v-if="!['Project Backlog', 'Sprint Backlog', 'Done'].includes(column.title)" class="flex gap-2">
                        <button @click="emit('toggleEditColumn', column.id)" class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                            </svg>
                        </button>
                        <button @click="emit('deleteColumn', column.id)" class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- New Column Form -->
            <div v-if="showNewColumn" class="w-[300px] flex-none bg-black/5 shadow-md rounded-lg p-4">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">New Column</h2>
                <div class="space-y-4">
                    <input 
                        v-model="newColumnTitle"
                        type="text" 
                        class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Enter column title" 
                    />
                    <label class="flex items-center mt-2">
                        <input 
                            v-model="newColumnDone"
                            type="checkbox" 
                            class="form-checkbox h-5 w-5 text-blue-600" 
                        />
                        <span class="ml-2 text-gray-700">Done</span>
                    </label>
                </div>
                <div class="flex justify-between mt-4">
                    <button @click="emit('addColumn', { title: newColumnTitle, done: newColumnDone, board_id: board.id }); resetNewColumnForm(); toggleNewColumn()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add</button>
                    <button @click="toggleNewColumn" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Cancel</button>
                </div>
            </div>
                        
            <!-- Add Column Button -->
            <button 
                v-if="!showNewColumn" 
                @click="toggleNewColumn"
                class="w-20 h-20 flex-none bg-blue-500 text-white rounded-lg shadow-md hover:bg-blue-600 flex items-center justify-center"
            >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            </button>

            <!-- Done Column (placed after the add column button) -->
            <div 
                v-if="doneColumn"
                :key="doneColumn.id"
                @dragover.prevent="handleDragOver($event, doneColumn.id)"
                @dragleave="handleDragLeave"
                @drop.prevent="handleDrop($event, doneColumn.id)"
                :class="{ 'bg-blue-100': currentDragColumnId === doneColumn.id }"
                class="w-[300px] flex-none bg-black/5 shadow-md rounded-lg p-4 transition-colors duration-200"
            >
                <h2 class="text-xl font-semibold text-gray-800 mb-4">{{ doneColumn.title }}</h2>

                <!-- Cards -->
                <div class="space-y-4">
                    <div 
                        v-for="card in doneColumn.cards" 
                        :key="card.id"
                        draggable="true"
                        @dragstart="handleDragStart($event, card.id, doneColumn.id)"
                        class="bg-white p-3 rounded-lg shadow-sm cursor-move transition-transform duration-200 hover:scale-[1.02]"
                    >
                        <!-- Card View -->
                        <div v-if="cardEditing !== card.id">
                            <p class="text-gray-700">{{ card.title }}</p>
                            <p class="text-gray-500">{{ card.description }}</p>
                            <span class="text-gray-500">Points: {{ card.points }}</span>
                            <div class="flex gap-2 mt-2">
                                <button @click="toggleEditCard(card)" class="bg-blue-500 text-white py-1 px-2 rounded-lg hover:bg-blue-600">
                                    Edit
                                </button>
                                <button @click="emit('deleteCard', card.id)" class="bg-red-500 text-white py-1 px-2 rounded-lg hover:bg-red-600">
                                    Delete
                                </button>
                            </div>
                        </div>

                        <!-- Card Edit Form -->
                        <CardForm
                            v-else-if="cardEditing === card.id"
                            :initial-values="{
                                title: card.title,
                                description: card.description,
                                points: card.points
                            }"
                            :is-editing="true"
                            :column-id="doneColumn.id"
                            :card-id="card.id"
                            @save="emit('updateCard', $event); resetForm()"
                            @cancel="resetForm"
                        />
                    </div>
                </div>

                <CardForm
                    v-if="cardOpen[doneColumn.id]"
                    :initial-values="{
                        title: cardTitle,
                        description: cardDesc,
                        points: cardPoints
                    }"
                    :is-editing="false"
                    :column-id="doneColumn.id"
                    @save="emit('addCard', $event); resetForm(); toggleAddCard(doneColumn.id)"
                    @cancel="toggleAddCard(doneColumn.id)"
                />

                <!-- Column Actions -->
                <div class="flex justify-between mt-2">
                    <button 
                        v-if="!cardOpen[doneColumn.id]" 
                        @click="toggleAddCard(doneColumn.id)"
                        class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        + Add Card
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>