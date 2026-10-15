<?php
// Pre-check ringan (AJAX): apakah tanggal ini masih segar hasil pull terakhir?
// Dipakai tombol Tarik di kelola.php sebelum submit: kalau segar, tawarkan
// "Pakai Data Lokal" (skip SOAP) vs "Tarik Ulang".
error_reporting(0);
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['iduser'])) { echo json_encode(['ok' => false]); exit; }
include "../../koneksi.php";
include_once __DIR__ . '/../../device_sync_helper.php';
$tgl = $_GET['tgl'] ?? date('Y-m-d');
$tgl2 = $_GET['tgl2'] ?? $tgl;
$win = isset($_GET['win']) ? max(1, min(120, (int)$_GET['win'])) : 10;
$fr = function_exists('pull_cache_fresh') ? pull_cache_fresh($koneksi, $tgl, $tgl2, $win) : ['fresh' => false, 'age_min' => null];
echo json_encode(['ok' => true, 'fresh' => !empty($fr['fresh']), 'age_min' => $fr['age_min']]);
