import { createApp } from 'vue'
import { createPinia } from 'pinia'
import '@fontsource/plus-jakarta-sans/400.css'
import '@fontsource/plus-jakarta-sans/400-italic.css'
import '@fontsource/plus-jakarta-sans/500.css'
import '@fontsource/plus-jakarta-sans/500-italic.css'
import '@fontsource/plus-jakarta-sans/600.css'
import '@fontsource/plus-jakarta-sans/600-italic.css'

import App from './App.vue'
import router from './app/router'
import { useUiStore } from './app/stores/uiStore'
import './assets/main.css'
import { configureHttpNotifications } from './shared/api/http'
import FormSelect from './shared/components/FormSelect.vue'
import ToggleSwitch from './shared/components/ToggleSwitch.vue'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.component('FormSelect', FormSelect)
app.component('ToggleSwitch', ToggleSwitch)

configureHttpNotifications((notification) => useUiStore(pinia).notify(notification))

app.mount('#app')
