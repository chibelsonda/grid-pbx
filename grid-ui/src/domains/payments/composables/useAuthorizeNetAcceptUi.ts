import { onBeforeUnmount, ref } from 'vue'
import type { PaymentOpaqueData } from '../types/payment'

type AcceptUiResponse = {
  opaqueData?: {
    dataDescriptor?: string
    dataValue?: string
  }
  messages?: {
    resultCode?: string
    message?: Array<{ code?: string }>
  }
}

declare global {
  interface Window {
    gridPbxAuthorizeNetResponseHandler?: (response: AcceptUiResponse) => void
  }
}

const handlerName = 'gridPbxAuthorizeNetResponseHandler'

export function useAuthorizeNetAcceptUi(
  onToken: (data: PaymentOpaqueData) => void | Promise<void>,
) {
  const ready = ref(false)
  const error = ref<string | null>(null)
  let script: HTMLScriptElement | null = null

  const load = (url: string): void => {
    if (script) return

    window.gridPbxAuthorizeNetResponseHandler = (response: AcceptUiResponse): void => {
      const descriptor = response.opaqueData?.dataDescriptor
      const value = response.opaqueData?.dataValue

      if (
        response.messages?.resultCode !== 'Ok' ||
        descriptor !== 'COMMON.ACCEPT.INAPP.PAYMENT' ||
        !value
      ) {
        const safeCode = response.messages?.message?.[0]?.code
        error.value = `Authorize.Net could not tokenize the payment details.${safeCode ? ` (${safeCode})` : ''} Try again.`

        return
      }

      error.value = null
      void onToken({ dataDescriptor: descriptor, dataValue: value })
    }

    script = document.createElement('script')
    script.src = url
    script.charset = 'utf-8'
    script.async = true
    script.dataset.gridPbxAcceptUi = 'true'
    script.addEventListener('load', () => {
      ready.value = true
    })
    script.addEventListener('error', () => {
      error.value = 'The secure Authorize.Net payment form could not be loaded.'
    })
    document.body.appendChild(script)
  }

  onBeforeUnmount(() => {
    script?.remove()
    script = null
    delete window.gridPbxAuthorizeNetResponseHandler
  })

  return { ready, error, load, handlerName }
}
