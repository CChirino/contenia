import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    primary: '#4F46E5',
                    secondary: '#8B5CF6',
                    ink: '#14122B',
                    tint: '#F5F4FF',
                },
                surface: 'rgb(var(--c-surface) / <alpha-value>)',
                'surface-2': 'rgb(var(--c-surface-2) / <alpha-value>)',
                'surface-3': 'rgb(var(--c-surface-3) / <alpha-value>)',
                fg: 'rgb(var(--c-fg) / <alpha-value>)',
                'fg-2': 'rgb(var(--c-fg-2) / <alpha-value>)',
                muted: 'rgb(var(--c-muted) / <alpha-value>)',
                line: 'rgb(var(--c-line) / <alpha-value>)',
                'line-strong': 'rgb(var(--c-line-strong) / <alpha-value>)',
            },
        },
    },

    plugins: [forms],
};
