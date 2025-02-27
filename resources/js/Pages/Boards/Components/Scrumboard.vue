<script setup>
import CardForm from './CardForm.vue';

const props = defineProps({
    columns: Array,
    cardOpen: Object,
    cardEditing: Number,
    cardTitle: String,
    cardDesc: String,
    cardPoints: Number,
    showNewColumn: Boolean,
    newColumnTitle: String,
    newColumnDone: Boolean,
    currentDragColumnId: Number
});

const emit = defineEmits([
    'toggleAddCard',
    'toggleEditCard',
    'toggleDeleteCard',
    'handleEditCard',
    'resetForm',
    'handleAddCard',
    'toggleEditColumn',
    'handleDeleteColumn',
    'handleDragStart',
    'handleDragOver',
    'handleDragLeave',
    'handleDrop',
    'toggleNewColumn',
    'handleAddColumn',
    'update:cardTitle',
    'update:cardDesc',
    'update:cardPoints',
    'update:newColumnTitle',
    'update:newColumnDone'
]);
</script>

<template>
    <div class="overflow-x-auto pb-4" style="min-height: calc(100vh - 250px)">
        <div class="flex gap-6 flex-nowrap min-w-full">
            <!-- Column -->
            <div 
                v-for="column in columns" 
                :key="column.id"
                @dragover.prevent="emit('handleDragOver', $event, column.id)"
                @dragleave="emit('handleDragLeave')"
                @drop.prevent="emit('handleDrop', $event, column.id)"
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
                        @dragstart="emit('handleDragStart', $event, card.id, column.id)"
                        class="bg-white p-3 rounded-lg shadow-sm cursor-move transition-transform duration-200 hover:scale-[1.02]"
                    >
                        <!-- Card View -->
                        <div v-if="cardEditing !== card.id">
                            <p class="text-gray-700">{{ card.title }}</p>
                            <p class="text-gray-500">{{ card.description }}</p>
                            <span class="text-gray-500">Points: {{ card.points }}</span>
                            <div class="flex gap-2 mt-2">
                                <button @click="emit('toggleEditCard', card)" class="bg-blue-500 text-white py-1 px-2 rounded-lg hover:bg-blue-600">
                                    Edit
                                </button>
                                <button @click="emit('toggleDeleteCard', card.id)" class="bg-red-500 text-white py-1 px-2 rounded-lg hover:bg-red-600">
                                    Delete
                                </button>
                            </div>
                        </div>

                        <!-- Card Edit Form -->
                        <CardForm
                            v-else-if="cardEditing === card.id"
                            :cardTitle="cardTitle"
                            :cardDesc="cardDesc"
                            :cardPoints="cardPoints"
                            :isEditing="true"
                            :columnId="column.id"
                            :cardId="card.id"
                            @update:cardTitle="emit('update:cardTitle', $event)"
                            @update:cardDesc="emit('update:cardDesc', $event)"
                            @update:cardPoints="emit('update:cardPoints', $event)"
                            @save="emit('handleEditCard', $event)"
                            @cancel="emit('resetForm')"
                        />
                    </div>
                </div>

                <!-- Add Card Button/Form -->
                <button 
                    v-if="!cardOpen[column.id]" 
                    @click="emit('toggleAddCard', column.id)"
                    class="mt-3 text-blue-500 hover:text-blue-700"
                >
                    + Add Card
                </button>

                <CardForm
                    v-if="cardOpen[column.id]"
                    :cardTitle="cardTitle"
                    :cardDesc="cardDesc"
                    :cardPoints="cardPoints"
                    :isEditing="false"
                    :columnId="column.id"
                    @update:cardTitle="emit('update:cardTitle', $event)"
                    @update:cardDesc="emit('update:cardDesc', $event)"
                    @update:cardPoints="emit('update:cardPoints', $event)"
                    @save="emit('handleAddCard', $event)"
                    @cancel="emit('toggleAddCard', column.id)"
                />

                <!-- Column Actions -->
                <div class="flex justify-between mt-2">
                    <button @click="emit('toggleEditColumn', column.id)" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Edit</button>
                    <button @click="emit('handleDeleteColumn', column.id)" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">Delete</button>
                </div>
            </div>

            <!-- New Column Form -->
            <div v-if="showNewColumn" class="w-[300px] flex-none bg-black/5 shadow-md rounded-lg p-4">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">New Column</h2>
                <div class="space-y-4">
                    <input 
                        :value="newColumnTitle"
                        @input="emit('update:newColumnTitle', $event.target.value)"
                        type="text" 
                        class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Enter column title" 
                    />
                    <label class="flex items-center mt-2">
                        <input 
                            :checked="newColumnDone"
                            @change="emit('update:newColumnDone', $event.target.checked)"
                            type="checkbox" 
                            class="form-checkbox h-5 w-5 text-blue-600" 
                        />
                        <span class="ml-2 text-gray-700">Done</span>
                    </label>
                </div>
                <div class="flex justify-between mt-4">
                    <button @click="emit('handleAddColumn')" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add</button>
                    <button @click="emit('toggleNewColumn')" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Cancel</button>
                </div>
            </div>
                        
            <!-- Add Column Button -->
            <button 
                v-if="!showNewColumn" 
                @click="emit('toggleNewColumn')"
                class="w-[300px] h-20 flex-none bg-blue-500 text-white rounded-lg shadow-md hover:bg-blue-600 flex items-center justify-center"
            >
                + Create Column
            </button>
        </div>
    </div>
</template>