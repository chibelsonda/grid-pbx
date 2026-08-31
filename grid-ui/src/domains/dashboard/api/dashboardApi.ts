import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import {
  callActivityTrendSchema,
  type CallActivityRange,
  type CallActivityTrend,
} from '../schemas/callActivityTrendSchema'
import { dashboardOverviewSchema, type DashboardOverview } from '../schemas/dashboardOverviewSchema'
import { callGeographySchema, type CallGeography } from '../schemas/callGeographySchema'
import { callQualitySchema, type CallQuality } from '../schemas/callQualitySchema'
import { recentMissedCallsSchema, type RecentMissedCalls } from '../schemas/recentMissedCallsSchema'
import {
  topCallDestinationsSchema,
  type TopCallDestinations,
} from '../schemas/topCallDestinationsSchema'

export const dashboardApi = {
  async overview(accountId: string): Promise<DashboardOverview> {
    return dashboardOverviewSchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<DashboardOverview>>(`/api/v1/accounts/${accountId}/dashboard`),
      ),
    )
  },

  async callActivity(accountId: string, range: CallActivityRange): Promise<CallActivityTrend> {
    return callActivityTrendSchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<CallActivityTrend>>(
          `/api/v1/accounts/${accountId}/dashboard/call-activity`,
          { params: { range } },
        ),
      ),
    )
  },

  async callGeography(accountId: string, range: CallActivityRange): Promise<CallGeography> {
    return callGeographySchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<CallGeography>>(
          `/api/v1/accounts/${accountId}/dashboard/call-geography`,
          { params: { range } },
        ),
      ),
    )
  },

  async callQuality(accountId: string, range: CallActivityRange): Promise<CallQuality> {
    return callQualitySchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<CallQuality>>(
          `/api/v1/accounts/${accountId}/dashboard/call-quality`,
          { params: { range } },
        ),
      ),
    )
  },

  async recentMissedCalls(accountId: string, range: CallActivityRange): Promise<RecentMissedCalls> {
    return recentMissedCallsSchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<RecentMissedCalls>>(
          `/api/v1/accounts/${accountId}/dashboard/recent-missed-calls`,
          { params: { range } },
        ),
      ),
    )
  },

  async topDestinations(accountId: string, range: CallActivityRange): Promise<TopCallDestinations> {
    return topCallDestinationsSchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<TopCallDestinations>>(
          `/api/v1/accounts/${accountId}/dashboard/top-destinations`,
          { params: { range } },
        ),
      ),
    )
  },
}
