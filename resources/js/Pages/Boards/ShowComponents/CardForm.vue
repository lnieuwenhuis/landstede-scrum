<script setup>
import { ref } from 'vue';

const props = defineProps({
    columnId: {
        type: [Number, String],
        required: true
    },
    card: {
        type: Object,
        default: null
    },
    // Add these new props
    initialTitle: {
        type: String,
        default: ''
    },
    initialDescription: {
        type: String,
        default: ''
    },
    initialPoints: {
        type: Number,
        default: 0
    },
    isEditing: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['save', 'cancel']);

// Use either card props or initial props
const title = ref(props.card ? props.card.title : props.initialTitle);
const description = ref(props.card ? props.card.description : props.initialDescription);
const points = ref(props.card ? props.card.points : props.initialPoints);

// Use either card or isEditing prop
const isEditingCard = ref(!!props.card || props.isEditing);

const handleSubmit = () => {
    if (!title.value.trim()) {
        return;
    }
    
    const cardData = {
        columnId: props.columnId,
        title: title.value,
        description: description.value,
        points: points.value
    };
    
    if (isEditingCard.value && props.card) {
        cardData.cardId = props.card.id;
    }
    
    emit('save', cardData);
};

const handleCancel = () => {
    emit('cancel');
};
</script>

<template>
    <div class="bg-white p-3 rounded shadow-sm mb-2">
        <!-- Remove the heading when editing -->
        <h4 v-if="!isEditingCard" class="font-medium text-gray-800 mb-2">Add Card</h4>
        
        <form @submit.prevent="handleSubmit">
            <div class="mb-3">
                <label for="card-title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input 
                    id="card-title"
                    v-model="title"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Card title"
                    required
                />
            </div>
            
            <div class="mb-3">
                <label for="card-description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea 
                    id="card-description"
                    v-model="description"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Card description"
                    rows="3"
                ></textarea>
            </div>
            
            <div class="mb-4">
                <label for="card-points" class="block text-sm font-medium text-gray-700 mb-1">Points</label>
                <input 
                    id="card-points"
                    v-model="points"
                    type="number"
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Story points"
                />
            </div>
            
            <div class="flex justify-end space-x-2">
                <button 
                    type="button"
                    @click="handleCancel"
                    class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="px-3 py-1 text-sm bg-blue-600 text-white hover:bg-blue-700 rounded"
                >
                    {{ isEditingCard ? 'Update' : 'Add' }}
                </button>
            </div>
        </form>
    </div>
</template>