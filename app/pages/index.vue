<script setup lang="ts">
import dayjs from 'dayjs'
import type {Kebun,Kendaraan,Pks,Transaction,User} from '~/types'
const{db,seed}=useDatabase();const transactions=ref<Transaction[]>([]);const vehicles=ref<Kendaraan[]>([]);const pksList=ref<Pks[]>([]);const kebunList=ref<Kebun[]>([]);const drivers=ref<User[]>([])
const filters=reactive({startDate:dayjs().startOf('month').format('YYYY-MM-DD'),endDate:dayjs().format('YYYY-MM-DD'),owner:'',tnkb:'',pks:'',kebun:'',divisi:'',driver:'',receiverPic:''})
const filtered=computed(()=>transactions.value.filter(row=>{const date=dayjs(row.date).format('YYYY-MM-DD');const vehicle=vehicles.value.find(item=>item.tnkb===row.vehicle);return date>=filters.startDate&&date<=filters.endDate&&(!filters.owner||vehicle?.namaPemilik===filters.owner)&&(!filters.tnkb||row.vehicle===filters.tnkb)&&(!filters.kebun||row.kebun===filters.kebun)&&(!filters.divisi||row.divisi===filters.divisi)&&(!filters.pks||row.pks===filters.pks)&&(!filters.driver||row.driver===filters.driver)&&(!filters.receiverPic||row.receiverPic===filters.receiverPic)}))
const stats=computed(()=>{const totalLoadWeight=filtered.value.reduce((total,row)=>total+row.loadWeight,0);const totalUnloadWeight=filtered.value.reduce((total,row)=>total+row.unloadWeight,0);const totalLoadPrice=filtered.value.reduce((total,row)=>total+row.price*row.loadWeight,0);const totalUnloadPrice=filtered.value.reduce((total,row)=>total+row.price*row.unloadWeight,0);return{
    users:drivers.value.length,
    vehicles:new Set(filtered.value.map(row=>row.vehicle)).size,
    transactions:filtered.value.length,
    loadWeight:totalLoadWeight,
    loadMoney:totalLoadPrice,
    unloadWeight:totalUnloadWeight,
    unloadMoney:totalUnloadPrice,
    diffWeight:totalLoadWeight-totalUnloadWeight,
    diffMoney:totalLoadPrice-totalUnloadPrice
  }});
const chartData=computed(()=>{const start=dayjs(filters.startDate);const end=dayjs(filters.endDate);const labels:string[]=[];const dates:string[]=[];let current=start;while(current.valueOf()<=end.valueOf()){dates.push(current.format('YYYY-MM-DD'));labels.push(current.format('DD MMM'));current=current.add(1,'day')}const loadSeries=dates.map(date=>filtered.value.reduce((total,row)=>total+(dayjs(row.date).format('YYYY-MM-DD')===date?row.loadWeight:0),0));const unloadSeries=dates.map(date=>filtered.value.reduce((total,row)=>total+(dayjs(row.date).format('YYYY-MM-DD')===date?row.unloadWeight:0),0));const priceSeries=dates.map(date=>filtered.value.reduce((total,row)=>total+(dayjs(row.date).format('YYYY-MM-DD')===date?row.price*row.unloadWeight:0),0));return{labels,loadSeries,unloadSeries,priceSeries}});
const recent=computed(()=>[...filtered.value].sort((a,b)=>dayjs(b.date).valueOf()-dayjs(a.date).valueOf()).slice(0,5));const receiverPics=computed(()=>[...new Set(transactions.value.map(item=>item.receiverPic).filter(Boolean))]);const divisions=computed(()=>[...new Set(transactions.value.map(item=>item.divisi).filter(Boolean))]);const ownerOptions=computed(()=>vehicles.value.map(row=>({label:row.namaPemilik,value:row.namaPemilik})));const driverOptions=computed(()=>drivers.value.map(row=>({label:row.name,value:row.email})))
function resetFilters(){Object.assign(filters,{startDate:dayjs().startOf('month').format('YYYY-MM-DD'),endDate:dayjs().format('YYYY-MM-DD'),owner:'',tnkb:'',pks:'',kebun:'',divisi:'',driver:'',receiverPic:''})}
onMounted(async()=>{await seed();[transactions.value,vehicles.value,pksList.value,kebunList.value,drivers.value]=await Promise.all([db.transactions.toArray(),db.kendaraan.toArray(),db.pks.toArray(),db.kebun.toArray(),db.users.where('role').equals('driver').toArray()])})
</script>
<template><div class="space-y-6"><div><h2 class="text-2xl font-bold">Filter Dashboard</h2><p class="mt-1 text-sm text-slate-500">Ringkasan aktivitas berdasarkan periode dan parameter yang dipilih.</p></div>
<OperationsFilterCard :filters="filters" :owners="ownerOptions" :vehicles="vehicles.map(row=>row.tnkb)" :kebuns="kebunList.map(row=>row.nama)" :divisions="divisions" :pks="pksList.map(row=>row.nama)" :drivers="driverOptions" :receivers="receiverPics" @reset="resetFilters" />
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <StatCard
    label="Total Pengiriman"
    :value="stats.transactions"
    icon="⇄"
    color="blue"
  />

  <StatCard
    label="Berat Muatan"
    :value="(stats.loadWeight / 1000).toFixed(2) + ' T\nRp. ' + stats.loadMoney.toLocaleString('id-ID')"
    icon="▰"
  />

  <StatCard
    label="Berat Bongkar"
    :value="(stats.unloadWeight / 1000).toFixed(2) + ' T\nRp. ' + stats.unloadMoney.toLocaleString('id-ID')"
    icon="◉"
    color="amber"
  />

  <StatCard
    label="Selisih Berat"
    :value="(stats.diffWeight / 1000).toFixed(2) + ' T\nRp. ' + stats.diffMoney.toLocaleString('id-ID')"
    icon="⇆"
  />
</div>
<div class="grid gap-6"><section class="card p-6"><div class="mb-5"><div><h3 class="font-bold">Performa Angkutan</h3><p class="text-sm text-slate-500">Tonase muat dan bongkar per tanggal.</p></div></div><OperationsChart :labels="chartData.labels" :load-data="chartData.loadSeries" :unload-data="chartData.unloadSeries" :price-data="chartData.priceSeries"/></section></div>
</div></template>
