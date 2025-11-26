<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QRCodeService - FIXED VERSION
 * 
 * Generates QR codes that encode: QR_{TABLE_NUMBER}_{TIMESTAMP}
 * Example: QR_A1_1761053954
 */
class QRCodeService
{
    private const QR_SIZE = 300;
    private const QR_MARGIN = 1;
    private const ERROR_CORRECTION = 'H';

    /**
     * Generate QR code for table
     * 
     * QR Code format: QR_{TABLE_NUMBER}_{TIMESTAMP}
     * Example: QR_A1_1761053954
     */
    public function generateTableQR(int $tableId, ?string $oldQrPath = null, string $format = 'svg'): array
    {
        // Delete old QR if exists
        if ($oldQrPath) {
            $this->deleteQRCode($oldQrPath);
        }

        // Get table info
        $table = \DB::table('tables')->where('id', $tableId)->first();
        if (!$table) {
            throw new \Exception("Table not found");
        }

        // Generate QR code value: QR_{TABLE_NUMBER}_{TIMESTAMP}
        $timestamp = time();
        $qrCodeValue = "QR_{$table->table_number}_{$timestamp}";

        // Generate QR image and save
        $qrData = $this->createQR($tableId, $qrCodeValue, $format);

        // Generate frontend URL (what user will access)
        $frontendUrl = config('app.frontend_url', config('app.url')) . '?qr=' . $qrCodeValue;

        return [
            'qr_path' => $qrData['path'],           // File path in storage
            'qr_svg' => $qrData['svg'] ?? null,     // SVG content (if format=svg)
            'qr_url' => $frontendUrl,               // Frontend URL with QR parameter
            'qr_value' => $qrCodeValue              // The actual QR code value
        ];
    }

    /**
     * Create QR code SVG/PNG and save to storage
     */
    private function createQR(int $tableId, string $qrCodeValue, string $format = 'svg'): array
    {
        // Generate QR code image
        $qrCode = QrCode::format($format)
            ->size(self::QR_SIZE)
            ->margin(self::QR_MARGIN)
            ->errorCorrection(self::ERROR_CORRECTION)
            ->generate($qrCodeValue);

        // Filename
        $ext = $format === 'svg' ? 'svg' : 'png';
        $filename = "table_{$tableId}_" . time() . ".{$ext}";

        // Directory
        $directory = 'uploads/qr-codes';
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $path = "{$directory}/{$filename}";

        // Save to storage
        Storage::disk('public')->put($path, $qrCode);

        // Return path for database
        $storagePath = '/storage/' . $path;

        if ($format === 'svg') {
            return [
                'path' => $storagePath,
                'svg' => $qrCode
            ];
        } else {
            return [
                'path' => $storagePath
            ];
        }
    }

    /**
     * Bulk generate QR codes for multiple tables
     */
    public function bulkGenerateQR(array $tableIds, string $format = 'svg'): array
    {
        $result = [
            'success' => [],
            'failed' => []
        ];

        foreach ($tableIds as $tableId) {
            try {
                $qrData = $this->generateTableQR($tableId, null, $format);
                $result['success'][$tableId] = $qrData;
            } catch (\Exception $e) {
                $result['failed'][$tableId] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Delete QR code from storage
     */
    public function deleteQRCode(string $qrPath): bool
    {
        $storagePath = str_replace('/storage/', '', $qrPath);

        if (Storage::disk('public')->exists($storagePath)) {
            return Storage::disk('public')->delete($storagePath);
        }

        return false;
    }

    /**
     * Get QR code as base64 string
     */
    public function getQRAsBase64(string $qrPath): ?string
    {
        $storagePath = str_replace('/storage/', '', $qrPath);

        if (!Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        $imageData = Storage::disk('public')->get($storagePath);
        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
        
        $mimeType = $extension === 'svg' ? 'image/svg+xml' : 'image/png';
        
        return "data:{$mimeType};base64," . base64_encode($imageData);
    }

    /**
     * Check if QR code file exists
     */
    public function qrExists(string $qrPath): bool
    {
        if (empty($qrPath)) return false;
        
        $storagePath = str_replace('/storage/', '', $qrPath);
        return Storage::disk('public')->exists($storagePath);
    }

    /**
     * Get QR code file size
     */
    public function getQRSize(string $qrPath): ?int
    {
        $storagePath = str_replace('/storage/', '', $qrPath);

        if (!Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        return Storage::disk('public')->size($storagePath);
    }

    /**
     * Generate printable QR code (higher resolution)
     */
    public function generatePrintableQR(int $tableId, string $format = 'svg'): string
    {
        $table = \DB::table('tables')->where('id', $tableId)->first();
        if (!$table) {
            throw new \Exception("Table not found");
        }

        $timestamp = time();
        $qrCodeValue = "QR_{$table->table_number}_{$timestamp}";

        return $this->createQR($tableId, $qrCodeValue, $format)['path'];
    }

    /**
     * Batch delete QR codes
     */
    public function batchDeleteQR(array $qrPaths): array
    {
        $deleted = 0;
        $failed = 0;

        foreach ($qrPaths as $qrPath) {
            if ($this->deleteQRCode($qrPath)) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed
        ];
    }

    /**
     * Parse QR code value to extract table info
     * 
     * @param string $qrValue Format: QR_{TABLE_NUMBER}_{TIMESTAMP}
     * @return array ['table_number' => string, 'timestamp' => int]
     */
    public function parseQRValue(string $qrValue): array
    {
        // Expected format: QR_A1_1761053954
        $parts = explode('_', $qrValue);
        
        if (count($parts) < 3 || $parts[0] !== 'QR') {
            throw new \Exception("Invalid QR code format");
        }

        return [
            'table_number' => $parts[1],
            'timestamp' => (int) $parts[2]
        ];
    }
}