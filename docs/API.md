# Belakang Toko API

Base URL pengembangan: `http://localhost:8000/api/v1`

Semua endpoint selain login memerlukan:

```http
Accept: application/json
Authorization: Bearer {token}
```

Respons operasi simpan menggunakan bentuk:

```json
{
  "message": "Operasi berhasil",
  "data": {}
}
```

## Autentikasi

### Login

`POST /auth/login`

```json
{
  "username": "admin",
  "password": "password"
}
```

```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin",
      "username": "admin"
    },
    "token": "1|..."
  }
}
```

### Profil dan logout

- `GET /auth/me`
- `POST /auth/logout`

## Dashboard

`GET /dashboard`

Mengembalikan satu paket data dashboard agar frontend tidak perlu melakukan banyak
request terpisah:

- ringkasan penjualan bersih, transaksi, laba kotor, penerimaan, retur, dan stok;
- tren penjualan bersih tujuh hari terakhir;
- produk terlaris bulan berjalan;
- produk yang stoknya sudah mencapai batas minimum;
- komposisi metode pembayaran hari ini;
- aktivitas transaksi terbaru.

## Master satuan

- `GET /master/satuan/get-satuan?search=&page=1&per_page=15`
- `GET /master/satuan/get-all`
- `POST /master/satuan/simpan`
- `DELETE /master/satuan/hapus/{id}`

Body tambah/edit:

```json
{
  "id": null,
  "satuan": "Dus"
}
```

Isi `id` untuk memperbarui data. Penghapusan satuan bersifat soft delete melalui `flaging`.

## Master supplier

- `GET /master/supplier/get-data?search=&page=1&per_page=15`
- `GET /master/supplier/get-data-all`
- `GET /master/supplier/detail/{id}`
- `POST /master/supplier/simpan`
- `DELETE /master/supplier/hapus/{id}`

```json
{
  "id": null,
  "nama": "Supplier Utama",
  "alamat": "Jakarta",
  "telepon": "08123456789",
  "rekening": "123456"
}
```

## Master merk

- `GET /master/merk/get-data?search=&page=1&per_page=15`
- `GET /master/merk/get-all`
- `GET /master/merk/detail/{id}`
- `POST /master/merk/simpan`
- `DELETE /master/merk/hapus/{id}`

```json
{
  "id": null,
  "merk": "Merek Contoh"
}
```

## Master barang

- `GET /master/barang/get-data?search=&page=1&per_page=15`
- `GET /master/barang/get-data-all`
- `POST /master/barang/simpan`
- `POST /master/barang/hapus`

```json
{
  "kodebarang": "BRG0001",
  "kodebarcode": "899000000001",
  "namabarang": "Air Mineral",
  "jenisbarang": "Minuman",
  "keterangan": "600 ML",
  "merk": "Segar",
  "satuanbesar": "Dus",
  "satuankecil": "Botol",
  "isisatuan": 24,
  "limitstok": 10,
  "hargajual_satuankecil": 4000,
  "hargajual_satuanbesar": 90000
}
```

Untuk menghapus barang:

```json
{
  "id": 1
}
```

## Master pelanggan

- `GET /master/pelanggan/get-data?search=&page=1&per_page=12`
- `GET /master/pelanggan/detail/{id}`
- `POST /master/pelanggan/simpan`
- `DELETE /master/pelanggan/hapus/{id}`

Body tambah/edit:

```json
{
  "id": null,
  "nama": "Budi Santoso",
  "alamat": "Jalan Merdeka",
  "telepon": "08123456789",
  "email": "budi@example.com"
}
```

## Gudang penerimaan

- `GET /gudang/penerimaan/get-data`
- `GET /gudang/penerimaan/detail/{id}`
- `POST /gudang/penerimaan/simpan`
- `POST /gudang/penerimaan/kirim-stok/{id}`
- `DELETE /gudang/penerimaan/hapus/{id}`

Parameter daftar:

- `search`: nomor transaksi atau faktur
- `supplier_id`
- `tanggal_awal`: format `YYYY-MM-DD`
- `tanggal_akhir`: format `YYYY-MM-DD`
- `page`
- `per_page`

Body tambah/edit:

```json
{
  "id": null,
  "nomorfaktur": "F-001",
  "tanggal": "2026-07-25",
  "tglfaktur": "2026-07-24",
  "supplier_id": 1,
  "cara_bayar": "CASH",
  "catatan": "Pengiriman pagi",
  "status": "Draft",
  "pajakpersen": 11,
  "rincian": [
    {
      "barang_id": 1,
      "qtybesar": 2,
      "isi": 24,
      "hargabeli": 80000,
      "diskonpersen": 5,
      "diskonnominal": 0
    }
  ]
}
```

Nomor transaksi dibuat otomatis dengan format `TRM-YYYYMMDD-0001`. Nilai jumlah item,
subtotal, diskon, pajak, dan grand total dihitung ulang oleh server di dalam transaksi
database. Barang yang sama tidak boleh muncul lebih dari sekali dalam satu penerimaan.

### Kirim penerimaan ke stok

`cara_bayar` wajib berisi `CASH` atau `HUTANG`.

`POST /gudang/penerimaan/kirim-stok/{id}` membuat satu lot stok untuk setiap rincian
barang, mencatat mutasi masuk, mengubah `flaging` menjadi `1`, lalu mengubah status
penerimaan dari `Draft` menjadi `Terkunci`.
Operasi ini idempoten: penerimaan yang sudah dikirim tidak dapat dikirim ulang, diedit,
atau dihapus.

Sebelum lot dibuat, server membandingkan harga perolehan efektif setelah diskon dengan
harga jual pada Master Barang untuk satuan kecil dan satuan besar. Jika salah satu harga
jual lebih rendah daripada harga perolehannya, pengiriman stok ditolak; penerimaan tetap
`Draft` dan tidak ada lot maupun mutasi stok yang dibuat.

Tabel `stok` menyimpan posisi persediaan per lot untuk kebutuhan FIFO:

- kode dan urutan lot;
- barang, supplier, penerimaan, dan rincian sumber;
- tanggal masuk dan tanggal kedaluwarsa;
- qty masuk, keluar, dan tersedia dalam satuan kecil;
- harga efektif per unit setelah diskon;
- nilai awal dan nilai persediaan tersisa;
- status `TERSEDIA` atau `HABIS`;
- metadata harga dan kemasan sumber.

Tabel `stok_mutasi` merupakan buku besar persediaan yang mencatat setiap pergerakan
masuk/keluar, saldo sebelum dan sesudah, nilai mutasi, sumber transaksi, referensi,
pengguna, dan waktu mutasi. Pengeluaran FIFO mengambil lot berdasarkan
`tanggal_masuk` paling lama, kemudian `id` lot paling kecil.

Response `GET /gudang/stok/get-data` menyertakan rekap per lot dan rekap global untuk
`qty_penerimaan`, `qty_penjualan`, `qty_retur_penjualan`, dan
`qty_retur_pembelian`. Saldo tersedia dan nilai persediaan tetap dibaca dari tabel
`stok`, sedangkan rekap pergerakan dihitung dari `stok_mutasi` agar pembuatan maupun
penghapusan retur langsung tercermin secara konsisten.

## Penjualan

Semua endpoint berikut memerlukan token Sanctum:

- `GET /penjualan/get-data`
- `GET /penjualan/detail/{id}`
- `POST /penjualan/simpan`

Payload penyimpanan:

```json
{
  "cara_bayar": "HUTANG",
  "dibayar": 0,
  "pelanggan_id": 1,
  "items": [
    { "barang_id": 1, "qty": 3 }
  ]
}
```

`cara_bayar` menerima `CASH`, `DEBIT`, `QRIS`, atau `HUTANG`. Pelanggan wajib dipilih
untuk pembayaran hutang. Transaksi hutang menyimpan `dibayar` dan `kembalian` sebagai
nol serta mencatat seluruh nilai transaksi pada `sisa_hutang`. Harga jual selalu diambil ulang
dari master barang oleh server. Penyimpanan header, rincian, dan pengeluaran lot FIFO
dijalankan dalam satu transaksi database. Jika stok salah satu barang tidak cukup,
seluruh transaksi dibatalkan dan tidak ada stok yang berubah.

### Retur penjualan

- `GET /penjualan/retur/get-data`
- `GET /penjualan/retur/detail/{id}`
- `GET /penjualan/retur/transaksi/{id}`
- `POST /penjualan/retur/simpan`
- `DELETE /penjualan/retur/hapus/{id}`

Retur harus mengacu pada rincian penjualan asli dan tidak boleh melebihi jumlah yang
belum pernah diretur. Kuantitas dikonversi kembali ke satuan kecil, dimasukkan ke lot
FIFO asal sesuai `alokasi_fifo`, lalu dicatat sebagai mutasi `MASUK` dengan sumber
`RETUR_PENJUALAN`. Header retur, rincian, saldo lot, dan mutasi disimpan dalam satu
transaksi database. Retur hanya dapat dibuat maksimal 3×24 jam sejak waktu transaksi
penjualan; transaksi yang lebih lama tidak ditampilkan sebagai pilihan dan ditolak oleh
backend.
Penghapusan retur membalikkan stok ke kondisi sebelum retur dan menghapus mutasi retur.
Jika stok hasil retur penjualan sudah digunakan kembali, penghapusan ditolak.

## Retur Pembelian

- `GET /gudang/retur-pembelian/get-data`
- `GET /gudang/retur-pembelian/penerimaan`
- `GET /gudang/retur-pembelian/penerimaan/{id}`
- `GET /gudang/retur-pembelian/detail/{id}`
- `POST /gudang/retur-pembelian/simpan`
- `DELETE /gudang/retur-pembelian/hapus/{id}`

Hanya penerimaan dengan `flaging = 1` dan `stok_terkirim_at` terisi yang dapat menjadi
sumber retur. Jumlah retur tidak boleh melebihi `qty_tersedia` pada lot penerimaan
tersebut. Retur mendukung satuan kecil dan besar, mengurangi saldo lot asal, serta
mencatat mutasi `KELUAR` dengan sumber `RETUR_PEMBELIAN`. Seluruh header, rincian,
saldo stok, nilai stok, dan mutasi disimpan dalam satu transaksi database.
Penghapusan retur pembelian mengembalikan kuantitas ke lot asal dan menghapus mutasi
`RETUR_PEMBELIAN`.

## Kartu stok

`GET /laporan/kartu-stok?barang_id=1&tanggal_awal=2026-07-01&tanggal_akhir=2026-07-31&page=1&per_page=25`

Parameter `barang_id` wajib diisi. Laporan mengembalikan identitas barang, mutasi
secara kronologis, dan ringkasan:

- saldo awal sebelum periode;
- total masuk dan keluar dalam periode;
- saldo akhir periode;
- saldo persediaan saat ini.

Field `saldo_kartu` pada setiap baris dihitung lintas seluruh lot FIFO untuk kode
barang yang sama. Karena itu saldo kartu stok tidak menggunakan `saldo_sesudah` lot
secara langsung. Data mutasi menyertakan lot, sumber transaksi, nomor referensi,
kuantitas masuk/keluar, harga per unit, nilai mutasi, keterangan, serta pengguna.

## Status HTTP

| Status | Arti |
|---|---|
| 200 | Berhasil |
| 401 | Token tidak ada/tidak valid |
| 404 | Data tidak ditemukan |
| 422 | Validasi gagal |
| 500 | Kesalahan server |
