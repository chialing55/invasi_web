import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                forest: {
                    DEFAULT: '#2f4f4f',   // 深森林綠 (主色)
                    canopy: '#4f7942',    // 樹冠綠
                    moss: '#8a9a5b',      // 苔蘚綠
                    bark: '#5c4438',      // 樹皮棕
                    fern: '#6b8e23',      // 羊齒植物綠
                    mist: '#dce3dc',      // 森林霧白
                    leaf: '#3b7a57',      // 葉片綠
                    soil: '#836953',      // 土壤棕
                },
            },
        },
    },

    plugins: [forms],
};
