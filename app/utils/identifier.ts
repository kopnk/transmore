import { z } from 'zod'

export const normalizePhone=(value:string)=>value.trim().replace(/[\s().-]/g,'')
export const phoneSchema=z.string().transform(normalizePhone).pipe(z.string().regex(/^\+?[0-9]{8,15}$/,'Nomor HP harus terdiri dari 8–15 digit'))
export const identifierSchema=z.string().trim().min(1,'Email atau nomor HP wajib diisi').refine(value=>z.email().safeParse(value).success||phoneSchema.safeParse(value).success,'Masukkan email atau nomor HP yang valid')
