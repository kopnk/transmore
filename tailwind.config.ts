import type { Config } from 'tailwindcss'
export default <Partial<Config>>{ content: ['./app/**/*.{vue,js,ts}'], theme: { extend: { colors: { brand: { 50:'#effaf8',100:'#d7f3ee',500:'#0d9488',600:'#0f766e',700:'#115e59' }, ocean:{500:'#2563eb',600:'#1d4ed8',700:'#1e40af'} }, boxShadow:{soft:'0 10px 30px rgba(15, 23, 42, .08)'} } } }
