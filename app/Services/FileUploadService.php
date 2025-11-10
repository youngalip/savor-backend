<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FileUploadService
 * 
 * Handles file uploads with validation and storage management
 * Supports: Images (menu photos, etc.)
 */
class FileUploadService
{
    /**
     * Upload menu image
     * 
     * @param UploadedFile $file
     * @param int $menuId
     * @param string|null $oldImagePath
     * @return string Image URL path
     * @throws \Exception
     */
    public function uploadMenuImage(UploadedFile $file, int $menuId, ?string $oldImagePath = null): string
    {
        // Validate file
        $this->validateImage($file);

        // Delete old image if exists
        if ($oldImagePath) {
            $this->deleteFile($oldImagePath);
        }

        // Generate unique filename
        $filename = $this->generateFilename($file, $menuId, 'menu');

        // Store file
        $path = $file->storeAs('uploads/menus', $filename, 'public');

        return '/storage/' . $path;
    }

    /**
     * Upload generic image
     * 
     * @param UploadedFile $file
     * @param string $directory
     * @param string|null $customFilename
     * @return string Image URL path
     * @throws \Exception
     */
    public function uploadImage(UploadedFile $file, string $directory = 'uploads', ?string $customFilename = null): string
    {
        // Validate file
        $this->validateImage($file);

        // Generate filename
        $filename = $customFilename ?? $this->generateFilename($file);

        // Store file
        $path = $file->storeAs($directory, $filename, 'public');

        return '/storage/' . $path;
    }

    /**
     * Delete file from storage
     * 
     * @param string $filePath URL path (e.g., /storage/uploads/menus/image.jpg)
     * @return bool
     */
    public function deleteFile(string $filePath): bool
    {
        // Convert URL path to storage path
        $storagePath = str_replace('/storage/', '', $filePath);

        if (Storage::disk('public')->exists($storagePath)) {
            return Storage::disk('public')->delete($storagePath);
        }

        return false;
    }

    /**
     * Validate image file
     * 
     * @param UploadedFile $file
     * @return void
     * @throws \Exception
     */
    private function validateImage(UploadedFile $file): void
    {
        // Check if file is valid
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }

        // Validate mime type
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Invalid file type. Only JPG, PNG, and WebP are allowed.');
        }

        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds maximum limit of 5MB');
        }

        // Validate dimensions (optional - set your own requirements)
        $imageInfo = getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw new \Exception('Unable to read image dimensions');
        }

        [$width, $height] = $imageInfo;
        
        // Example: Maximum 4000x4000 pixels
        if ($width > 4000 || $height > 4000) {
            throw new \Exception('Image dimensions exceed maximum size of 4000x4000 pixels');
        }
    }

    /**
     * Generate unique filename
     * 
     * @param UploadedFile $file
     * @param int|null $id
     * @param string|null $prefix
     * @return string
     */
    private function generateFilename(UploadedFile $file, ?int $id = null, ?string $prefix = null): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = time();
        
        $parts = [];
        
        if ($prefix) {
            $parts[] = $prefix;
        }
        
        if ($id) {
            $parts[] = $id;
        }
        
        $parts[] = $timestamp;
        $parts[] = Str::random(8);
        
        return implode('_', $parts) . '.' . $extension;
    }

    /**
     * Get file size in human-readable format
     * 
     * @param string $filePath
     * @return string|null
     */
    public function getFileSize(string $filePath): ?string
    {
        $storagePath = str_replace('/storage/', '', $filePath);

        if (!Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        $bytes = Storage::disk('public')->size($storagePath);

        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Check if file exists
     * 
     * @param string $filePath
     * @return bool
     */
    public function fileExists(string $filePath): bool
    {
        $storagePath = str_replace('/storage/', '', $filePath);
        return Storage::disk('public')->exists($storagePath);
    }

    /**
     * Get file URL (for direct access)
     * 
     * @param string $filePath Storage path
     * @return string Full URL
     */
    public function getFileUrl(string $filePath): string
    {
        return url($filePath);
    }

    /**
     * Create directory if not exists
     * 
     * @param string $directory
     * @return bool
     */
    public function ensureDirectoryExists(string $directory): bool
    {
        if (!Storage::disk('public')->exists($directory)) {
            return Storage::disk('public')->makeDirectory($directory);
        }
        
        return true;
    }

    /**
     * Batch delete files
     * 
     * @param array $filePaths Array of file paths
     * @return array ['deleted' => [], 'failed' => []]
     */
    public function batchDelete(array $filePaths): array
    {
        $result = [
            'deleted' => [],
            'failed' => []
        ];

        foreach ($filePaths as $filePath) {
            try {
                if ($this->deleteFile($filePath)) {
                    $result['deleted'][] = $filePath;
                } else {
                    $result['failed'][] = $filePath;
                }
            } catch (\Exception $e) {
                $result['failed'][] = $filePath;
            }
        }

        return $result;
    }
}