import { Chart as ChartJS, Filler } from 'chart.js'

export default defineNuxtPlugin(()=>{
  ChartJS.register(Filler)
})
