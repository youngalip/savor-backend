<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QRCodeService
 * 
 * Handles QR code generation and management for table ordering system
 * Uses simplesoftwareio/simple-qrcode library
 */
class QRCodeService
{
    /**
     * QR Code size in pixels
     */
    private const QR_SIZE = 300;

    /**
     * QR Code margin
     */
    private const QR_MARGIN = 1;

    /**
     * Error correction level (L, M, Q, H)
     * H = High (30% of data can be restored)
     */
    private const ERROR_CORRECTION = 'H';

    /**
     * Generate QR code for table
     * 
     * @param int $tableId
     * @param string|null $oldQrPath Previous QR code path to delete
     * @return array ['qr_path' => string, 'qr_url' => string, 'token' => string]
     * @throws \Exception
     */
    public function generateTableQR(int $tableId, ?string $oldQrPath = null, string $format = 'svg'): array
    {
        // Delete old QR if exists
        if ($oldQrPath) {
            $this->deleteQRCode($oldQrPath);
        }

        // Generate token
        $token = $this->generateSecureToken();

        // Generate order URL
        $orderUrl = $this->generateOrderUrl($tableId, $token);

        // Generate QR (format png/svg)
        $qrData = $this->createQR($tableId, $orderUrl, $format);

        return [
            'qr_path' => $qrData['path'],   // path file di storage
            'qr_svg' => $qrData['svg'] ?? null, // hanya ada kalau format = svg
            'qr_url'  => $orderUrl,
            'token'   => $token
        ];
    }

    /**
     * Bulk generate QR codes for multiple tables
     * 
     * @param array $tableIds
     * @return array ['success' => [], 'failed' => []]
     */
    public function bulkGenerateQR(array $tableIds): array
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
     * Create QR code SVG/PNG and save to storage
     */
    private function createQR(int $tableId, string $url, string $format = 'svg'): array
    {
        $qrCode = QrCode::format($format)
            ->size(self::QR_SIZE)
            ->margin(self::QR_MARGIN)
            ->errorCorrection(self::ERROR_CORRECTION)
            ->generate($url);

        // Filename
        $ext = $format === 'svg' ? 'svg' : 'png';
        $filename = "table_{$tableId}_" . time() . ".{$ext}";

        // Directory
        $directory = 'uploads/qr-codes';
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $path = "{$directory}/{$filename}";

        if ($format === 'svg') {
            Storage::disk('public')->put($path, $qrCode);
            return ['path' => '/storage/' . $path, 'svg' => $qrCode];
        } else {
            Storage::disk('public')->put($path, $qrCode);
            return ['path' => '/storage/' . $path];
        }
    }


    /**
     * Generate QR code filename
     * 
     * @param int $tableId
     * @return string
     */
    private function generateQRFilename(int $tableId): string
    {
        return "table_{$tableId}_" . time() . ".png";
    }

    /**
     * Generate secure random token
     * 
     * @param int $length
     * @return string
     */
    private function generateSecureToken(int $length = 32): string
    {
        return Str::random($length);
    }

    /**
     * Generate order URL for QR code
     * 
     * @param int $tableId
     * @param string $token
     * @return string
     */
    private function generateOrderUrl(int $tableId, string $token): string
    {
        $baseUrl = config('app.url');
        return "{$baseUrl}/order?table={$tableId}&code={$token}";
    }

    /**
     * Delete QR code from storage
     * 
     * @param string $qrPath
     * @return bool
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
     * Verify QR code token
     * 
     * @param int $tableId
     * @param string $token
     * @return bool
     */
    public function verifyToken(int $tableId, string $token): bool
    {
        // TODO: Implement token verification logic
        // This should check if token is valid for the table
        // You might want to store tokens in database for verification
        
        return !empty($token) && strlen($token) === 32;
    }

    /**
     * Get QR code as base64 string (useful for API responses)
     * 
     * @param string $qrPath
     * @return string|null
     */
    public function getQRAsBase64(string $qrPath): ?string
    {
        $storagePath = str_replace('/storage/', '', $qrPath);

        if (!Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        $imageData = Storage::disk('public')->get($storagePath);
        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Generate QR code with custom styling
     * 
     * @param int $tableId
     * @param string $url
     * @param array $options Additional styling options
     * @return string QR code path
     * @throws \Exception
     */
    public function generateStyledQR(int $tableId, string $url, array $options = []): string
    {
        try {
            $qrCode = QrCode::format('png')
                ->size($options['size'] ?? self::QR_SIZE)
                ->margin($options['margin'] ?? self::QR_MARGIN)
                ->errorCorrection($options['error_correction'] ?? self::ERROR_CORRECTION);

            // Add color if specified
            if (isset($options['color'])) {
                $qrCode->color(...$options['color']); // [R, G, B]
            }

            // Add background color if specified
            if (isset($options['background_color'])) {
                $qrCode->backgroundColor(...$options['background_color']); // [R, G, B]
            }

            // Generate QR code
            $qrCodeImage = $qrCode->generate($url);

            // Generate filename
            $filename = $this->generateQRFilename($tableId);

            // Save to storage
            $directory = 'uploads/qr-codes';
            $path = "{$directory}/{$filename}";
            Storage::disk('public')->put($path, $qrCodeImage);

            return '/storage/' . $path;

        } catch (\Exception $e) {
            throw new \Exception("Failed to generate styled QR code: " . $e->getMessage());
        }
    }

    /**
     * Check if QR code file exists
     * 
     * @param string $qrPath
     * @return bool
     */
    public function qrExists(string $qrPath): bool
    {
        $storagePath = str_replace('/storage/', '', $qrPath);
        return Storage::disk('public')->exists($storagePath);
    }

    /**
     * Get QR code file size
     * 
     * @param string $qrPath
     * @return int|null File size in bytes
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
    public function generatePrintableQR(int $tableId, string $url, string $format = 'svg'): string
    {
        return $this->createQR($tableId, $url, $format)['path'];
    }


    /**
     * Batch delete QR codes
     * 
     * @param array $qrPaths
     * @return array ['deleted' => int, 'failed' => int]
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
}