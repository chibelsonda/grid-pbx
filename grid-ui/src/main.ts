import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './app/router'
import './assets/main.css'
import FormSelect from './shared/components/FormSelect.vue'
import ToggleSwitch from './shared/components/ToggleSwitch.vue'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.component('FormSelect', FormSelect)
app.component('ToggleSwitch', ToggleSwitch)

app.mount('#app')
