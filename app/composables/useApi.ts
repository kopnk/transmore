export interface ApiResponse<T = unknown> {
  success: boolean
  data?: T
  user?: T
  message?: string
  errors?: Record<string, string>
  offlineGrant?: {token:string;publicKey:string;expiresAt:string}
}

export class ApiError extends Error {
  constructor(message: string, public status = 0, public errors?: Record<string, string>) {
    super(message)
  }
}

export const useApi = () => {
  const config = useRuntimeConfig()
  const request = async <T>(path: string, options: Parameters<typeof $fetch>[1] = {}) => {
    try {
      return await $fetch<ApiResponse<T>>(path, {
        baseURL: config.public.apiBase,
        credentials: 'include',
        ...options,
      })
    } catch (error: any) {
      const body = error?.data as ApiResponse | undefined
      const validation = body?.errors ? Object.values(body.errors)[0] : undefined
      throw new ApiError(validation || body?.message || 'Tidak dapat terhubung ke server', error?.status || 0, body?.errors)
    }
  }
  return { request }
}
