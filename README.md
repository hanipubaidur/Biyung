# 🥤 Biyung - Manajemen Keuangan Jualan Es Ubi Ungu

## 📝 Pembaruan Terbaru
- ✅ Validasi quantity pada transaksi produk: user tidak bisa input lebih dari stok, muncul pesan error jika melebihi stok.
- ✅ Amount pada transaksi produk otomatis mengikuti harga x quantity.
- ✅ Financial Overview menampilkan Total Product Sold (total produk terjual).
- ✅ Period selector global di laporan/report, chart dan tabel mengikuti periode yang dipilih.
- ✅ Produk terlaris di chart penjualan produk diurutkan otomatis.

<div align="center">
  
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue?style=for-the-badge&logo=php)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-blue?style=for-the-badge&logo=mysql)](https://www.mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.1-blueviolet?style=for-the-badge&logo=bootstrap)](https://getbootstrap.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6-yellow?style=for-the-badge&logo=javascript)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

</div>

## 👨‍💻 Dibuat Oleh

<div align="center">
  <a href="https://github.com/hanipubaidur">
    <img src="https://avatars.githubusercontent.com/hanipubaidur" width="100px" style="border-radius:50%"/>
  </a>
  <h3>Hanif Ubaidur Rohman Syah</h3>
  <p>Full Stack Developer | UI/UX Design</p>
  
  [![GitHub](https://img.shields.io/badge/GitHub-hanipubaidur-181717?style=flat&logo=github)](https://github.com/hanipubaidur)
</div>

## 🌟 Tentang Biyung

Biyung adalah brand es cendol yang terbuat dari ubi ungu, dan saat ini berjualan di Jogja.  
Aplikasi ini dibuat khusus untuk pencatatan keuangan usaha Biyung Ubi Ungu.

- **Brand:** Biyung Ubi Ungu (Es Cendol Ubi Ungu)
- **Media Sosial:**  
  - TikTok: [@biyungubiungu.jogja](https://www.tiktok.com/@biyungubiungu.jogja)  
  - Instagram: [@biyungubiungu.jogja](https://www.instagram.com/biyungubiungu.jogja)

Fitur utama: pencatatan pemasukan (cash, transfer/e-wallet, QRIS), pengeluaran modal, gaji karyawan (dengan dropdown karyawan), pengeluaran lain-lain, dan penjualan produk dengan quantity.  
Manajemen karyawan terintegrasi.

---

## 🚀 Fitur Utama

- **Dashboard Ringkasan:**  
  Lihat saldo, pemasukan, pengeluaran, rasio pengeluaran, dan total produk terjual secara real-time dengan animasi dan breakdown per periode (harian, mingguan, bulanan, tahunan).

- **Pencatatan Transaksi:**  
  Catat pemasukan (cash, transfer, QRIS, produk) dan pengeluaran (modal, gaji, dll) dengan kategori yang mudah dipilih.  
  Untuk transaksi produk, input quantity dan amount otomatis dihitung (harga x quantity), serta validasi tidak bisa melebihi stok.

- **Manajemen Karyawan:**  
  Tambah/edit/nonaktifkan karyawan, serta pencatatan gaji otomatis terhubung ke data karyawan.

- **Manajemen Produk & Stok:**  
  Tambah produk, atur stok, dan harga. Stok otomatis berkurang saat ada penjualan.

- **Kategori Dinamis:**  
  Tambah/hapus sumber pemasukan dan kategori pengeluaran sesuai kebutuhan usaha.

- **Laporan & Analisis:**  
  Laporan keuangan lengkap, grafik cashflow, breakdown pengeluaran, analisis performa bulanan, ekspor data ke Excel.
  Period selector global di laporan, chart dan tabel mengikuti periode yang dipilih.
  Chart produk terlaris otomatis diurutkan.

- **Responsive & User Friendly:**  
  Tampilan modern, mudah digunakan di HP maupun laptop.

- **Keamanan Data:**  
  Data transaksi dan karyawan tersimpan di database MySQL lokal.

---

### ✨ Fitur Lainnya

- Export data ke Excel
- Riwayat transaksi terbaru
- Tabel ringkasan produk terjual

## 🛠️ Teknologi yang Digunakan
- PHP 7.4+
- MySQL 5.7+ 
- HTML5, CSS3, JavaScript ES6
- Bootstrap 5
- Chart.js
- PHPSpreadsheet 
- BoxIcons

## ⚙️ Cara Install & Jalankan

### 1. **Requirement**
- **XAMPP** (Apache & MySQL, minimal PHP 7.4)
- **Git** (untuk clone repo)
- **VSCode** (opsional, untuk edit)

### 2. **Clone Repo**
Buka **CMD** atau **Git Bash**:
```bash
git clone https://github.com/hanipubaidur/Biyung.git
```

### 3. **Pindahkan Folder ke XAMPP**
- Buka **File Explorer** ke `C:\Users\<namamu>\Biyung`
- Tekan `Ctrl+X` pada folder `Biyung`
- Buka `C:\xampp\htdocs\`
- Tekan `Ctrl+V` untuk paste di `htdocs`

### 4. **Jalankan XAMPP**
- Buka aplikasi **XAMPP**
- Start **Apache** dan **MySQL**

### 5. **Buka Project di VSCode**
- Buka **VSCode**
- Tekan `Ctrl+K O` (Open Folder)
- Pilih folder `C:\xampp\htdocs\Biyung`

### 6. **Import Database**
- Buka file `database/biyung.sql` di VSCode, `Ctrl+A` lalu `Ctrl+C`
- Di XAMPP, klik tombol **Admin** pada MySQL (phpMyAdmin)
- Buat database baru, misal: `biyung`
- Masuk ke menu **SQL**, paste seluruh isi `biyung.sql`, lalu klik **Go**
- **ATAU:**  
  Ke tab **Import**, lalu pilih file `biyung.sql` dari folder project, kemudian klik **Go**

### 7. **Konfigurasi Database (Opsional)**
- Jika perlu, edit file `config/database.php` agar sesuai user/password MySQL kamu

### 8. **Jalankan di Browser**
- Buka browser, akses:  
  ```
  http://localhost/Biyung
  ```

---

## 🧩 Penjelasan Fungsi-Fungsi Penting (Deskripsi)

> **Catatan:**  
> Penjelasan/deskripsi fungsi-fungsi utama di setiap file (seperti `main.js`, `report.js`, `transactions.js`, dsb) tetap tersedia di dalam file masing-masing dalam bentuk komentar.  
> Jangan hapus komentar deskripsi fungsi pada kode, agar developer lain mudah memahami alur dan kegunaan setiap fungsi.

---

<div align="center">
  Dibuat dengan ❤️ oleh <a href="https://github.com/hanipubaidur">Hanif Ubaidur Rohman Syah</a>
  <br>
  © 2025 Biyung Ubi Ungu Jogja
</div>