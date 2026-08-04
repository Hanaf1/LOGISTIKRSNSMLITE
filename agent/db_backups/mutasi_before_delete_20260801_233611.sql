-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: mlite_rsns
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `rsns_custom_logistik_non_medis_mutasi`
--

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_mutasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rsns_custom_logistik_non_medis_mutasi` (
  `no_mutasi` varchar(50) NOT NULL,
  `tgl_mutasi` date NOT NULL,
  `kode_lokasi_asal` varchar(50) DEFAULT NULL,
  `kode_lokasi_tujuan` varchar(50) DEFAULT NULL,
  `keterangan` text,
  `status` enum('Draft','Dikirim','Diterima','Batal') NOT NULL DEFAULT 'Draft',
  `user_input` varchar(100) DEFAULT NULL,
  `user_terima` varchar(100) DEFAULT NULL,
  `tgl_terima` datetime DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  PRIMARY KEY (`no_mutasi`),
  KEY `kode_lokasi_asal` (`kode_lokasi_asal`),
  KEY `kode_lokasi_tujuan` (`kode_lokasi_tujuan`),
  KEY `idx_mutasi_tanggal_status` (`tgl_mutasi`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rsns_custom_logistik_non_medis_mutasi`
--

LOCK TABLES `rsns_custom_logistik_non_medis_mutasi` WRITE;
/*!40000 ALTER TABLE `rsns_custom_logistik_non_medis_mutasi` DISABLE KEYS */;
/*!40000 ALTER TABLE `rsns_custom_logistik_non_medis_mutasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rsns_custom_logistik_non_medis_mutasi_detail`
--

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_mutasi_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rsns_custom_logistik_non_medis_mutasi_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_mutasi` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `jenis_mutasi` enum('Masuk','Keluar','Penyesuaian') NOT NULL DEFAULT 'Penyesuaian',
  `batch_no` varchar(100) DEFAULT '-',
  `qty` double NOT NULL DEFAULT '0',
  `satuan` varchar(50) DEFAULT NULL,
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `no_mutasi` (`no_mutasi`),
  KEY `kode_item` (`kode_item`),
  KEY `idx_mutasi_detail_nomor_item` (`no_mutasi`,`kode_item`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rsns_custom_logistik_non_medis_mutasi_detail`
--

LOCK TABLES `rsns_custom_logistik_non_medis_mutasi_detail` WRITE;
/*!40000 ALTER TABLE `rsns_custom_logistik_non_medis_mutasi_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `rsns_custom_logistik_non_medis_mutasi_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rsns_custom_logistik_non_medis_kartu_stok`
--

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_kartu_stok`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rsns_custom_logistik_non_medis_kartu_stok` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tgl_transaksi` datetime NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `batch_no` varchar(100) DEFAULT '-',
  `tipe_transaksi` enum('Masuk','Keluar','Retur','Opname','Mutasi Masuk','Mutasi Keluar') NOT NULL,
  `no_referensi` varchar(50) NOT NULL,
  `qty_masuk` double NOT NULL DEFAULT '0',
  `qty_keluar` double NOT NULL DEFAULT '0',
  `stok_akhir` double NOT NULL DEFAULT '0',
  `harga` double NOT NULL DEFAULT '0',
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_kartu_stok_item_lokasi_tanggal` (`kode_item`,`kode_lokasi`,`tgl_transaksi`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rsns_custom_logistik_non_medis_kartu_stok`
--

LOCK TABLES `rsns_custom_logistik_non_medis_kartu_stok` WRITE;
/*!40000 ALTER TABLE `rsns_custom_logistik_non_medis_kartu_stok` DISABLE KEYS */;
INSERT INTO `rsns_custom_logistik_non_medis_kartu_stok` VALUES (1,'2026-07-22 02:21:21','BRG0720260002','-','-','Masuk','LP/202607/0001',1,0,1,250,'admin'),(2,'2026-07-22 02:21:22','BRG0720260006','-','-','Masuk','LP/202607/0001',1,0,1,350,'admin'),(3,'2026-07-22 02:21:42','BRG0720260002','-','-','Masuk','LP/202607/0001',1,0,1,250,'admin'),(4,'2026-07-22 02:21:43','BRG0720260006','-','-','Masuk','LP/202607/0001',1,0,1,350,'admin'),(5,'2026-07-22 02:24:08','BRG0720260002','-','-','Masuk','LP/202607/0001',1,0,1,250,'admin'),(6,'2026-07-22 02:24:08','BRG0720260006','-','-','Masuk','LP/202607/0001',1,0,1,350,'admin'),(7,'2026-07-22 02:30:22','BRG0720260002','-','-','Masuk','LP/202607/0001',1,0,1,250,'admin'),(8,'2026-07-22 02:30:22','BRG0720260006','-','-','Masuk','LP/202607/0001',1,0,1,350,'admin'),(9,'2026-07-22 02:30:38','BRG0720260002','-','-','Masuk','LP/202607/0001',1,0,1,250,'admin'),(10,'2026-07-22 02:30:38','BRG0720260006','-','-','Masuk','LP/202607/0001',1,0,1,350,'admin'),(11,'2026-07-22 02:38:20','BRG0720260002','-','-','Masuk','LP/202607/0001',1,0,1,250,'admin'),(12,'2026-07-22 02:38:20','BRG0720260006','-','-','Masuk','LP/202607/0001',1,0,1,350,'admin'),(13,'2026-07-22 02:40:08','BRG0720260002','-','-','Masuk','LP/202607/0001',1,0,1,250,'admin'),(14,'2026-07-22 02:40:08','BRG0720260006','-','-','Masuk','LP/202607/0001',1,0,1,350,'admin'),(15,'2026-07-22 02:40:21','BRG0720260002','-','-','Masuk','LP/202607/0001',1,0,2,250,'admin'),(16,'2026-07-22 02:40:21','BRG0720260006','-','-','Masuk','LP/202607/0001',1,0,2,350,'admin'),(89,'2026-07-29 21:31:25','BRG0720260005','FISIK-MANUAL','FISIK-MANUAL','Keluar','SPPB/202607/UNT-2026070007/0001',0,1,-1,0,'logistik_t'),(90,'2026-08-02 00:20:36','BRG0720260314','FISIK-MANUAL','FISIK-MANUAL','Opname','SPPB/202608/UNT-2026070007/0001',1,0,1,0,'admin'),(91,'2026-08-02 00:20:36','BRG0720260314','FISIK-MANUAL','FISIK-MANUAL','Keluar','SPPB/202608/UNT-2026070007/0001',0,1,0,0,'admin');
/*!40000 ALTER TABLE `rsns_custom_logistik_non_medis_kartu_stok` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-01 23:36:12
