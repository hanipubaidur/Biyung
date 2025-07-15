<?php
// Setel zona waktu ke Asia/Jakarta (GMT+7)
date_default_timezone_set('Asia/Jakarta');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Fill, Border, Alignment, Font};

try {
    $db = new Database();
    $conn = $db->getConnection();
    $spreadsheet = new Spreadsheet();
    
    // Palet Warna
    $theme = [
        'primary' => '0F1419', 'secondary' => '1A365D', 'accent' => '2B6CB0',
        'success' => '047857', 'danger' => 'B91C1C', 'warning' => 'D97706',
        'info' => '0369A1', 'light' => 'F8FAFC',
    ];

    // Query Utama (digunakan di beberapa sheet)
    $summaryQuery = "SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) as total_expense,
        COUNT(CASE WHEN type = 'income' THEN 1 END) as income_count,
        COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count,
        COUNT(DISTINCT DATE_FORMAT(date, '%Y-%m')) as active_months
        FROM transactions WHERE status != 'deleted'";
    $summary = $conn->query($summaryQuery)->fetch(PDO::FETCH_ASSOC);
    
    // SHEET 1: EXECUTIVE DASHBOARD
    $executive = $spreadsheet->getActiveSheet();
    $executive->setTitle('📊 Dashboard Eksekutif');
    
    // Informasi brand telah disesuaikan
    $executive->setCellValue('A1', 'Kedai');
    $executive->setCellValue('A2', 'Analisis Keuangan | Kedai');
    $executive->setCellValue('A3', 'DASHBOARD EKSEKUTIF & ANALITIK');
    $executive->setCellValue('A4', '📅 ' . date('l, d F Y • G:i T'));
    
    $headerRanges = ['A1:H1', 'A2:H2', 'A3:H3', 'A4:H4'];
    $headerStyles = [
        ['size' => 24, 'color' => 'FFFFFF', 'bgColor' => $theme['primary']],
        ['size' => 11, 'color' => 'FFFFFF', 'bgColor' => $theme['secondary']],
        ['size' => 16, 'color' => 'FFFFFF', 'bgColor' => $theme['accent']],
        ['size' => 10, 'color' => 'FFFFFF', 'bgColor' => $theme['info']]
    ];

    foreach ($headerRanges as $i => $range) {
        $executive->mergeCells($range);
        $style = $headerStyles[$i];
        $executive->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => $style['size'], 'color' => ['rgb' => $style['color']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $style['bgColor']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }
    $executive->getRowDimension('1')->setRowHeight(35);
    $executive->getRowDimension('3')->setRowHeight(25);

    $netWorth = $summary['total_income'] - $summary['total_expense'];
    $burnRate = $summary['active_months'] > 0 ? ($summary['total_expense'] / $summary['active_months']) : 0;
    $runwayMonths = $burnRate > 0 ? ($netWorth / $burnRate) : 0;
    $totalTransactions = $summary['income_count'] + $summary['expense_count'];

    $executive->setCellValue('A6', 'METRIK KINERJA KEUANGAN');
    $executive->mergeCells('A6:H6');
    $executive->getStyle('A6')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $theme['primary']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $theme['accent']]]]
    ]);

    $metrics = [
        ['💰 Total Pemasukan', 'Rp', $summary['total_income'], $theme['success']],
        ['💸 Total Pengeluaran', 'Rp', $summary['total_expense'], $theme['danger']],
        ['💎 Kekayaan Bersih', 'Rp', $netWorth, $netWorth >= 0 ? $theme['success'] : $theme['danger']],
        ['🔥 Burn Rate', '', $burnRate, $burnRate > 0 ? $theme['warning'] : $theme['info']],
        ['🛡️ Financial Runway', 'bulan', $runwayMonths, $runwayMonths >= 6 ? $theme['success'] : ($runwayMonths > 0 ? $theme['warning'] : $theme['danger'])],
        ['📈 Jumlah Transaksi', 'transaksi', $totalTransactions, $theme['accent']]
    ];

    $row = 8;
    // == BLOK KODE YANG DIPERBAIKI UNTUK FORMATTING ==
    foreach($metrics as $metric) {
        $executive->setCellValue('A'.$row, $metric[0]);
        
        $label = $metric[0];
        $unit = $metric[1];
        $value = $metric[2];
        $formattedValue = '';

        // Kasus khusus untuk Burn Rate
        if ($label === '🔥 Burn Rate') {
            $formattedValue = 'Rp ' . number_format($value, 0, ',', '.') . ' /month';
        } 
        // Kasus khusus untuk Financial Runway (memperbaiki urutan dan format)
        elseif ($label === '🛡️ Financial Runway') {
            $formattedValue = number_format($value, 1, ',', '.') . ' ' . $unit;
        } 
        // Kasus khusus untuk Transaction Volume (tanpa desimal)
        elseif ($label === '📈 Transaction Volume') {
            $formattedValue = number_format($value, 0, ',', '.') . ' ' . $unit;
        } 
        // Format default untuk sisanya (Revenue, Expenses, Net Worth)
        else {
            $formattedValue = $unit . ' ' . number_format($value, 0, ',', '.');
        }
        
        $executive->setCellValue('E'.$row, $formattedValue);
        
        // Sisa styling tetap sama
        $executive->mergeCells("A$row:D$row");
        $executive->mergeCells("E$row:H$row");
        $executive->getStyle("A$row:H$row")->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D1D5DB']]]]);
        $executive->getStyle("A$row:D$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $executive->getStyle("E$row:H$row")->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT], 'font' => ['color' => ['rgb' => $metric[3]]]]);
        $executive->getRowDimension($row)->setRowHeight(22);
        $row++;
    }
    // == AKHIR DARI BLOK KODE YANG DIPERBAIKI ==

    $executive->setCellValue('A' . ($row + 1), 'SKOR KESEHATAN KEUANGAN');
    $executive->mergeCells('A' . ($row + 1) . ':H' . ($row + 1));
    $executive->getStyle('A' . ($row + 1))->applyFromArray(['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    
    $healthScore = min(100, max(0, ($netWorth > 0 ? 35 : 0) + ($runwayMonths >= 6 ? 35 : ($runwayMonths > 0 ? $runwayMonths * 5.83 : 0)) + ($summary['total_income'] > $summary['total_expense'] ? 30 : 0)));
    $scoreColor = $healthScore >= 75 ? $theme['success'] : ($healthScore >= 50 ? $theme['warning'] : $theme['danger']);
    $scoreEmoji = $healthScore >= 75 ? '🌟' : ($healthScore >= 50 ? '⚡' : '🚨');
    
    $executive->setCellValue('A' . ($row + 3), $scoreEmoji . ' ' . number_format($healthScore, 1) . '%');
    $executive->mergeCells('A' . ($row + 3) . ':H' . ($row + 3));
    $executive->getStyle('A' . ($row + 3))->applyFromArray(['font' => ['bold' => true, 'size' => 28, 'color' => ['rgb' => $scoreColor]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $executive->getRowDimension($row + 3)->setRowHeight(36);

    foreach(range('A', 'H') as $col) { $executive->getColumnDimension($col)->setAutoSize(true); }

    // SHEET 2: INCOME INTELLIGENCE
    $income = $spreadsheet->createSheet();
    $income->setTitle('💚 Analisis Pemasukan');
    
    $income->setCellValue('A1', '💚 ANALISIS & OPTIMASI PEMASUKAN');
    $income->mergeCells('A1:G1');
    $income->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['success']]],'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],]);
    $income->getRowDimension('1')->setRowHeight(30);

    $incomeQuery = "SELECT i.source_name, COUNT(t.id) as frequency, SUM(t.amount) as total, AVG(t.amount) as average, MAX(t.amount) as peak, STDDEV(t.amount) as volatility, COUNT(DISTINCT DATE_FORMAT(t.date, '%Y-%m')) as active_months FROM income_sources i LEFT JOIN transactions t ON i.id = t.income_source_id WHERE t.type = 'income' AND t.status != 'deleted' GROUP BY i.id, i.source_name ORDER BY total DESC";
    $incomeData = $conn->query($incomeQuery)->fetchAll(PDO::FETCH_ASSOC);

    $headers = ['💰 Sumber Pemasukan', '📊 Frekuensi', '💎 Total', '📈 Rata-rata', '🚀 Tertinggi', '💫 Konsistensi', '⚡ Growth Rate'];
    foreach($headers as $i => $header) { $income->setCellValue(chr(65 + $i) . '3', $header); }

    $income->getStyle('A3:G3')->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],'fill' => ['fillType' => Fill::FILL_GRADIENT_LINEAR, 'startColor' => ['rgb' => $theme['success']], 'endColor' => ['rgb' => '065F46']],'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],]);
    
    $row = 4;
    foreach($incomeData as $data) {
        $consistency = $data['average'] > 0 ? (100 - min(100, ($data['volatility'] / $data['average']) * 100)) : 0;
        $growthRate = $data['active_months'] > 1 ? (($data['total'] / $data['active_months']) / $data['average'] - 1) * 100 : 0;
        $values = ['🏢 ' . $data['source_name'], $data['frequency'] . 'x', 'Rp ' . number_format($data['total'], 0, ',', '.'), 'Rp ' . number_format($data['average'], 0, ',', '.'), 'Rp ' . number_format($data['peak'], 0, ',', '.'), number_format($consistency, 1) . '%', ($growthRate >= 0 ? '+' : '') . number_format($growthRate, 1) . '%'];
        foreach($values as $i => $value) { $income->setCellValue(chr(65 + $i) . $row, $value); }
        $income->getStyle("A$row:G$row")->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $row % 2 == 0 ? $theme['light'] : 'FFFFFF']], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
        $income->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        if($growthRate >= 10) { $income->getStyle("G$row")->getFont()->getColor()->setRGB($theme['success']); } elseif($growthRate < -5) { $income->getStyle("G$row")->getFont()->getColor()->setRGB($theme['danger']); }
        $row++;
    }
    foreach(range('A', 'G') as $col) { $income->getColumnDimension($col)->setAutoSize(true); }

    // SHEET 3: EXPENSE ANALYTICS
    $expense = $spreadsheet->createSheet();
    $expense->setTitle('🔥 Analisis Pengeluaran');
    
    $expense->setCellValue('A1', '🔥 ANALISIS & OPTIMASI PENGELUARAN');
    $expense->mergeCells('A1:H1');
    $expense->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['danger']]],'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],]);
    $expense->getRowDimension('1')->setRowHeight(30);
    
    $expenseQuery = "SELECT ec.category_name, COUNT(t.id) as transactions, SUM(t.amount) as total, AVG(t.amount) as average, MAX(t.amount) as highest, STDDEV(t.amount) as volatility FROM expense_categories ec LEFT JOIN transactions t ON ec.id = t.expense_category_id WHERE t.type = 'expense' AND t.status != 'deleted' GROUP BY ec.id, ec.category_name ORDER BY total DESC";
    $expenseData = $conn->query($expenseQuery)->fetchAll(PDO::FETCH_ASSOC);

    $expenseHeaders = ['💸 Kategori', '📊 Volume', '💰 Total', '📈 Rata-rata', '🔥 Tertinggi', '📊 Persen', '⚡ Volatilitas', '🎯 Prioritas'];
    foreach($expenseHeaders as $i => $header) { $expense->setCellValue(chr(65 + $i) . '3', $header); }
    
    $expense->getStyle('A3:H3')->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],'fill' => ['fillType' => Fill::FILL_GRADIENT_LINEAR, 'startColor' => ['rgb' => $theme['danger']], 'endColor' => ['rgb' => '991B1B']],'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],]);

    $row = 4;
    foreach($expenseData as $data) {
        $percentage = $summary['total_expense'] > 0 ? ($data['total'] / $summary['total_expense'] * 100) : 0;
        $volatilityScore = $data['average'] > 0 ? min(100, ($data['volatility'] / $data['average']) * 100) : 0;
        $priority = 'LOW'; $priorityColor = $theme['info'];
        if($percentage > 20) { $priority = 'CRITICAL'; $priorityColor = $theme['danger']; } elseif($percentage > 10) { $priority = 'HIGH'; $priorityColor = $theme['warning']; }
        
        $values = ['💳 ' . $data['category_name'], $data['transactions'] . 'x', 'Rp ' . number_format($data['total'], 0, ',', '.'), 'Rp ' . number_format($data['average'], 0, ',', '.'), 'Rp ' . number_format($data['highest'], 0, ',', '.'), number_format($percentage, 1) . '%', number_format($volatilityScore, 1) . '%', $priority];
        foreach($values as $i => $value) { $expense->setCellValue(chr(65 + $i) . $row, $value); }

        $expense->getStyle("A$row:H$row")->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $row % 2 == 0 ? $theme['light'] : 'FFFFFF']], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
        $expense->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $expense->getStyle("H$row")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $priorityColor]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        $row++;
    }
    foreach(range('A', 'H') as $col) { $expense->getColumnDimension($col)->setAutoSize(true); }

    // SHEET 4: TREND ANALYSIS
    $trends = $spreadsheet->createSheet();
    $trends->setTitle('📈 Analisis Tren');
    
    // Judul dan tabel dilebarkan hingga kolom H (bukan I)
    $trends->setCellValue('A1', '📈 ANALISIS TREN & PERKIRAAN');
    $trends->mergeCells('A1:H1');
    $trends->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['accent']]],'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],]);
    $trends->getRowDimension('1')->setRowHeight(30);
    
    $trendsQuery = "SELECT DATE_FORMAT(date, '%Y-%m') as period, DATE_FORMAT(date, '%b %Y') as display_period, SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income, SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense FROM transactions WHERE status != 'deleted' AND date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(date, '%Y-%m'), DATE_FORMAT(date, '%b %Y') ORDER BY period ASC LIMIT 12";
    $trendsData = $conn->query($trendsQuery)->fetchAll(PDO::FETCH_ASSOC);

    // Kolom analisis diperbanyak hingga H
    $trendHeaders = ['📅 Periode', '💰 Pemasukan', '💸 Pengeluaran', '💎 Net', '⚡ Efisiensi', '📈 Growth', '🎯 Skor', '🔮 Perkiraan'];
    foreach($trendHeaders as $i => $header) { $trends->setCellValue(chr(65 + $i) . '3', $header); }
    $trends->getStyle('A3:H3')->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],'fill' => ['fillType' => Fill::FILL_GRADIENT_LINEAR, 'startColor' => ['rgb' => $theme['primary']], 'endColor' => ['rgb' => $theme['accent']]],'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],]);

    $row = 4;
    $prevIncome = 0;
    foreach($trendsData as $data) {
        $netFlow = $data['income'] - $data['expense'];
        $efficiency = $data['income'] > 0 ? ($netFlow / $data['income'] * 100) : 0;
        $growth = $prevIncome > 0 ? (($data['income'] - $prevIncome) / $prevIncome * 100) : 0;
        $score = min(100, max(0, $efficiency + ($growth * 0.5)));
        $forecast = $data['income'] * (1 + ($growth / 100));
        
        $values = [
            '📅 ' . $data['display_period'],
            'Rp ' . number_format($data['income']),
            'Rp ' . number_format($data['expense']),
            'Rp ' . number_format($netFlow),
            number_format($efficiency, 1) . '%',
            ($growth >= 0 ? '▲ ' : '▼ ') . number_format(abs($growth), 1) . '%',
            number_format($score, 0),
            'Rp ' . number_format($forecast)
        ];
        
        foreach($values as $j => $value) { $trends->setCellValue(chr(65 + $j) . $row, $value); }
        
        $trends->getStyle("A$row:H$row")->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $netFlow >= 0 ? 'E8F5E9' : 'FBE9E7']], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
        $trends->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $trends->getStyle("D$row")->getFont()->applyFromArray(['bold' => true, 'color' => ['rgb' => $netFlow >= 0 ? $theme['success'] : $theme['danger']]]);
        
        $prevIncome = $data['income'];
        $row++;
    }
    foreach(range('A', 'H') as $col) { $trends->getColumnDimension($col)->setAutoSize(true); }

    // SHEET 5: TRANSACTION MASTER
    $transactions = $spreadsheet->createSheet();
    $transactions->setTitle('📋 Data Transaksi');

    // Panjangkan header sampai kolom H
    $transactions->setCellValue('A1', 'DATABASE TRANSAKSI LENGKAP');
    $transactions->mergeCells('A1:H1');
    $transactions->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => $theme['secondary']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['light']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $transactions->getRowDimension('1')->setRowHeight(30);

    // Kolom: Date, Type, Category, Product Name, Product Price, Quantity, Amount, Description, Shift, Shift Name
    $transHeaders = ['Tanggal', 'Tipe', 'Kategori', 'Nama Produk', 'Harga Produk', 'Jumlah', 'Nominal', 'Deskripsi', 'Shift', 'Nama Shift'];
    foreach($transHeaders as $i => $header) { $transactions->setCellValue(chr(65 + $i).'3', $header); }
    $transactions->getStyle('A3:J3')->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['accent']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $transactions->getRowDimension('3')->setRowHeight(20);

    // Query transaksi lengkap dengan produk, quantity, shift
    $transQuery = "SELECT 
        t.date, 
        t.type, 
        t.amount, 
        t.quantity,
        t.description, 
        CASE WHEN t.type = 'income' THEN i.source_name ELSE e.category_name END as category,
        p.name as product_name,
        p.price as product_price,
        t.shift_id,
        s.name as shift_name
    FROM transactions t
    LEFT JOIN income_sources i ON t.income_source_id = i.id
    LEFT JOIN expense_categories e ON t.expense_category_id = e.id
    LEFT JOIN products p ON t.product_id = p.id
    LEFT JOIN shifts s ON t.shift_id = s.id
    WHERE t.status != 'deleted'
    ORDER BY t.date DESC, t.id DESC
    LIMIT 500";
    $transData = $conn->query($transQuery)->fetchAll(PDO::FETCH_ASSOC);

    $row = 4;
    foreach($transData as $t) {
        $values = [
            date('d/m/Y', strtotime($t['date'])),
            ($t['type'] === 'income' ? '+' : '-') . ' ' . ucfirst($t['type']),
            $t['category'] ?: '-',
            $t['product_name'] ?: '-',
            $t['product_price'] ? 'Rp ' . number_format($t['product_price'], 0, ',', '.') : '-',
            $t['quantity'] ?: 1,
            $t['amount'] ? 'Rp ' . number_format($t['amount'], 0, ',', '.') : '-',
            $t['description'] ?: '-',
            $t['shift_id'] ?: '-',
            $t['shift_name'] ?: '-'
        ];
        foreach($values as $i => $value) { $transactions->setCellValue(chr(65 + $i) . $row, $value); }
        $transactions->getStyle("A$row:J$row")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $t['type'] === 'income' ? 'E8F5E9' : 'FBE9E7']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $transactions->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $transactions->getStyle("B$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $transactions->getStyle("C$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $transactions->getStyle("E$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $transactions->getStyle("F$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $transactions->getStyle("G$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $transactions->getStyle("H$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $transactions->getStyle("I$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $transactions->getStyle("J$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $row++;
    }

    $summaryStartRow = $row + 1;
    $transactions->setCellValue("A$summaryStartRow", "RINGKASAN TRANSAKSI");
    $transactions->mergeCells("A$summaryStartRow:J$summaryStartRow");
    $transactions->getStyle("A$summaryStartRow:J$summaryStartRow")->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['primary']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    
    $totalIncome = array_sum(array_column(array_filter($transData, fn($t) => $t['type'] === 'income'), 'amount'));
    $totalExpense = array_sum(array_column(array_filter($transData, fn($t) => $t['type'] === 'expense'), 'amount'));
    $netFlow = $totalIncome - $totalExpense;
    $summaryData = [
        ['Total Pemasukan', '', '', '', '', '', 'Rp ' . number_format($totalIncome), $theme['success']],
        ['Total Pengeluaran', '', '', '', '', '', 'Rp ' . number_format($totalExpense), $theme['danger']],
        ['Net', '', '', '', '', '', 'Rp ' . number_format($netFlow), $netFlow >= 0 ? $theme['success'] : $theme['danger']]
    ];
    
    $currentRow = $summaryStartRow + 1;
    foreach ($summaryData as $data) {
        $transactions->setCellValue("A$currentRow", $data[0]);
        $transactions->setCellValue("G$currentRow", $data[6]);
        $transactions->mergeCells("A$currentRow:F$currentRow");
        $transactions->mergeCells("G$currentRow:H$currentRow");
        $transactions->getStyle("A$currentRow:H$currentRow")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['light']]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]]
        ]);
        $transactions->getStyle("G$currentRow")->getFont()->getColor()->setRGB($data[7]);
        $transactions->getStyle("G$currentRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $currentRow++;
    }

    foreach(range('A', 'H') as $col) { $transactions->getColumnDimension($col)->setAutoSize(true); }

    // Finalisasi
    $spreadsheet->setActiveSheetIndex(0);
    $filename = "Kedai_Laporan_Lengkap_" . date('Y-m-d_His') . ".xlsx";
    
    ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log('Export Error: ' . $e->getMessage());
    die('Terjadi kesalahan saat membuat laporan: ' . $e->getMessage());
}