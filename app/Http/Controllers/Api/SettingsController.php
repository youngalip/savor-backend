<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    // Get all settings
    public function index(Request $request)
    {
        try {
            $query = Setting::query();

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $settings = $query->get()->groupBy('category');

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings'
            ], 500);
        }
    }

    // Get specific setting
    public function show($key)
    {
        try {
            $value = Setting::get($key);

            if ($value === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $value
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve setting'
            ], 500);
        }
    }

    // Get pricing rates (most used)
    public function getRates()
    {
        try {
            $rates = Setting::getPricingRates();

            return response()->json([
                'success' => true,
                'data' => [
                    'service_charge_rate' => $rates['service_charge_rate'],
                    'service_charge_percentage' => round($rates['service_charge_rate'] * 100, 2) . '%',
                    'tax_rate' => $rates['tax_rate'],
                    'tax_percentage' => round($rates['tax_rate'] * 100, 2) . '%'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rates'
            ], 500);
        }
    }

    // Update setting
    public function update(Request $request, $key)
    {
        try {
            $request->validate(['value' => 'required']);

            $updated = Setting::set($key, $request->value);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found or not editable'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'data' => [
                    'key' => $key,
                    'value' => Setting::get($key)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // Clear cache
    public function clearCache()
    {
        try {
            Setting::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Settings cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache'
            ], 500);
        }
    }
}