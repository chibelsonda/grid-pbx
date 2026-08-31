import axios from 'axios'
import { defineStore } from 'pinia'
import { paymentApi } from '../api/paymentApi'
import type {
  PaymentAttempt,
  PaymentCapability,
  PaymentCustomerProfile,
  PaymentOpaqueData,
} from '../types/payment'

const errorMessage = (error: unknown, fallback: string): string =>
  axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback

export const usePaymentStore = defineStore('payments', {
  state: () => ({
    capability: null as PaymentCapability | null,
    attempts: [] as PaymentAttempt[],
    customerProfile: null as PaymentCustomerProfile | null,
    latestAttempt: null as PaymentAttempt | null,
    loading: false,
    charging: false,
    operating: false,
    error: null as string | null,
  }),
  actions: {
    reset(): void {
      this.capability = null
      this.attempts = []
      this.customerProfile = null
      this.latestAttempt = null
      this.loading = false
      this.charging = false
      this.operating = false
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
