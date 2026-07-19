import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

c = c.replace("fgetcsv($handle, 1000, \":\")", "fgetcsv($handle, 1000, \";\")")
c = c.replace("fputcsv($output, ['No Transaksi', 'Tanggal', 'Kode Barang', 'Nama Barang', 'Jenis Mutasi', 'Qty', 'Keterangan'], ':');", "fputcsv($output, ['No Transaksi', 'Tanggal', 'Kode Barang', 'Nama Barang', 'Jenis Mutasi', 'Qty', 'Keterangan'], ';');")
c = c.replace("fputcsv($output, $row, ':');", "fputcsv($output, $row, ';');")
c = c.replace("fputcsv($output, ['No Transaksi', 'Tanggal (YYYY-MM-DD)', 'Catatan Umum', 'Kode Barang', 'Jenis Mutasi (Masuk/Keluar/Penyesuaian)', 'Jumlah', 'Catatan Item'], ':');", "fputcsv($output, ['No Transaksi', 'Tanggal (YYYY-MM-DD)', 'Catatan Umum', 'Kode Barang', 'Jenis Mutasi (Masuk/Keluar/Penyesuaian)', 'Jumlah', 'Catatan Item'], ';');")
c = c.replace("fputcsv($output, ['MUT/2026/001', '2026-07-19', 'Stok awal bulan', 'BRG0001', 'Masuk', '100', ''], ':');", "fputcsv($output, ['MUT/2026/001', '2026-07-19', 'Stok awal bulan', 'BRG0001', 'Masuk', '100', ''], ';');")
c = c.replace("fputcsv($output, ['MUT/2026/001', '2026-07-19', 'Stok awal bulan', 'BRG0002', 'Masuk', '50', 'Dus rusak'], ':');", "fputcsv($output, ['MUT/2026/001', '2026-07-19', 'Stok awal bulan', 'BRG0002', 'Masuk', '50', 'Dus rusak'], ';');")

with open(path, 'w', encoding='utf-8') as f: f.write(c)
