<?php

namespace App\Services;

class StationAssignmentService
{
    /**
     * Mapping kategori ke station type
     */
    const CATEGORY_STATION_MAP = [
        'Kitchen' => 'kitchen',
        'Bar' => 'bar',
        'Pastry' => 'pastry',
    ];

    /**
     * Get station type dari nama kategori
     * 
     * @param string $categoryName
     * @return string|null kitchen|bar|pastry
     */
    public static function getStationFromCategory(string $categoryName): ?string
    {
        // Exact match
        if (isset(self::CATEGORY_STATION_MAP[$categoryName])) {
            return self::CATEGORY_STATION_MAP[$categoryName];
        }

        // Case-insensitive search
        $categoryNameLower = strtolower($categoryName);
        
        foreach (self::CATEGORY_STATION_MAP as $key => $station) {
            if (strtolower($key) === $categoryNameLower) {
                return $station;
            }
        }

        return null;
    }

    /**
     * Get category IDs berdasarkan station type
     * 
     * @param string $stationType kitchen|bar|pastry
     * @return array
     */
    public static function getCategoryIdsByStation(string $stationType): array
    {
        // Direct match: capitalize station type untuk match dengan nama kategori
        // 'kitchen' → 'Kitchen', 'bar' → 'Bar', 'pastry' → 'Pastry'
        $categoryName = ucfirst(strtolower($stationType));
        
        // Query langsung berdasarkan nama kategori
        $categoryIds = \App\Models\Category::where('name', $categoryName)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();
        
        // Jika tidak ada hasil, coba dengan fallback mechanism (loop semua kategori)
        if (empty($categoryIds)) {
            $categories = \App\Models\Category::where('is_active', true)->get();
            
            foreach ($categories as $category) {
                if (self::getStationFromCategory($category->name) === $stationType) {
                    $categoryIds[] = $category->id;
                }
            }
        }

        return $categoryIds;
    }

    /**
     * Get semua station types yang tersedia
     * 
     * @return array
     */
    public static function getAvailableStations(): array
    {
        return ['kitchen', 'bar', 'pastry'];
    }

    /**
     * Validate station type
     * 
     * @param string $stationType
     * @return bool
     */
    public static function isValidStation(string $stationType): bool
    {
        return in_array($stationType, self::getAvailableStations());
    }
}