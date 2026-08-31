import axios from 'axios'
import { defineStore } from 'pinia'
import { paymentApi } from '../api/paymentApi'
import type {
  PaymentAttempt,
  PaymentAttemptDetail,
  PaymentCapability,
  PaymentCustomerProfile,
  PaymentOpaqueData,
  PaymentWebhookHealth,
} from '../types/payment'

const errorMessage = (error: unknown, fallback: string): string =>
  axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback

export const usePaymentStore = defineStore('payments', {
  state: () => ({
    capability: null as PaymentCapability | null,
    attempts: [] as PaymentAttempt[],
    attemptDetails: {} as Record<string, PaymentAttemptDetail>,
    customerProfiles: [] as PaymentCustomerProfile[],
    customerProfile: null as PaymentCustomerProfile | null,
    latestAttempt: null as PaymentAttempt | null,
    webhookHealth: null as PaymentWebhookHealth | null,
    loading: false,
    charging: false,
    operating: false,
    loadingAttemptId: null as string | null,
    recoveringWebhookId: null as string | null,
    error: null as string | null,
  }),
  actions: {
    reset(): void {
      this.capability = null
      this.attempts = []
      this.attemptDetails = {}
      this.customerProfiles = []
      this.customerProfile = null
      this.latestAttempt = null
      this.webhookHealth = null
      this.loading = false
      this.charging = false
      this.operating = false
      this.loadingAttemptId = null
      this.recoveringWebhookId = null
      this.error = null
    },
    async loadCapability(accountId: string): Promise<void> {
      this.loading = true
      this.error = null

      try {
        this.capability = await paymentApi.capabilities(accountId)
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load the payment-provider status.')
      } finally {
        this.loading = false
      }
    },
    async loadAttempts(accountId: string): Promise<void> {
      try {
        this.attempts = await paymentApi.attempts(accountId)
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load recent payment attempts.')
      }
    },
    async loadAttempt(accountId: string, attemptId: string): Promise<boolean> {
      this.loadingAttemptId = attemptId
      this.error = null

      try {
        this.attemptDetails[attemptId] = await paymentApi.attempt(accountId, attemptId)

        return true
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load the payment attempt timeline.')

        return false
      } finally {
        this.loadingAttemptId = null
      }
    },
    async loadProfiles(accountId: string): Promise<void> {
      try {
        this.customerProfiles = await paymentApi.profiles(accountId)
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load saved payment profiles.')
      }
    },
    async loadWebhookHealth(accountId: string): Promise<void> {
      try {
        this.webhookHealth = await paymentApi.webhookHealth(accountId)
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load webhook reconciliation health.')
      }
    },
    async retryWebhook(accountId: string, deliveryId: string): Promise<boolean> {
      this.recoveringWebhookId = deliveryId
      this.error = null

      try {
        const delivery = await paymentApi.retryWebhook(accountId, deliveryId)

        if (this.webhookHealth) {
          this.webhookHealth.deliveries = this.webhookHealth.deliveries.map((existing) =>
            existing.id === delivery.id ? delivery : existing,
          )
        }

        await Promise.all([this.loadWebhookHealth(accountId), this.loadAttempts(accountId)])

        return true
      } catch (error) {
        this.error = errorMessage(error, 'Webhook reconciliation could not be retried.')

        return false
      } finally {
        this.recoveringWebhookId = null
      }
    },
    async sandboxCharge(
      accountId: string,
      amountMinor: number,
      opaqueData: PaymentOpaqueData,
    ): Promise<void> {
      this.charging = true
      this.error = null

      try {
        this.latestAttempt = await paymentApi.sandboxCharge(
          accountId,
          amountMinor,
          opaqueData,
          crypto.randomUUID(),
        )
        this.attempts = [this.latestAttempt, ...this.attempts]
      } catch (error) {
        this.error = errorMessage(error, 'The sandbox payment could not be confirmed.')
      } finally {
        this.charging = false
      }
    },
    async sandboxVoid(accountId: string, sourceAttemptId: string): Promise<boolean> {
      this.operating = true
      this.error = null

      try {
        this.latestAttempt = await paymentApi.sandboxVoid(
          accountId,
          sourceAttemptId,
          crypto.randomUUID(),
        )
        await this.loadAttempts(accountId)

        return true
      } catch (error) {
        this.error = errorMessage(error, 'The sandbox void could not be confirmed.')

        return false
      } finally {
        this.operating = false
      }
    },
    async sandboxRefund(
      accountId: string,
      sourceAttemptId: string,
      amountMinor: number,
    ): Promise<boolean> {
      this.operating = true
      this.error = null

      try {
        this.latestAttempt = await paymentApi.sandboxRefund(
          accountId,
          sourceAttemptId,
          amountMinor,
          crypto.randomUUID(),
        )
        await this.loadAttempts(accountId)

        return true
      } catch (error) {
        this.error = errorMessage(error, 'The sandbox refund could not be confirmed.')

        return false
      } finally {
        this.operating = false
      }
    },
    async createSandboxCustomerProfile(
      accountId: string,
      sourceAttemptId: string,
    ): Promise<boolean> {
      this.operating = true
      this.error = null

      try {
        const outcome = await paymentApi.createSandboxCustomerProfile(
          accountId,
          sourceAttemptId,
          crypto.randomUUID(),
        )
        this.latestAttempt = outcome.attempt
        this.customerProfile = outcome.profile
        const createdProfile = outcome.profile

        if (createdProfile) {
          this.customerProfiles = [
            createdProfile,
            ...this.customerProfiles.filter((profile) => profile.id !== createdProfile.id),
          ]
        }
        await this.loadAttempts(accountId)

        return true
      } catch (error) {
        this.error = errorMessage(error, 'The sandbox payment profile could not be created.')

        return false
      } finally {
        this.operating = false
      }
    },
  },
})
