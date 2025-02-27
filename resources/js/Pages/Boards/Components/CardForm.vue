<script setup>
const props = defineProps({
    cardTitle: String,
    cardDesc: String,
    cardPoints: Number,
    isEditing: Boolean,
    columnId: Number,
    cardId: Number
});

const emit = defineEmits([
    'update:cardTitle',
    'update:cardDesc',
    'update:cardPoints',
    'save',
    'cancel'
]);
</script>

<template>
    <div class="mt-2">
        <input 
            :value="cardTitle"
            @input="emit('update:cardTitle', $event.target.value)"
            type="text" 
            placeholder="Title" 
            class="w-full p-2 border rounded mb-2" 
        />
        <textarea 
            :value="cardDesc"
            @input="emit('update:cardDesc', $event.target.value)"
            placeholder="Description" 
            class="w-full p-2 border rounded mb-2"
        ></textarea>
        <input 
            :value="cardPoints"
            @input="emit('update:cardPoints', Number($event.target.value))"
            type="number" 
            placeholder="Points" 
            class="w-full p-2 border rounded mb-2" 
        />
        <div class="flex justify-between">
            <button 
                @click="emit('save', columnId, cardId)" 
                class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
            >
                {{ isEditing ? 'Save' : 'Add' }}
            </button>
            <button 
                @click="emit('cancel')" 
                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
            >
                Cancel
            </button>
        </div>
    </div>
</template>