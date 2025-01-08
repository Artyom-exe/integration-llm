import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import scrollbar from 'tailwind-scrollbar';


/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            typography: (theme) => ({
                DEFAULT: {
                    css: {
                        color: theme('colors.gray.800'),
                        a: {
                            color: theme('colors.blue.600'),
                            '&:hover': {
                                color: theme('colors.blue.700'),
                            },
                        },
                        h1: {
                            fontWeight: '700',
                            color: theme('colors.gray.900'),
                        },
                        h2: {
                            fontWeight: '600',
                            color: theme('colors.gray.900'),
                        },
                        code: {
                            backgroundColor: theme('colors.gray.100'),
                            padding: '0.25rem 0.5rem',
                            borderRadius: '0.25rem',
                            color: theme('colors.pink.600'),
                        },
                    },
                },
                dark: {
                    css: {
                        color: theme('colors.gray.200'),
                        a: {
                            color: theme('colors.blue.400'),
                            '&:hover': {
                                color: theme('colors.blue.300'),
                            },
                        },
                        h1: {
                            fontWeight: '700',
                            color: theme('colors.gray.100'),
                        },
                        h2: {
                            fontWeight: '600',
                            color: theme('colors.gray.100'),
                        },
                        code: {
                            backgroundColor: theme('colors.gray.700'),
                            padding: '0.25rem 0.5rem',
                            borderRadius: '0.25rem',
                            color: theme('colors.pink.300'),
                        },
                    },
                },
            }),
        },
    },

    plugins: [forms, typography, scrollbar],
};
