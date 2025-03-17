<script setup>
import { reactive, watch, computed } from 'vue';

const props = defineProps({
    isEditing: Boolean,
    columnId: Number,
    cardId: Number,
    initialValues: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['save', 'cancel']);

const formData = reactive({
    title: '',
    description: '',
    points: 0
});

// Initialize form data
formData.title = props.initialValues.title;
formData.description = props.initialValues.description;
formData.points = props.initialValues.points;

// Update form data when initialValues change
watch(() => props.initialValues, (newValues) => {
    formData.title = newValues.title;
    formData.description = newValues.description;
    formData.points = newValues.points ?? 0;
}, { immediate: true, deep: true });

const buttonText = computed(() => props.isEditing ? 'Save' : 'Add');

const handleSubmit = () => {
    emit('save', {
        columnId: props.columnId,
        cardId: props.cardId,
        title: formData.title,
        description: formData.description,
        points: formData.points
    });
};
</script>

<template>
    <div class="mt-2">
        <input 
            v-model="formData.title"
            type="text" 
            placeholder="Title" 
            class="w-full p-2 border rounded mb-2 focus:ring-2 focus:ring-blue-500 focus:outline-none" 
        />
        <textarea 
            v-model="formData.description"
            placeholder="Description" 
            class="w-full p-2 border rounded mb-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
        ></textarea>
        <input 
            v-model.number="formData.points"
            type="number" 
            placeholder="Points" 
            class="w-full p-2 border rounded mb-2 focus:ring-2 focus:ring-blue-500 focus:outline-none" 
        />
        <div class="flex justify-between">
            <button 
                @click="handleSubmit" 
                class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 focus:ring-2 focus:ring-green-500 focus:outline-none"
            >
                {{ buttonText }}
            </button>
            <button 
                @click="emit('cancel')" 
                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 focus:ring-2 focus:ring-gray-500 focus:outline-none"
            >
                Cancel
            </button>
        </div>
    </div>
</template>