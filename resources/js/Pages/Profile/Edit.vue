<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { ChromePicker } from 'vue-color'; 
import axios from 'axios';
import { useToast } from 'vue-toastification';
import { useTranslations } from '@/translations';
const toast = useToast();
const { props } = usePage();
const { __ } = useTranslations();
const user = ref(props.user); 

// Define color with a static default value
const color = defineModel({
    default: '#3b82f6'
});

// Update the color with user's color when component mounts
onMounted(() => {
    if (user.value?.color) {
        color.value = user.value.color;
    }
});

const handleSaveColor = async (colorValue) => {
    try {
        const response = await axios.post('/api/users/changeUserColor', { color: colorValue });
        console.log(response)
        if (response.data.message) {
            toast.success(__('Color saved successfully!'));
        } else {
            toast.error(__('Failed to save color.'));
        }
    } catch (error) {
        console.error('Error saving color:', error);
        toast.error(__('An error occurred while saving your color.'));
    }
};
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                {{ __('Profile') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <div class="max-w-xl">
                        <section>
                            <header>
                                <h2 class="text-lg font-medium text-gray-900">{{__('Profile Colour')}}</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{__('Choose a color to personalize your profile.')}}
                                </p>
                            </header>

                            <div class="mt-6 space-y-6">
                                <div>
                                    <label for="profileColorPicker" class="block text-sm font-medium text-gray-700 mb-2">
                                        {{__('Select Color')}}
                                    </label>
                                    <div class="flex items-start space-x-4">
                                        <div>
                                            <ChromePicker
                                                v-model="color"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <button
                                        type="button"
                                        @click="handleSaveColor(color)"
                                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    >
                                        {{__('Save Color')}}
                                    </button>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
