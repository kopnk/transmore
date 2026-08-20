import { toast } from 'vue-sonner'
import type { ZodError } from 'zod'

export function notifyValidationErrors(error: ZodError): void {
  const messages = [...new Set(error.issues.map(issue => issue.message))]
  const [title = 'Data form tidak valid', ...details] = messages
  toast.error(title, {
    description: details.length ? details.join(' • ') : 'Periksa kembali isian form Anda.',
  })
}
