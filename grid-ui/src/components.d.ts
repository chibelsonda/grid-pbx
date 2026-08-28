declare module 'vue' {
  export interface GlobalComponents {
    FormSelect: (typeof import('./shared/components/FormSelect.vue'))['default']
    ToggleSwitch: (typeof import('./shared/components/ToggleSwitch.vue'))['default']
  }
}

export {}
