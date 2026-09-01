import { createApp } from 'vue'
import { createPinia } from 'pinia'

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
