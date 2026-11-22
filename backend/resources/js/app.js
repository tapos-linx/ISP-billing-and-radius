import './bootstrap';
import { createApp, h, defineComponent, onMounted, onUnmounted, ref } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import React from 'react';
import { createRoot } from 'react-dom/client';

const vuePages = import.meta.glob('./Pages/**/*.vue', { eager: true });
const reactPages = import.meta.glob('./Pages/**/*.tsx', { eager: true });

function wrapReact(Component) {
    return defineComponent({
        name: 'ReactInertiaWrapper',
        props: {
            initialData: {
                type: Object,
                default: () => ({}),
            },
        },
        setup(props, { attrs }) {
            const rootRef = ref(null);
            let root = null;

            onMounted(() => {
                if (rootRef.value) {
                    root = createRoot(rootRef.value);
                    root.render(React.createElement(Component, { ...props, ...attrs }));
                }
            });

            onUnmounted(() => {
                if (root) {
                    root.unmount();
                }
            });

            return () => h('div', { ref: rootRef, class: 'w-full min-h-screen' });
        },
    });
}

createInertiaApp({
    resolve: name => {
        if (reactPages[`./Pages/${name}.tsx`]) {
            const mod = reactPages[`./Pages/${name}.tsx`];
            return wrapReact(mod.default || mod);
        }
        return vuePages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
