import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#fff0f0',
                    100: '#ffd6d6',
                    200: '#ffb3b3',
                    300: '#ff8080',
                    400: '#ff4d4d',
                    500: '#ff1a1a',
                    600: '#e60000',
                    700: '#b30000',
                    800: '#800000',
                    900: '#4d0000',
                },
            },
        },
    },

    plugins: [forms],
}
