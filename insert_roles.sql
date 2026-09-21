-- Jalankan hanya bila rincian permission perlu dibangun ulang setelah import.
-- Definisi seluruh role berasal dari schemanew.sql dan tidak dihapus di sini.
DELETE FROM `rsns_custom_logistik_non_medis_role_permission_item`;

-- Menyentuh setiap role akan menjalankan trigger sinkronisasi dan mengisi ulang
-- permission_item untuk Admin, Logistik, Gudang, Aset, Unit, Keuangan,
-- Kepala Unit, Kepala Sie, Kepala Bidang, dan Bendahara.
UPDATE `rsns_custom_logistik_non_medis_role_permissions`
SET `permissions` = `permissions`;

SELECT `role`, COUNT(*) AS `jumlah_permission`
FROM `rsns_custom_logistik_non_medis_role_permission_item`
GROUP BY `role`
ORDER BY `role`;
