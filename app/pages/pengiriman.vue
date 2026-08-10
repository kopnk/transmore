<script setup lang="ts">
import dayjs from'dayjs';
import{toast}from'vue-sonner';
import{z}from'zod';
import type{Kebun,Kendaraan,Pks,Transaction}from'~/types';
const auth=useAuthStore();
const route=useRoute();
const{db,seed,audit,offlineDb}=useDatabase();
const{sync,enqueue}=useTransactionSync();
const kebuns=ref<Pick<Kebun,'nama'>[]>([]);
const pksList=ref<Pick<Pks,'nama'>[]>([]);
const rows=ref<Transaction[]>([]);
const vehicles=ref<Pick<Kendaraan,'tnkb'|'namaPemilik'>[]>([]);
const show=ref(false);
const editing=ref<number|undefined>();
const saving=ref(false);
const isManagement=computed(()=>auth.user?.role==='superadmin'||auth.user?.role==='admin');
const canApprove=isManagement;
const canChangeDriver=isManagement;
const canViewFinancial=isManagement;
const tableHeaders=computed(()=>[
  'No','Kebun - Divisi','PKS','SPB','Kendaraan','Odo','Driver','Muat','Berat Muatan','Bongkar','Berat Bongkar',
  ...(canViewFinancial.value?['Harga','Harga Muatan','Harga Bongkar','Fee','Fee Awal','Fee Bongkar']:[]),
  'Catatan','Link Doc','Status','Aksi'
]);
const newForm=()=>({
  idpengiriman:crypto.randomUUID(),
  number:'',
  date:dayjs().format('YYYY-MM-DDTHH:mm'),
  kebun:'',
  divisi:'',
  vehicle:'',
  pks:'',
  odoStart:0,
  odoEnd:0,
  driver:'',
  loadDate:dayjs().format('YYYY-MM-DDTHH:mm'),
  loadWeight:0,
  unloadDate:dayjs().format('YYYY-MM-DDTHH:mm'),
  unloadWeight:0,
  price:0,
  fee:0,
  receiverPic:'',
  notes:'',
  docLink:'',
  approved:false,
  status:'Draft' as Transaction['status']
});
const form=reactive(newForm());
const shipmentValue=(weight:number,rate:number)=>weight*rate;
const tonnageFee=(weight:number,fee:number)=>(weight/1000)*fee;
const filters=reactive({
  startDate:dayjs().startOf('month').format('YYYY-MM-DD'),
  endDate:dayjs().format('YYYY-MM-DD'),
  owner:'',
  tnkb:'',
  pks:'',
  kebun:'',
  divisi:'',
  driver:'',
  receiverPic:''
});
const receiverPics=computed(()=>[...new Set(rows.value.map(row=>row.receiverPic).filter(Boolean))]);
const divisions=computed(()=>[...new Set(rows.value.map(row=>row.divisi).filter(Boolean))]);
const kebunOptions=computed(()=>[...new Set(kebuns.value.map(row=>row.nama).filter(Boolean))]);
const vehicleOptions=computed(()=>[...new Set(vehicles.value.map(row=>row.tnkb).filter(Boolean))]);
const pksOptions=computed(()=>[...new Set(pksList.value.map(row=>row.nama).filter(Boolean))]);
const ownerFilterOptions=computed(()=>vehicles.value.map(row=>({label:row.namaPemilik,value:row.namaPemilik})));
const driverFilterOptions=computed(()=>[...new Set(rows.value.map(row=>row.driver).filter(Boolean))].map(value=>({label:value,value})));
const filteredRows=computed(()=>rows.value.filter(row=>{
  const rowDate=dayjs(row.date).format('YYYY-MM-DD');
  const vehicle=vehicles.value.find(item=>item.tnkb===row.vehicle);
  return rowDate>=filters.startDate && rowDate<=filters.endDate
    && (!filters.owner || vehicle?.namaPemilik===filters.owner)
    && (!filters.tnkb || row.vehicle===filters.tnkb)
    && (!filters.kebun || row.kebun===filters.kebun)
    && (!filters.divisi || row.divisi===filters.divisi)
    && (!filters.pks || row.pks===filters.pks)
    && (!filters.driver || row.driver===filters.driver)
    && (!filters.receiverPic || row.receiverPic===filters.receiverPic);
}));
async function load(){
  await seed();
  rows.value=await db.transactions.reverse().toArray();
  if(navigator.onLine)try{await sync();rows.value=await db.transactions.reverse().toArray()}catch{toast.error('Backend belum terhubung; data transaksi lokal tetap aman')}
  if(navigator.onLine)try{
    const response=await useApi().request<{kendaraan:Pick<Kendaraan,'tnkb'|'namaPemilik'>[];kebun:Pick<Kebun,'nama'>[];pks:Pick<Pks,'nama'>[]}>('/transaction-options');
    vehicles.value=response.data?.kendaraan||[];kebuns.value=response.data?.kebun||[];pksList.value=response.data?.pks||[];
  }catch{toast.error('Master data belum dapat dimuat dari backend')}
}
function resetForm(){
  Object.assign(form,newForm(),{driver:auth.user?.name||''});
}
function resetFilters(){Object.assign(filters,{startDate:dayjs().startOf('month').format('YYYY-MM-DD'),endDate:dayjs().format('YYYY-MM-DD'),owner:'',tnkb:'',kebun:'',divisi:'',pks:'',driver:'',receiverPic:''})}
function openNew(){
  editing.value=undefined;
  resetForm();
  show.value=true;
}
function canEditRow(row:Transaction){
  return !(row.status==='Selesai' && auth.user?.role==='driver');
}
function canDeleteRow(row:Transaction){
  return !(row.status==='Selesai' && auth.user?.role==='driver');
}
async function remove(row:Transaction){
  if(!auth.user?.permissions?.pengiriman?.delete) return toast.error('Tidak punya izin menghapus');
  if(row.status==='Selesai' && auth.user?.role==='driver') return toast.error('Pengiriman selesai tidak dapat dihapus oleh driver.');
  if(!confirm(`Hapus pengiriman ${row.number}? Data yang dihapus akan disinkronkan ke server.`))return;
  try{
    await offlineDb.transaction('rw',offlineDb.transactions,offlineDb.outbox,async()=>{
      await db.transactions.delete(row.id!);
      await enqueue('delete',row);
    });
    await audit('DELETE','Pengiriman',row.number);
    await load();
    toast.success('Pengiriman berhasil dihapus');
  }catch(error){toast.error(error instanceof Error?error.message:'Pengiriman gagal dihapus')}
}
function openRow(row:Transaction){
  if(!canEditRow(row)){
    return toast.error('Pengiriman selesai tidak dapat diubah oleh driver.');
  }
  editing.value=row.id;
  Object.assign(form,row,{docLink:row.docLink||''});
  show.value=true;
}
async function save(){
  if(saving.value)return;
  if(!canChangeDriver.value)form.driver=auth.user?.name||'';
  const result=z.object({
    idpengiriman:z.string().uuid('ID pengiriman tidak valid'),
    number:z.string().min(3,'No SPB harus diisi'),
    date:z.string().min(1,'Tanggal SPB harus diisi'),
    kebun:z.string().min(2,'Kebun harus diisi'),
    divisi:z.string().min(2,'Divisi harus diisi'),
    vehicle:z.string().min(2,'Kendaraan harus diisi'),
    odoStart:z.number().min(0,'Odo awal harus diisi'),
    pks:z.string().min(1,'PKS harus diisi'),
    odoEnd:z.number().min(0,'Odo akhir tidak valid'),
    driver:z.string().min(2,'Driver harus diisi'),
    loadDate:z.string(),
    loadWeight:z.number().min(0,'Berat muatan tidak valid'),
    unloadDate:z.string(),
    unloadWeight:z.number().min(0,'Berat bongkar tidak valid'),
    price:z.number().min(0,'Harga tidak valid'),
    fee:z.number().min(0,'Fee tidak valid'),
    receiverPic:z.string(),
    notes:z.string().optional(),
    docLink:z.union([z.literal(''),z.url('Link Doc harus berupa URL yang valid')]).optional(),
    approved:z.boolean(),
    status:z.enum(['Draft','Proses','Selesai'])
  }).safeParse(form);
  if(!result.success)return toast.error(result.error.issues[0]!.message);
  saving.value=true;
  try{if(editing.value){
    const existing=await db.transactions.get(editing.value);
    const saved={...existing,...form,createdBy:existing?.createdBy||auth.user?.email||'system',createdAt:existing?.createdAt||dayjs().toISOString(),updatedBy:auth.user?.email||'system',updatedAt:dayjs().toISOString()} as Transaction;
    await offlineDb.transaction('rw',offlineDb.transactions,offlineDb.outbox,async()=>{
      await db.transactions.update(editing.value!,saved);
      await enqueue('update',saved);
    });
    await audit('UPDATE','Pengiriman',form.number);
    toast.success('Pengiriman berhasil diperbarui');
  } else {
    const saved={...form,createdBy:auth.user?.email||'system',createdAt:dayjs().toISOString()} as Transaction;
    await offlineDb.transaction('rw',offlineDb.transactions,offlineDb.outbox,async()=>{
      await db.transactions.add(saved);
      await enqueue('create',saved);
    });
    await audit('CREATE','Pengiriman',form.number);
    toast.success('Pengiriman tersimpan secara lokal');
  }
  show.value=false;
  await load();
  }catch(error){toast.error(error instanceof Error?error.message:'Pengiriman gagal disimpan')}
  finally{saving.value=false}
}
async function toggleApprove(row:Transaction){
  if(!canApprove.value || row.id==null) return;
  await db.transactions.update(row.id,{approved:row.approved});
  await enqueue('update',row);
  await audit('APPROVE','Pengiriman',`${row.number} => ${row.approved ? 'Disetujui' : 'Dibatalkan'}`);
}
async function exportExcel(){
  const ExcelJS=(await import('exceljs')).default;
  const wb=new ExcelJS.Workbook();
  const ws=wb.addWorksheet('Pengiriman');
  ws.columns=[
    {header:'No',key:'no',width:6},
    {header:'Kebun',key:'kebun',width:18},
    {header:'Divisi',key:'divisi',width:16},
    {header:'PKS',key:'pks',width:18},
    {header:'Tgl SPB',key:'date',width:20},
    {header:'No SPB',key:'number',width:20},
    {header:'Kendaraan',key:'vehicle',width:18},
    {header:'Odo Awal',key:'odoStart',width:12},
    {header:'Odo Akhir',key:'odoEnd',width:12},
    {header:'Driver',key:'driver',width:18},
    {header:'Harga',key:'price',width:14},
    {header:'Muat',key:'loadDate',width:20},
    {header:'Berat Muatan',key:'loadWeight',width:16},
    {header:'Harga Muatan',key:'hargaMuat',width:18},
    {header:'Bongkar',key:'unloadDate',width:20},
    {header:'Berat Bongkar',key:'unloadWeight',width:16},
    {header:'Harga Bongkar',key:'hargaBongkar',width:18},
    {header:'Fee',key:'fee',width:14},
    {header:'Fee Awal',key:'feeAwal',width:16},
    {header:'Fee Bongkar',key:'feeBongkar',width:16},
    {header:'PIC Penerima',key:'receiverPic',width:18},
    {header:'Catatan',key:'notes',width:30},
    {header:'Status',key:'status',width:12}
  ];
  filteredRows.value.forEach((row,index)=>ws.addRow({
    no:index+1,
    kebun:row.kebun,
    divisi:row.divisi,
    pks:row.pks,
    date:dayjs(row.date).toDate(),
    number:row.number,
    vehicle:row.vehicle,
    odoStart:row.odoStart,
    odoEnd:row.odoEnd,
    driver:row.driver,
    price:row.price,
    loadDate:row.loadDate ? dayjs(row.loadDate).toDate() : '',
    loadWeight:row.loadWeight,
    hargaMuat:shipmentValue(row.loadWeight,row.price),
    unloadDate:row.unloadDate ? dayjs(row.unloadDate).toDate() : '',
    unloadWeight:row.unloadWeight,
    hargaBongkar:shipmentValue(row.unloadWeight,row.price),
    fee:row.fee,
    feeAwal:tonnageFee(row.loadWeight,row.fee),
    feeBongkar:tonnageFee(row.unloadWeight,row.fee),
    receiverPic:row.receiverPic,
    notes:row.notes,
    status:row.status
  }));
  const data=await wb.xlsx.writeBuffer();
  const url=URL.createObjectURL(new Blob([data]));
  const link=document.createElement('a');
  link.href=url;
  link.download=`pengiriman-${dayjs().format('YYYYMMDD-HHmm')}.xlsx`;
  link.click();
  URL.revokeObjectURL(url);
  await audit('EXPORT','Pengiriman',`${rows.value.length} baris`);
}
onMounted(async()=>{
  await auth.restore();
  await load();
  if(route.query.new)openNew();
});
watch(()=>route.query.new,(value,previous)=>{if(value&&value!==previous)openNew()});
</script>
<template>
  <div>
    <div class="mb-6 flex justify-between">
      <div>
        <h2 class="text-2xl font-bold">Filter Pengiriman</h2>
        <p class="text-sm text-slate-500">Pencatatan pengiriman muatan.</p>
      </div>
      <div class="flex gap-3">
        <button v-if="canViewFinancial" class="btn-secondary" @click="exportExcel">↓ Ekspor Excel</button>
        <button class="btn-primary" @click="openNew">＋ Pengiriman</button>
      </div>
    </div>
    <OperationsFilterCard class="mb-6" :filters="filters" :owners="ownerFilterOptions" :vehicles="vehicleOptions" :kebuns="kebunOptions" :divisions="divisions" :pks="pksOptions" :drivers="driverFilterOptions" :receivers="receiverPics" @reset="resetFilters" />
    <div class="card overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead>
          <tr>
            <th v-for="h in tableHeaders" :key="h" class="px-6 py-3">{{h}}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in filteredRows" :key="row.id" class="border-t">
            <td class="px-6 py-4 font-mono text-xs">{{idx + 1}}</td>
            <td class="px-6 py-4">{{row.kebun}} / {{row.divisi}}</td>
            <td class="px-6 py-4">{{row.pks||''}}</td>
            <td class="px-6 py-4">
              <div>{{dayjs(row.date).format('DD/MM/YYYY HH:mm')}}</div>
              <div class="font-semibold">{{row.number}}</div>
            </td>
            <td class="px-6 py-4">{{row.vehicle}}</td>
            <td class="px-6 py-4">
              <div class="font-semibold">{{(row.odoEnd-row.odoStart).toLocaleString('id-ID')}}</div>
              <div class="whitespace-nowrap text-xs text-slate-500">{{row.odoStart.toLocaleString('id-ID')}} - {{row.odoEnd.toLocaleString('id-ID')}}</div>
            </td>
            <td class="px-6 py-4">{{row.driver}}</td>
            <td class="px-6 py-4">{{dayjs(row.loadDate).format('DD/MM/YYYY HH:mm')}}</td>
            <td class="px-6 py-4">{{row.loadWeight.toLocaleString('id-ID')}} kg</td>
            <td class="px-6 py-4">{{dayjs(row.unloadDate).format('DD/MM/YYYY HH:mm')}}</td>
            <td class="px-6 py-4">{{row.unloadWeight.toLocaleString('id-ID')}} kg</td>
            <template v-if="canViewFinancial">
              <td class="px-6 py-4">Rp {{row.price.toLocaleString('id-ID')}}</td>
              <td class="px-6 py-4 whitespace-nowrap">Rp {{shipmentValue(row.loadWeight,row.price).toLocaleString('id-ID')}}</td>
              <td class="px-6 py-4 whitespace-nowrap">Rp {{shipmentValue(row.unloadWeight,row.price).toLocaleString('id-ID')}}</td>
              <td class="px-6 py-4">Rp {{row.fee.toLocaleString('id-ID')}}</td>
              <td class="px-6 py-4 whitespace-nowrap">Rp {{tonnageFee(row.loadWeight,row.fee).toLocaleString('id-ID',{maximumFractionDigits:2})}}</td>
              <td class="px-6 py-4 whitespace-nowrap">Rp {{tonnageFee(row.unloadWeight,row.fee).toLocaleString('id-ID',{maximumFractionDigits:2})}}</td>
            </template>
            <td class="px-6 py-4 max-w-xs truncate">{{row.notes}}</td>
            <td class="max-w-xs select-all truncate px-6 py-4" :title="row.docLink||''">{{row.docLink||'-'}}</td>
            <td class="px-6 py-4">{{row.status}}</td>
            <td class="px-6 py-4">
              <div class="mb-3 whitespace-nowrap text-xs text-slate-400">
                <p><b>Created:</b> {{row.createdBy}}</p><p>{{dayjs(row.createdAt).format('DD/MM/YYYY HH:mm:ss')}}</p>
                <template v-if="row.updatedAt"><p class="mt-2"><b>Updated:</b> {{row.updatedBy}}</p><p>{{dayjs(row.updatedAt).format('DD/MM/YYYY HH:mm:ss')}}</p></template>
              </div>
              <div class="flex gap-3">
                <button v-if="canEditRow(row) && auth.user?.permissions?.pengiriman?.update" type="button" class="font-medium text-ocean-600" @click="openRow(row)">Update</button>
                <button v-if="canDeleteRow(row) && auth.user?.permissions?.pengiriman?.delete" type="button" class="font-medium text-red-500" @click="remove(row)">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
        <div v-if="show" class="fixed inset-0 z-50 overflow-auto bg-black/50 p-4">
          <form class="card w-full max-w-4xl p-6 mx-auto my-8 flex flex-col max-h-[calc(100vh-120px)]" @submit.prevent="save">
        <h3 class="mb-5 text-xl font-bold">{{editing!==undefined?'Update':'Tambah'}} Pengiriman</h3>
        <div class="grid gap-4 sm:grid-cols-2 flex-1 overflow-auto pr-2">
          <!-- ID hidden from form as requested -->
          <div>
            <label class="label">No SPB *</label>
            <input v-model="form.number" class="input" placeholder="No SPB" required>
          </div>
          <div>
            <label class="label">Tgl SPB *</label>
            <input v-model="form.date" type="datetime-local" class="input" placeholder="Tgl SPB" required>
          </div>
          <div>
            <label class="label">Kebun *</label>
            <select v-model="form.kebun" class="input" required>
              <option value="" disabled>Pilih Kebun</option>
              <option v-for="name in kebunOptions" :key="name" :value="name">{{name}}</option>
            </select>
          </div>
          <div>
            <label class="label">Divisi *</label>
            <input v-model="form.divisi" class="input" placeholder="Divisi" required>
          </div>
          <div>
            <label class="label">Kendaraan *</label>
            <select v-model="form.vehicle" class="input" required>
              <option value="" disabled>Pilih Kendaraan</option>
              <option v-for="tnkb in vehicleOptions" :key="tnkb" :value="tnkb">{{tnkb}}</option>
            </select>
          </div>
          <div>
            <label class="label">Driver *</label>
            <input v-model="form.driver" class="input disabled:bg-slate-100 disabled:text-slate-500" placeholder="Driver" :disabled="!canChangeDriver" required>
          </div>
          <div>
            <label class="label">Odo Awal *</label>
            <input v-model.number="form.odoStart" type="number" step="any" inputmode="decimal" class="input" placeholder="Odo Awal" min="0" required>
          </div>
          <div>
            <label class="label">Odo Akhir</label>
            <input v-model.number="form.odoEnd" type="number" step="any" inputmode="decimal" class="input" placeholder="Odo Akhir">
          </div>
          <div>
            <label class="label">Tgl + Jam Muat</label>
            <input v-model="form.loadDate" type="datetime-local" class="input" placeholder="Tgl + Jam Muat">
          </div>
          <div>
            <label class="label">Berat Muatan (kg)</label>
            <input v-model.number="form.loadWeight" type="number" step="any" inputmode="decimal" class="input" placeholder="Berat Muatan (kg)">
          </div>
          <div>
            <label class="label">Tgl + Jam Bongkar</label>
            <input v-model="form.unloadDate" type="datetime-local" class="input" placeholder="Tgl + Jam Bongkar">
          </div>
          <div>
            <label class="label">Berat Bongkar (kg)</label>
            <input v-model.number="form.unloadWeight" type="number" step="any" inputmode="decimal" class="input" placeholder="Berat Bongkar (kg)">
          </div>
          <div>
            <label class="label">PKS *</label>
            <select v-model="form.pks" class="input" required>
              <option value="" disabled>Pilih PKS</option>
              <option v-for="name in pksOptions" :key="name" :value="name">{{name}}</option>
            </select>
          </div>
          <div v-if="canViewFinancial">
            <label class="label">Harga</label>
            <input v-model.number="form.price" type="number" step="any" inputmode="decimal" class="input" placeholder="Harga">
          </div>
          <div v-if="canViewFinancial">
            <label class="label">Fee</label>
            <input v-model.number="form.fee" type="number" step="any" inputmode="decimal" class="input" placeholder="Fee">
          </div>
          <div>
            <label class="label">PIC Penerima</label>
            <input v-model="form.receiverPic" class="input" placeholder="PIC Penerima">
          </div>
          <div>
            <label class="label">Catatan</label>
            <input v-model="form.notes" class="input" placeholder="Catatan">
          </div>
          <div>
            <label class="label">Status</label>
            <select v-model="form.status" class="input"><option>Draft</option><option>Proses</option><option>Selesai</option></select>
          </div>
          <div class="sm:col-span-2">
            <label class="label">Link Doc</label>
            <input v-model="form.docLink" type="text" class="input" placeholder="https://..." autocomplete="off">
            <p class="mt-1 text-xs text-slate-500">Link disimpan sebagai teks dan hanya dapat disalin, bukan diklik langsung.</p>
          </div>
        </div>
        <div class="mt-6 flex-shrink-0 flex justify-end gap-3">
          <button type="button" class="btn-secondary" :disabled="saving" @click="show=false">Batal</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{saving?(editing!==undefined?'Mengupdate...':'Menyimpan...'):(editing!==undefined?'Update':'Simpan')}}</button>
        </div>
      </form>
    </div>
  </div>
</template>
