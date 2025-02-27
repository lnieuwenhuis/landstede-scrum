<script setup>
import CardForm from './CardForm.vue';
import { ref } from 'vue';

const props = defineProps({
    columns: Array,
    board: Object
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
            <!-- Column -->
            <div 
                v-for="column in columns" 
                :key="column.id"
                @dragover.prevent="handleDragOver($event, column.id)"
                @dragleave="handleDragLeave"
                @drop.prevent="handleDrop($event, column.id)"
                :class="{ 'bg-blue-100': currentDragColumnId === column.id }"
                class="w-[300px] flex-none bg-black/5 shadow-md rounded-lg p-4 transition-colors duration-200"
            >
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

                <!-- Add Card Button/Form -->
                <button 
                    v-if="!cardOpen[column.id]" 
                    @click="toggleAddCard(column.id)"
                    class="mt-3 text-blue-500 hover:text-blue-700"
                >
                    + Add Card
                </button>

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
                    <button @click="emit('toggleEditColumn', column.id)" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Edit</button>
                    <button @click="emit('deleteColumn', column.id)" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">Delete</button>
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
                class="w-[300px] h-20 flex-none bg-blue-500 text-white rounded-lg shadow-md hover:bg-blue-600 flex items-center justify-center"
            >
                + Create Column
            </button>
        </div>
    </div>
</template>