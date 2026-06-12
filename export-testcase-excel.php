<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/testcase-data.php';

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

function style_test_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $headerRow, int $lastRow, string $lastCol): void
{
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    foreach (range('A', $lastCol) as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
}

$usecaseSheet = $spreadsheet->getActiveSheet();
$usecaseSheet->setTitle('Usecase');
$usecaseSheet->mergeCells('A1:H1')->setCellValue('A1', 'ShoeStore - Bảng usecase');
$usecaseSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$usecaseSheet->fromArray(['Mã','Nhóm','Tên usecase','Tác nhân','Tiền điều kiện','Hậu điều kiện','Luồng chính','Ngoại lệ'], null, 'A3');
$r = 4;
foreach (shoestore_usecases() as $uc) {
    $usecaseSheet->fromArray([$uc['code'],$uc['group'],$uc['name'],$uc['actor'],$uc['precondition'],$uc['postcondition'],implode("\n",$uc['main_flow']),implode("\n",$uc['exceptions'])], null, 'A'.$r++);
}
style_test_sheet($usecaseSheet, 3, $r - 1, 'H');

$testSheet = $spreadsheet->createSheet();
$testSheet->setTitle('Testcase');
$testSheet->mergeCells('A1:I1')->setCellValue('A1', 'ShoeStore - Bảng testcase');
$testSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$testSheet->fromArray(['Mã testcase','Chức năng','Mục tiêu','Điều kiện đầu vào','Các bước','Expected','Actual','Status','Ghi chú'], null, 'A3');
$r = 4;
foreach (shoestore_testcases() as $tc) {
    $testSheet->fromArray([$tc['code'],$tc['feature'],$tc['objective'],$tc['input'],$tc['steps'],$tc['expected'],$tc['actual'],$tc['status'],$tc['note']], null, 'A'.$r);
    $color = $tc['status'] === 'Pass' ? 'FFDCFCE7' : 'FFFEE2E2';
    $testSheet->getStyle('H'.$r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($color);
    $r++;
}
style_test_sheet($testSheet, 3, $r - 1, 'I');

$resultSheet = $spreadsheet->createSheet();
$resultSheet->setTitle('Kết quả kiểm thử');
$total = count(shoestore_testcases());
$pass = count(array_filter(shoestore_testcases(), fn($tc) => $tc['status'] === 'Pass'));
$resultSheet->fromArray([['Chỉ số','Giá trị'],['Tổng testcase',$total],['Pass',$pass],['Fail',$total-$pass],['Tỉ lệ Pass',round($pass / max(1,$total) * 100, 2) . '%']], null, 'A1');
style_test_sheet($resultSheet, 1, 5, 'B');

$evalSheet = $spreadsheet->createSheet();
$evalSheet->setTitle('Đánh giá');
$evalSheet->fromArray(['Hạng mục','Nội dung'], null, 'A1');
$r = 2;
foreach (shoestore_evaluation() as $title => $items) {
    $evalSheet->fromArray([$title, implode("\n", $items)], null, 'A'.$r++);
}
style_test_sheet($evalSheet, 1, $r - 1, 'B');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="shoestore-usecase-testcase.xlsx"');
header('Cache-Control: max-age=0');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
exit;
