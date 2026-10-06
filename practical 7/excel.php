<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create new spreadsheet
$spreadsheet = new Spreadsheet();

// Select active worksheet
$sheet = $spreadsheet->getActiveSheet();

// Set sheet name
$sheet->setTitle("Students");

// Add headings
$sheet->setCellValue('A1', 'ID');
$sheet->setCellValue('B1', 'Name');
$sheet->setCellValue('C1', 'Email');
$sheet->setCellValue('D1', 'Course');
$sheet->setCellValue('E1', 'Marks');

// Add student data
$students = [
    [1, 'Student 1', 'student1@example.com', 'Cyber Security', 92],
    [2, 'Student 2', 'student2@example.com', 'Computer Science', 88],
    [3, 'Student 3', 'student3@example.com', 'Cyber Security', 95],
    [4, 'Student 4', 'student4@example.com', 'Information Technology', 84]
];

// Starting row
$row = 2;

foreach ($students as $student) {

    $sheet->setCellValue('A' . $row, $student[0]);
    $sheet->setCellValue('B' . $row, $student[1]);
    $sheet->setCellValue('C' . $row, $student[2]);
    $sheet->setCellValue('D' . $row, $student[3]);
    $sheet->setCellValue('E' . $row, $student[4]);

    $row++;
}

// Make columns automatically fit
foreach (range('A', 'E') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Make header bold
$sheet->getStyle('A1:E1')->getFont()->setBold(true);

// Create Excel file
$writer = new Xlsx($spreadsheet);

// Download the file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="students.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');

exit;
?>