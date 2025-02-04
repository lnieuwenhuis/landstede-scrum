<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const currentUser = usePage().props.auth.user;
const toast = useToast();
const { props } = usePage();

const board = props.board;
const columns = ref(props.columns);
const users = ref(props.users);

const cardOpen = ref({});
const cardEditing = ref(null); // Track editing card ID

const showDeleteConfirmation = ref(false);
const cardToDelete = ref(null);

const cardTitle = ref('');
const cardDesc = ref('');
const cardPoints = ref(0);

const toggleDeleteCard = (cardId = null) => {
    document.body.style.overflow = document.body.style.overflow === 'hidden' ? '' : 'hidden';
    showDeleteConfirmation.value = !showDeleteConfirmation.value;
    cardToDelete.value = cardId;
};

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

const showNewColumn = ref(false);
const toggleNewColumn = () => {
    showNewColumn.value = !showNewColumn.value; 
}

const toggleEditColumn = (columnId) => {
    cardOpen.value[columnId] = !cardOpen.value[columnId];
};

const columnEditTitle = ref('');
const columnEditDone = ref(false);
const handleEditColumn = async (columnId) => {
    const response = await axios.post(`/api/updateColumn/${columnId}`, {
        title: columnEditTitle.value,
        done: columnEditDone.value
    });
}

const handleAddCard = async (columnId) => {
    const response = await axios.post(`/api/addCardToColumn/${columnId}`, {
        title: cardTitle.value,
        description: cardDesc.value,
        points: cardPoints.value
    });

    if (response.data.card) {
        const column = columns.value.find(col => col.id === columnId);
        if (column) {
            column.cards.push(response.data.card);
        }
        resetForm();
    }
};

const handleEditCard = async (columnId, cardId) => {
    const response = await axios.post(`/api/updateCard/${cardId}`, {
        title: cardTitle.value,
        description: cardDesc.value,
        points: cardPoints.value
    });

    if (response.data.message) {
        columns.value.forEach(column => {
            const cardIndex = column.cards.findIndex(c => c.id === cardId);
            if (cardIndex !== -1) {
                column.cards[cardIndex] = response.data.card;
            }
        });
        resetForm();
    }
};

const handleDeleteCard = async () => {
    const response = await axios.post(`/api/deleteCard/${cardToDelete.value}`);
    if (response.data.message) {
        columns.value.forEach(column => {
            column.cards = column.cards.filter(card => card.id !== cardToDelete.value);
        });
        toggleDeleteCard();
        toast.success(response.data.message);
    }
};

const resetForm = () => {
    cardEditing.value = null;
    cardTitle.value = '';
    cardDesc.value = '';
    cardPoints.value = 0;
};

const newColumnTitle = ref('');
const newColumnDone = ref(false);

const handleAddColumn = async () => {
    const response = await axios.post('/api/addColumn', {
        title: newColumnTitle.value,
        done: newColumnDone.value,
        board_id: board.id
    });

    if (response.data.column) {
        columns.value.push(response.data.column);
        resetNewColumnForm();
        toggleNewColumn();
        toast.success('Column added successfully!');
    }
};

const handleDeleteColumn = async (columnId) => {
    const response = await axios.post(`/api/deleteColumn`, {
        column_id: columnId
    });

    if (response.data.message) {
        columns.value = columns.value.filter(column => column.id !== columnId);
        toast.success(response.data.message);
    }
};

const resetNewColumnForm = () => {
    newColumnTitle.value = '';
    newColumnDone.value = false;
};

const currentDragColumnId = ref(null);
const dragCard = ref(null);
const dragColumn = ref(null);

const handleDragStart = (event, cardId, columnId) => {
    event.dataTransfer.setData('text/plain', JSON.stringify({ cardId, columnId }));
    dragCard.value = cardId;
    dragColumn.value = columnId;
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

const handleDrop = async (event, targetColumnId) => {
    event.preventDefault();
    currentDragColumnId.value = null;
    
    const data = JSON.parse(event.dataTransfer.getData('text/plain'));
    const { cardId, columnId: sourceColumnId } = data;

    if (sourceColumnId === targetColumnId) return;

    const sourceColumn = columns.value.find(col => col.id == sourceColumnId);
    const targetColumn = columns.value.find(col => col.id == targetColumnId);
    const cardIndex = sourceColumn.cards.findIndex(c => c.id == cardId);

    if (cardIndex === -1) return;

    // Remove from source column
    const [movedCard] = sourceColumn.cards.splice(cardIndex, 1);
    
    // Add to target column
    targetColumn.cards.push(movedCard);

    try {
        await axios.post(`/api/cards/${cardId}/move`, {
            column_id: targetColumnId
        });
        toast.success('Card moved successfully');
    } catch (error) {
        // Revert changes if API call fails
        sourceColumn.cards.splice(cardIndex, 0, movedCard);
        targetColumn.cards.pop();
        toast.error('Failed to move card');
    }
};

const handleRemoveUser = async (userId) => {
    const response = await axios.post(`/api/users/${board.group.id}/remove`, {
        user_id: userId
    });

    if (response.data.message) {
        users.value = users.value.filter(user => user.id !== userId);
        toast.success(response.data.message);
    } else {
        toast.error('Failed to remove user');
    }
}
</script>

<template>
    <Head title="Board" />

    <AuthenticatedLayout>
        <div class="container mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">{{ board.title }}</h1>
            </div>

            <p class="mb-6">{{ board.description }}</p>

            <div class="flex space-x-12">
                <div class="w-1/6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Members</h2>
                    <ul class="space-y-4">
                        <li v-for="user in users" :key="user.id" class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm">
                            <span class="text-gray-700">{{ user.name }} {{  user.id === currentUser.id ? '(You)' : '' }}</span>
                            <button v-if="user.id !== currentUser.id" @click="handleRemoveUser(user.id)" class="text-red-600 hover:text-red-800 focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </li>
                    </ul>
                    
                </div>

                <div class="flex-1">
                    <div class="flex flex-wrap gap-6">
                        <div 
                            v-for="column in columns" 
                            :key="column.id"
                            @dragover.prevent="handleDragOver($event, column.id)"
                            @dragleave="handleDragLeave"
                            @drop.prevent="handleDrop($event, column.id)"
                            :class="{ 'bg-blue-100': currentDragColumnId === column.id }"
                            class="w-1/4 bg-black/5 shadow-md rounded-lg p-4 transition-colors duration-200"
                        >
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">{{ column.title }}</h2>

                            <div class="space-y-4">
                                <div 
                                    v-for="card in column.cards" 
                                    :key="card.id"
                                    draggable="true"
                                    @dragstart="handleDragStart($event, card.id, column.id)"
                                    class="bg-white p-3 rounded-lg shadow-sm cursor-move transition-transform duration-200 hover:scale-[1.02]"
                                >
                                    <!-- Display Card Details -->
                                    <div v-if="cardEditing !== card.id">
                                        <p class="text-gray-700">{{ card.title }}</p>
                                        <p class="text-gray-500">{{ card.description }}</p>
                                        <span class="text-gray-500">Points: {{ card.points }}</span>
                                        <div>
                                            <button @click="toggleEditCard(card)" class="bg-blue-500 text-white py-1 px-2 rounded-lg hover:bg-blue-600 mr-1">
                                                Edit
                                            </button>
                                            <button @click="toggleDeleteCard(card.id)" class="bg-red-500 text-white py-1 px-2 rounded-lg hover:bg-red-600">
                                                Delete
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Edit Card Form -->
                                    <div v-else class="mt-2">
                                        <input v-model="cardTitle" type="text" placeholder="Title" class="w-full p-2 border rounded mb-2" />
                                        <textarea v-model="cardDesc" placeholder="Description" class="w-full p-2 border rounded mb-2"></textarea>
                                        <input v-model="cardPoints" type="number" placeholder="Points" class="w-full p-2 border rounded mb-2" />
                                        <div class="flex justify-between">
                                            <button @click="handleEditCard(column.id, card.id)" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                                Save
                                            </button>
                                            <button @click="resetForm" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button v-if="!cardOpen[column.id]" class="mt-3 text-blue-500 hover:text-blue-700" @click="toggleAddCard(column.id)">
                                + Add Card
                            </button>

                            <div v-if="cardOpen[column.id]" class="mt-3">
                                <input v-model="cardTitle" type="text" class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter card title" />
                                <textarea v-model="cardDesc" class="w-full resize-none mt-2 px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter card description"></textarea>
                                <input v-model="cardPoints" type="number" class="w-full mt-2 px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter card points" />
                                <div class="flex justify-between mt-2">
                                    <button @click="handleAddCard(column.id)" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        Add
                                    </button>
                                    <button @click="toggleAddCard(column.id)" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <div class="flex justify-between mt-2">
                                <button @click="toggleEditColumn" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Edit</button>
                                <button @click="handleDeleteColumn(column.id)" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500" >Delete</button>
                            </div>
                        </div>
                        <div v-if="showNewColumn" class="w-1/4 bg-black/5 shadow-md rounded-lg p-4">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">New Column</h2>

                            <div class="space-y-4">
                                <input v-model="newColumnTitle" type="text" class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter column title" />
                                <label class="flex items-center mt-2">
                                    <input v-model="newColumnDone" type="checkbox" class="form-checkbox h-5 w-5 text-blue-600" />
                                    <span class="ml-2 text-gray-700">Done</span>
                                </label>
                            </div>

                            <div class="flex justify-between mt-4">
                                <button @click="handleAddColumn" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Add
                                </button>
                                <button @click="toggleNewColumn" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    Cancel
                                </button>
                            </div>
                        </div>
                        
                        <button v-if="!showNewColumn" @click="toggleNewColumn"
                            class="w-1/4 h-20 bg-blue-500 text-white rounded-lg shadow-md hover:bg-blue-600 flex items-center justify-center shrink-0">
                            + Create Column
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDeleteConfirmation" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <p class="text-gray-800 mb-4">Are you sure you want to delete this card?</p>
                <div class="flex justify-between">
                    <button @click="handleDeleteCard" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete
                    </button>
                    <button @click="toggleDeleteCard" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>