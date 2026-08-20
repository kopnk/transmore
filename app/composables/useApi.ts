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
      const status = Number(error?.status || error?.statusCode || 0)
      const fallback = status === 401 ? 'Sesi Anda telah berakhir. Silakan masuk kembali.'
        : status === 403 ? 'Anda tidak memiliki izin untuk melakukan tindakan ini.'
          : status === 404 ? 'Data atau layanan yang diminta tidak ditemukan.'
            : status === 409 ? 'Data yang sama sudah digunakan.'
              : status === 429 ? 'Terlalu banyak permintaan. Silakan tunggu dan coba lagi.'
                : status >= 500 ? 'Server mengalami masalah. Silakan coba lagi.'
                  : 'Tidak dapat terhubung ke server.'
      throw new ApiError(validation || body?.message || fallback, status, body?.errors)
    }
  }
  return { request }
}
